<?php

declare(strict_types=1);

namespace Zolinga\Commons;

use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\RequestResponseEvent;

class ContactForm implements ListenerInterface
{
    public function onContactForm(RequestResponseEvent $event): void
    {
        global $api;

        // Handle the contact form submission
        $data = $event->request['data']
            or throw new \InvalidArgumentException('No data provided for contact form');

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
        $api->log->info('zolinga-commons', 'ContactForm: Sending contact form email to: ' . $to);
        if (!$mail->send($to)) {
            $api->log->error('zolinga-commons', 'ContactForm: Failed to send email to: ' . $to);
            $event->setStatus($event::STATUS_ERROR, dgettext('zolinga-commons', 'Failed to send email, please try again later.'));
            return;
        }

        $event->setStatus($event::STATUS_OK, 'Contact form submitted successfully');
    }
}