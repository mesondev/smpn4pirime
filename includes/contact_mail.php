<?php

declare(strict_types=1);

function send_school_contact_reply(string $recipient, string $subject, string $body): bool
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) return false;

    $from = trim((string)(getenv('SIMS_MAIL_FROM') ?: 'smpn4pirime@gmail.com'));
    $fromName = trim((string)(getenv('SIMS_MAIL_FROM_NAME') ?: 'SMP Negeri 4 Pirime'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        app_log('error', 'Contact reply email sender is invalid');
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers = [
        'From: ' . $encodedName . ' <' . $from . '>',
        'Reply-To: SMP Negeri 4 Pirime <smpn4pirime@gmail.com>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    try {
        $sent = @mail($recipient, $encodedSubject, $body, implode("\r\n", $headers));
        if (!$sent) app_log('warning', 'Contact reply email transport rejected message');
        return $sent;
    } catch (Throwable $exception) {
        app_log('error', 'Contact reply email failed', ['type' => get_class($exception)]);
        return false;
    }
}
