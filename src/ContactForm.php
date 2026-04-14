<?php

declare(strict_types=1);

namespace Zolinga\Commons;

use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\WebEvent;

class ContactForm implements ListenerInterface
{
    private function getDataHash(array $data): string
    {
        // Reorder, normalize and hash the data to create a consistent hash for the same data regardless of order
        ksort($data);
        return substr(md5(json_encode($data)), 0, 16); // 16 bytes hash
    }

    private function generateAuthToken(array $data): string
    {
        $dataHash = $this->getDataHash($data);
        $randHash = bin2hex(random_bytes(8));
        $token = $randHash . '-' . $dataHash;
        $_SESSION['zolinga-commons']['contactFormToken'] = $token;
        
        return $randHash;
    }

    private function checkAuthToken(string $token, array $data): bool
    {
        if (!$token || empty($_SESSION['zolinga-commons']['contactFormToken'])) {
            return false; // No token generated yet
        }

        $dataHash = $this->getDataHash($data);
        $tokenFull = $token . '-' . $dataHash;

        if (hash_equals($_SESSION['zolinga-commons']['contactFormToken'], $tokenFull)) {
            unset($_SESSION['zolinga-commons']['contactFormToken']); // Invalidate token after use
            return true;
        }

        return false; // Invalid token
    }

    public function onContactForm(WebEvent $event): void
    {
        global $api;

        // Handle the contact form submission
        $data = $event->request['data']
            or throw new \InvalidArgumentException('No data provided for contact form');

        if (!$this->checkAuthToken($event->request['token'] ?? '', $data)) {
            $event->response['token'] = $this->generateAuthToken($data);
            $event->setStatus($event::STATUS_CREATED, dgettext('zolinga-commons', 'Authorization token created.'));
            return;
        }

        // Build HTML email
        $html = '<h1>Contact Form Submission</h1>';

        $data['*IP'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $data['*User Agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $data['*Date'] = date('c');

        foreach($data as $key => $value) {
            $html .= '<p><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</p>';
        }

        // Send email
        $to = $api->config['contact']['email'];

        list($domain) = explode(':', $_SERVER['HTTP_HOST'] ?? gethostname() ?? 'localhost');
        $domainParts = explode('.', trim($domain, '.'));
        $hostname = implode('.', array_slice($domainParts, -2));
        $to = str_replace('{hostname}', $hostname, $to);

        $mail = new Email("Contact Form Submission", false, $html);

        $from = 'contact@' . $hostname;
        $mail->setHeader('From', $from);

        $api->log->info('zolinga-commons', 'ContactForm: Sending contact form email to: ' . $to);
        if (!$mail->send($to)) {
            $api->log->error('zolinga-commons', 'ContactForm: Failed to send email to: ' . $to);
            $event->setStatus($event::STATUS_ERROR, dgettext('zolinga-commons', 'Failed to send email, please try again later.'));
            return;
        }

        $event->setStatus($event::STATUS_OK, 'Contact form submitted successfully');
    }
}