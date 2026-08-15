<?php
/**
 * Every outbound email goes through mnj_send_mail(), never PHP's mail()
 * directly, so swapping in SMTP later (Hostinger's mail() deliverability is
 * fine for now but not guaranteed forever) means changing one function body,
 * not every call site.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function mnj_send_mail(string $to, string $subject, string $htmlBody): bool
{
    $from = mnj_config()['mail_from'] ?? 'no-reply@manojasolar.in';
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "From: Manoja Agencies <{$from}>\r\n";

    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
}
