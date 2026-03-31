<?php
/**
 * KND Store - Mail abstraction
 * Uses PHP mail() for now; designed for future SMTP swap.
 */

function knd_mail(string $to, string $subject, string $bodyPlain, array $options = []): bool {
    $from = $options['from'] ?? null;
    $replyTo = $options['reply_to'] ?? null;

    $domain = $_SERVER['SERVER_NAME'] ?? 'kndstore.com';
    $domain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $domain);
    $defaultFrom = 'no-reply@' . $domain;

    $headers = [
        'From: KND Store <' . ($from ?: $defaultFrom) . '>',
        'Reply-To: ' . ($replyTo ?: $defaultFrom),
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: KND-Store-PHP',
    ];

    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $bodyPlain = trim($bodyPlain);

    return @mail($to, $subject, $bodyPlain, implode("\r\n", $headers));
}
