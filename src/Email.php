<?php

namespace Zolinga\Commons;
use Exception;
use DOMDocument;

// RFC 2822 demands CRLF for SMTP
// But except for Windows mail() is not talking to SMTP but to native apps like
// postfix that expect native newlines on input.
// @see https://bugs.php.net/bug.php?id=15841
define("ELIXONMAIL_LF", PHP_EOL);

/**
 * $mail=new Email($subject, $text, $html);
 * $mail->addAttachment('test.txt', 'test me', 'text/plain');
 * $mail->send($to);
 *
 * $mail=new Email();
 * $mail->setMessage($text, $html); // subject is taken from HTML's TITLE tag
 * $mail->send($to);
 *
 * $mail=new Email;
 * $mail->addHeader('Subject', 'My Email');
 * $mail->addHeader('From', 'me <somewhere@example.com>');
 * $att=new Email;
 * $att->setContents($text, 'text/plain');
 * $att->addHeader('Content-Disposition', 'attachment; filename=test.txt');
 * $mail->append($att);
 * $mail->send($to);
 *
 * Inline Attachments
 * $mail=new Email('čeština', 'čeština test me', '<img src="cid:globaly-unique-cid"/>');
 * $mail->addInline('myimg.png', file_get_contents($img), 'image/png', 'globaly-unique-cid');
 * $mail->send($to);
 *
 * Variables: HTML & text messages and header values may include $variable or ${variable} strings.
 * $mail->send($to, array('name' => 'John Doe', 'link' => 'http://www.example.com/john-doe'));
 *
 * HTML content set using the $mail->setMessage() or
 * $mail->loadMessage() will automatically embed images linked from IMG
 * tags. Images are searched relativelly to cwd for $mail->setMessage()
 * or relatively to HTML file location in case of $o->loadMessage().
 *
 * Notice: If "Subject" header was not set yet then TITLE tag from the
 * HTML will be used. Meta tag "content-type" is pushed into e-mail
 * headers. Also all <meta name="mail.{HEADER}" content="{VALUE}" /> tags
 * will be extracted and added as headers. E.g. <meta name="mail.From" content="info@example.com"/>
 *
 * $mail=new Email('Imported test');
 * $mail->loadMessage('mail.txt', 'mail.html');
 * $mail->send($to, $variables);
 *
 * @package    Zolinga
 * @author     Daniel Sevcik <sevcik@webdevelopers.cz>
 * @version    2.0
 * @copyright  2011 Daniel Sevcik
 * @since      2011-09-15T13:16:21+0200
 * @access     public
 */
class Email
{
    /**
     * How to deal with relative-path images
     *
     * @access public
     * @var string 'embed': attach mails as inline-atttachments, 'link': (default) make full URL links
     */
    public string $htmlImageInsert = 'link';

    /**
     * Use this domain when fixing relative paths (<img>)
     * @access public
     * @var string URL default current URL
     */
    public $htmlBaseURL = false;

    private $httpHost = 'localhost';
    private $vars = array();
    private $boundary;
    private $headers = array();
    private $multiparts = array();
    private $messageId = false;

    /**
     * Content of the mail's MIME part
     * @access private
     * @var mixed string raw contents or DOMDocument for text/html parts.
     */
    private $contents = false;
    private $encoding = false;
    private $uniqueHeaders = array(
        'content-id',
        'content-disposition',
        'content-type',
        'content-transfer-encoding',
        'subject',
        'mime-version',
        'user-agent',
        'from',
        'message-id',
        'x-mailer',
        'dkim-signature'
    );
    private $rootHeaders = array(
        array('User-Agent', 'Thunderbird 1.5.0.10 (X11/20070306) Email/2.1'),
        array('MIME-Version', '1.0'),
        array('Content-Transfer-Encoding', '8bit'),
        array('X-Emailer', 'Elixon Email v2.1')
    );

    /**
     * RE to match any text contents (HTML/XML/text)
     * @access private
     * @var string
     */
    const TEXT_MIME_RE = '@^(text/|application/(xhtml|xml))@i';

    /**
     * RE to match any rich text contents (HTML/XML)
     * @access private
     * @var string
     */
    const RICH_TEXT_MIME_RE = '@^(text/html|application/(xhtml|xml))@i';

    /**
     * Constructor.
     *
     * @access public
     * @param mixed $subject Content of `Subject` header or false (default)
     * @param mixed $text contents of the e-mail or false (default)
     * @param mixed $html contents of the e-mail or false (default)
     * @return void
     */
    public function __construct($subject = false, $text = false, $html = false)
    {
        global $api;

        if ($_SERVER["HTTP_HOST"] ?? false) {
            [$this->httpHost] = explode(':', $_SERVER["HTTP_HOST"] ?? '');
        } else {
            $this->httpHost = 'localhost';
        }

        $this->htmlBaseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http') . '://' . $this->httpHost . ($_SERVER["REQUEST_URI"] ?? '/');
        $this->boundary = $this->mkUniqueId('E2Email-Boundary-');
        $this->messageId = $this->mkUniqueId();
        $this->rootHeaders[] = array('Message-ID', '<' . $this->messageId . '>');
        $this->rootHeaders[] = array('From', 'web@' . $this->httpHost);

        $this->addHeader('Subject', $subject);
        $this->setMessage($text, $html);
    }

    public function addVars($arr)
    {
        return $this->vars = array_merge($this->vars, $arr);
    }

    public function getVars($append = array())
    {
        return array_merge($this->vars, $append);
    }

    public function loadMessage($textFile = false, $htmlFile = false)
    {
        $oldDir = getcwd();

        if ($htmlFile) chdir(dirname($htmlFile));
        $this->setMessage(
            $textFile && is_readable($textFile) ? file_get_contents($textFile) : false,
            $htmlFile && is_readable($htmlFile) ? file_get_contents($htmlFile) : false
        );
        chdir($oldDir);

        return $this;
    }

    public function getSource($vars = array(), $returnParts = false, $omitRootHeaders = false)
    {
        $vars = $this->getVars($vars);

        $parts = array('from' => '', 'to' => '', 'subject' => '', 'message' => '', 'headers' => array());
        $headers = $omitRootHeaders ? array() : $this->rootHeaders;

        $envelop = $this;
        $headers = array_merge($headers, $envelop->getHeaders());

        // Multipart with only one part = optimize
        while (count($envelop->getMultiparts()) == 1) {
            list($envelop) = $envelop->getMultiparts();
            $headers = array_merge($headers, $envelop->getHeaders());
        }

        // Message
        $parts['message'] .= $envelop->getSourceMessage($vars);

        // Headers
        $parts = array_merge($parts, $envelop->getSourceHeaders($vars, $headers));

        // Take missing Subject from any nested HTML's title (see self::parseHTML())
        if (!$omitRootHeaders && !strlen($parts['subject'])) {
            $parts['subject'] = $this->prepareHeader('Subject', $this->getHeaderDeep('subject'));
        }

        // For future implementation of DKIM and such
        $parts = $this->filter($parts);

        $parts['headers'] = implode(ELIXONMAIL_LF, $parts['headers']) . ELIXONMAIL_LF;

        if ($returnParts) {
            $ret = $parts;
        } elseif (!strlen(trim($parts['message'])) && preg_match(self::TEXT_MIME_RE, $envelop->getHeader('content-type'))) {
            $ret = ''; // missing HTML or TEXT part.
        } else {
            $ret = '';
            if (strlen(trim($parts['to']))) $ret .= 'To: ' . $parts['to'] . ELIXONMAIL_LF;
            if (strlen(trim($parts['subject']))) $ret .= 'Subject: ' . $parts['subject'] . ELIXONMAIL_LF;
            $ret .= $parts['headers'];
            $ret .= ELIXONMAIL_LF;
            $ret .= $parts['message'];
        }

        return $ret;
    }

    private function getSourceMessage($vars)
    {
        $vars = $this->getVars($vars);

        $ret = '';
        if (count($this->getMultiparts()) == 0) {
            $mime = $this->getHeader('content-type');
            $contents = $this->getContents();
            // Replace vars
            if (preg_match(self::TEXT_MIME_RE, $mime)) {
                $escape = preg_match(self::RICH_TEXT_MIME_RE, $mime);
                $contents = $this->replaceVars($contents, $vars, $escape);
                // if (PHP_SAPI == 'cli') print_r(array($vars, $contents, $escape));
            }
            switch ($this->encoding) {
                case 'quoted-printable':
                    $contents = quoted_printable_encode($contents);
                    // quoted_printable_encode() generates RFC-compliant
                    // CRLF line endings but we speak to sendmail or other
                    // local client and we must use local
                    // line-endings. There was an issue with outlook.cn on
                    // Amazon's Chinese server.
                    $contents = preg_replace('/(*BSR_ANYCRLF)\R/', ELIXONMAIL_LF, $contents);
                    break;
                case 'base64':
                    $contents = chunk_split(base64_encode($contents), 76, ELIXONMAIL_LF);
                    break;
            }
            $ret .= $contents;
        } else {
            foreach ($this->getMultiparts() as $d) {
                if (strlen($msg = $d->getSource($vars, false, true))) {
                    $ret .= '--' . $this->boundary . ELIXONMAIL_LF;
                    $ret .= $msg;
                    $ret .= ELIXONMAIL_LF . ELIXONMAIL_LF;
                }
            }
            $ret .= '--' . $this->boundary . '--';
        }
        return $ret;
    }

    private function getSourceHeaders($vars, $headers)
    {
        $vars = $this->getVars($vars);

        $parts = array('to' => '', 'from' => '', 'headers' => array());
        foreach ($headers as $d) {
            $d[1] = $this->replaceVars($d[1], $vars, false);
            $val = $this->prepareHeader($d[0], $d[1]);

            switch (strtolower($d[0])) {
                case 'to':
                    $parts['to'] = trim(@$parts['to'] . ', ' . $val, ', ');
                    break;
                case 'from':
                    $parts['from'] = $val;
                    $parts['headers']['from'] = $d[0] . ": " . $val;
                    break;
                case 'subject':
                    $parts['subject'] = $val;
                    break;
                default:
                    $key = in_array(strtolower($d[0]), $this->uniqueHeaders) ? strtolower($d[0]) : count($parts['headers']);
                    $parts['headers'][$key] = $d[0] . ": " . $val;
            }
        }
        return $parts;
    }

    public function send($to = false, $vars = array(), $exceptions = 1): bool
    {
        $vars = $this->getVars($vars);

        if ($to) $this->addHeader('To', $to);
        $parts = $this->getSource($vars, true);
        $from = preg_replace('@^.+<|>.*$@', '', $parts['from']); // beware of format 'XY <mail>'

        // mail($parts['to'], $parts['subject'], $parts['message'], $parts['headers'], '-f ' . escapeshellarg($from)))
        if (!($return = $this->sendEmail($from, $parts['to'], $parts['subject'], $parts['message'], $parts['headers'])) && $exceptions) {
            $log = tempnam(sys_get_temp_dir(), 'elixonmail-error-');
            file_put_contents($log . '.json', json_encode(array('return' => $return, 'data' => $parts, 'vars' => $vars, 'stamp' => date('c'), 'serialized' => json_encode($this, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))));
            file_put_contents($log . '.mail', $source = $this->getSource($vars, false));
            $this->notifyAdmin('Email failed', 'See log file: ' . $log . '*' . "\n\n" . $source);
            throw new Exception('Email failed - see log file: ' . $log . '*', 540);
        }

        return $return;
    }

    private function sendEmail($from, $to, $subject, $message, $headers): bool
    {
        return mail($to, $subject, $message, $headers, '-f ' . escapeshellarg($from));
    }

    /**
     * For implementation of DKIM and such.
     *
     * Example:
     *
     * class DkimEmail extends Email {
     *   protected function filter($parts) {
     *      ... sign parts ...
     *      return parent::filter($parts);
     *   }
     * }
     *
     * @access protected
     * @param array $parts with e-mail parts
     * @return array $parts
     */
    protected function filter($parts)
    {
        return $parts;
    }

    private function prepareHeader($name, $value)
    {
        switch (strtolower($name)) {
            case 'subject':
                $encValue = $this->encodeMIMEHeader(trim($value));
                break;
            case 'content-type':
                if (preg_match('@^multipart/@', $value)) {
                    $encValue = $value . ';' . ELIXONMAIL_LF . '	boundary="' . $this->boundary . '"';
                } else {
                    $encValue = $value;
                }
                break;
            default:
                $encValue = $value;
        }
        return $encValue;
    }

    /**
     * Set text/HTML content for this envelpe.
     *
     * @access public
     * @param mixed $text false or string with text part of the e-mail
     * @param mixed $html false or HTML string or DOMDocument for the HTML part of the email.
     * @param string $charSet default utf-8
     * @return $this
     */
    public function setMessage(false|string $text = false, false|string $html = false, $charSet = 'utf-8')
    {
        if (!strlen(trim($text)) && (!($html instanceof DOMDocument) && !strlen(trim($html)))) return;

        $mail = new Email;
        $mail->addHeader('Content-Type', 'multipart/alternative');

        $parts = array();
        $encoding = is_callable('quoted_printable_encode') ? 'quoted-printable' : 'base64';
        if ($text) $parts[] = array($text, 'text/plain; charset="' . $charSet . '"', $encoding);
        if ($html) {
            $meta = get_meta_tags('data://text/plain;base64,' . base64_encode($html instanceof DOMDocument ? $html->saveHTML() : $html));
            $mime = isset($meta['content-type']) ? $meta['content-type'] : 'text/html; charset="' . $charSet . '"';
            $parts[] = array($this->parseHTML($html), $mime, $encoding);
        }
        foreach ($parts as $data) {
            $part = new Email;
            $part->addHeader('Content-Transfer-Encoding', $data[2]);
            $part->setContents($data[0], $data[1], $encoding);
            $mail->append($part);
        }

        // $this->addHeader('Content-Type', 'multipart/mixed'); - multipart/mixed: Thunderbird didn't show images - all were just attachments; Mac e-mail client showed images + attachments
        $this->addHeader('Content-Type', 'multipart/related'); // note: google seems to hide all attachments that are refered from content using Content-ID
        $this->append($mail);

        return $this;
    }

    public function addAttachment($fileName, $data, $contentType, $extraHeaders = array())
    {
        $att = new Email;
        $att->addHeader('Content-Description', $fileName);
        $att->addHeader('Content-Disposition', 'attachment; filename="' . addcslashes($fileName, '"') . '"');
        $att->addHeader('Content-Transfer-Encoding', 'base64');
        $att->setContents($data, $contentType . '; name="' . addcslashes($fileName, '"') . '"', 'base64');

        // Headers
        foreach ($extraHeaders as $header) {
            $att->addHeader($header[0], $header[1]);
        }

        $this->addHeader('Content-Type', 'multipart/related'); // see self::setMessage() why multipart/mixed is not good
        $this->append($att);
    }

    public function addInline($fileName, $data, $contentType, $cid = false, $extraHeaders = array())
    {
        if (!$cid) $cid = $this->mkUniqueId($fileName . '-');  // http://en.wikipedia.org/wiki/MIME#Content-ID

        $headers = array();
        $headers[] = array('Content-ID', '<' . $cid . '>');
        $headers[] = array('Content-Disposition', 'inline; filename="' . addcslashes($fileName, '"') . '"');
        $this->addAttachment($fileName, $data, $contentType, array_merge($headers, $extraHeaders));

        return $cid;
    }


    /**
     * Remove all previous $name and add this one.
     *
     * @access public
     * @param string $name header name
     * @param string $value header value
     * @return $this
     */
    public function setHeader($name, $value)
    {
        foreach ($this->headers as $k => $v) {
            if ($name == strtolower($v[0])) {
                unset($this->headers[$k]);
            }
        }
        return $this->addHeader($name, $value);
    }

    /**
     * Add new header. Note: headers that are allowed only in one copy
     * (e.g. "To" header) will be joined to existing headers.
     *
     * If you want to overwrite the header, use Email::setHeader() instead.
     *
     * @access public
     * @param string $name header name
     * @param string $value header value
     * @return $this
     */
    public function addHeader($name, $value)
    {
        if (!strlen(trim($value))) return $this;

        $this->headers[] = array($name, $value);
        return $this;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($name)
    {
        $name = strtolower($name);
        $ret = false;
        foreach ($this->headers as $data) {
            if ($name == strtolower($data[0])) {
                $ret = $data[1];
            }
        }
        return $ret;
    }

    /**
     * Return the contents of this mail part.
     *
     * @access public
     * @param bool $textData true - return string, false - return original value (DOMDocument for text/html part)
     * @return mixed based on $textData property either string or DOMDocument for text/html part
     */
    public function getContents($textData = true)
    {
        if ($textData) {
            if ($this->contents instanceof DOMDocument) {
                return $this->contents->saveHTML() ?: null;
            } else {
                return is_string($this->contents) ? $this->contents : null;
            }
        }

        return $this->contents;
    }

    public function getMultiparts()
    {
        return $this->multiparts;
    }

    public function append(Email $part)
    {
        if ($this->contents !== false) throw new Exception("The Email Envelope cannot contain multiparts.");

        $this->multiparts[] = $part;

        // Reorder
        if (preg_match('@^multipart/(mixed|related)@', $this->getHeader('content-type'))) {
            usort($this->multiparts, array($this, 'sortAttachments'));
        }

        return $this;
    }

    private function sortAttachments($a, $b)
    {
        $dispA = $a->getHeader('content-disposition');
        $dispB = $b->getHeader('content-disposition');
        return $dispA <=> $dispB;
    }

    /**
     * Set the envelope's contents. Cannot be called on multipart envelops.
     *
     * @access public
     * @param mixed $data string or DOMDocument. DOMDocument is allowed only for $contentType="text/html"
     * @param string $contentType MIME type
     * @param string $encoding
     * @return $this
     */
    public function setContents($data, $contentType, $encoding = false)
    {
        if (count($this->multiparts)) throw new Exception("The Email Envelope contains multiparts. You cannot set contents.");

        // To make DOMDocuments serializable
        if ($data instanceof DOMDocument) {
            $data = EmailDocument::import($data);
        }

        $this->addHeader('Content-Type', $contentType);
        $this->encoding = $encoding;
        $this->contents = $data;
        return $this;
    }

    private function mkUniqueId($prefix = 'e2.')
    {
        return $prefix . sprintf('%.0f', microtime(true) * 1000) . '.' . dechex(rand(0xF0000000, 0xFFFFFFFF)) . '@' . $this->httpHost;
    }

    private function encodeMIMEHeader($val, $charSet = 'utf-8')
    {
        // not working in Subject - mb_encode_mimeheader($value, "UTF-8", "B");
        // works? return mb_encode_mimeheader($val, 'UTF-7', "Q");
        $ascii = mb_detect_encoding($val, array('ASCII', $charSet)) == 'ASCII';
        return !$ascii ? "=?utf-8?B?" . base64_encode($val) . "?=" : $val;
    }

    private function replaceVars($data, $vars, $escape)
    {
        $vars = array_merge($this->getVars(), $vars);

        foreach ($vars as $name => $val) {
            $val = $escape ? htmlspecialchars($val) : $val;
            $data = str_replace(array('$' . $name, '${' . $name . '}'), $val, $data);
            $data = str_replace(array(rawurlencode('$' . $name), rawurlencode('${' . $name . '}')), $val, $data);
        }
        return $data;
    }

    /**
     * Search for first header value in current and all nested envelops.
     *
     * @access private
     * @param string $name header name
     * @return mixed false of string
     */
    private function getHeaderDeep($name)
    {
        $search = array_merge(array($this), $this->getMultiparts());
        foreach ($search as $p) {
            if (strlen($val = $p->getHeader($name))) {
                return $val;
            }
        }
        return false;
    }

    /**
     * Process HTML.
     *
     * @access private
     * @param mixed $html string or DOMDocument
     * @return DOMDocument
     */
    private function parseHTML($html)
    {
        if ($html instanceof DOMDocument) {
            $dom = $html;
        } else {
            $dom = new DOMDocument;
            if (!$dom->loadHTML($html)) {
                throw new Exception("Cannot load HTML: " . $html, 794);
            }
        }
        $replaced = 0;
        $baseURL = parse_url($this->htmlBaseURL);

        // Embed Images
        // 2014-03-24T14:13:57+0100 Lengthy discussion with David in person and on Skype. David unreasonably demands removal of this feature.
        // 2014-03-25T18:09:21+0100 David allowed to re-enable it.
        foreach (array($dom->getElementsByTagName('img'), $dom->getElementsByTagName('IMG')) as $list) {
            foreach ($list as $img) {
                $src = $img->getAttribute('src');
                if (!preg_match('@^[/.]@', $src)) {
                    continue; // not relative link
                }

                if (!$img->hasAttribute('alt')) {
                    $alt = basename($src);
                    $alt = preg_replace('@[#?].*$@', '', basename($alt));
                    $alt = preg_replace('@\.[a-z]{3,4}$@', '', basename($alt));
                    $alt = preg_replace('@\W+@', ' ', $alt);
                    $img->setAttribute('alt', trim($alt));
                }

                if ($this->htmlImageInsert == 'link') {
                    $img->setAttribute('src', $baseURL['scheme'] . '://' . $baseURL['host'] . '/' . ltrim($src, './'));
                } else {
                    $src = $this->findFile($src, array('jpg', 'png', 'gif', 'bmp', 'jpeg', 'ico'));
                    if (!is_file($src)) {
                        trigger_error("Email: Cannot locate image `$src`", E_USER_WARNING);
                        continue;
                    }
                    $mime = 'image/' . str_replace('jpg', 'jpeg', strtolower(pathinfo($src, PATHINFO_EXTENSION)));
                    $cid = $this->addInline(pathinfo($src, PATHINFO_BASENAME), file_get_contents($src), $mime, false, array(array('X-Content-Src', $img->getAttribute('src'))));
                    $img->setAttribute('src', 'cid:' . $cid);
                }
                $replaced++;
            }
        }

        // Use HTML TITLE as Subject
        if (!$this->getHeader('subject')) {
            foreach (array($dom->getElementsByTagName('title'), $dom->getElementsByTagName('TITLE')) as $list) {
                if ($list->length) {
                    $this->addHeader('Subject', $list->item(0)->textContent);
                    break;
                }
            }
        }
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (preg_match('/^mail\./', $meta->getAttribute('name'))) {
                $header = substr($meta->getAttribute('name'), 5);
                if (!strlen($content = $meta->getAttribute('content'))) {
                    trigger_error("Empty @content attribute in meta tag 'mail.$header' - ignoring.", E_USER_ERROR);
                    continue;
                }
                // trigger_error("Copying HTML content <meta> tag \"mail.$header\" with value \"$content\" to e-mail header \"$header\".", E_USER_NOTICE);
                $this->addHeader($header, $content);
            }
        }

        // return $replaced || $html instanceof DOMDocument ? $dom->saveHTML() : $html;
        return $dom;
    }

    /**
     * Try to locate the referenced file (image).
     *
     * @access private
     * @param string $src Path as given in @src attribute
     * @param array $allowedExtensions lower-case array of allowed file extensions
     * @return string with real path or false
     */
    private function findFile($src, $allowedExtensions = false)
    {
        // is absolute path?
        if (!is_file($test = $src))
            if (!is_file($test = './' . $src)) // May be relative to a current path (usually web root)
                if (!is_file($test = $_SERVER['DOCUMENT_ROOT'] . '/' . $src)) // Test Web Root
                {
                    return false;
                } // not found

        // Security
        $test = realpath($test);
        if (is_array($allowedExtensions)) {
            $ext = strtolower(pathinfo($test, PATHINFO_EXTENSION));
            if (array_search($ext, $allowedExtensions) === false) {
                trigger_error("Email: Extension $ext is not white listed in " . implode(", ", $allowedExtensions) . " ($test)", E_USER_ERROR);
                return false;
            }
        }

        return $test;
    }

    private function notifyAdmin($subject, $message)
    {
        //$mail='info@'.preg_replace('/^(www\d*)\./', '', strtolower($this->httpHost));
        $mail = 'error@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return mail($mail, $subject, $message);
    }

    public function getByContentType($contentType)
    {
        $list = array();
        if (false !== strpos($this->getHeader('content-type'), $contentType)) {
            $list[] = $this;
        }

        foreach ($this->getMultiparts() as $part) {
            $list = array_merge($list, $part->getByContentType($contentType));
        }

        return $list;
    }

    public function __toString()
    {
        return __CLASS__ . '["' . $this->getHeader('subject') . '",' . $this->messageId . ']';
    }
} /* Class End */
