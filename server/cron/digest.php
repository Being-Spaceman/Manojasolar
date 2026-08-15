<?php
/**
 * Daily digest — one email a day, not one per lead. Run by hPanel's cron
 * scheduler (see server/README.md for the exact expression); not web-facing.
 *
 * B2B leads first, then B2C, each ordered oldest-to-newest within its group
 * so the digest reads top-to-bottom in the order calls should probably
 * happen. Every lead carries a wa.me link with the message already typed —
 * one tap from the phone that receives this email opens the chat.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/leads.php';
require __DIR__ . '/../lib/mail.php';

$pdo = mnj_db();
$stmt = $pdo->query(
    "SELECT * FROM leads WHERE created_at > (NOW() - INTERVAL 24 HOUR)
     ORDER BY FIELD(lead_type, 'business', 'individual'), created_at ASC"
);
$leads = $stmt->fetchAll();

// Nothing to report — send nothing. A digest with zero rows every quiet day
// is exactly the kind of email that trains an inbox to stop reading it.
if (!$leads) {
    exit;
}

function mnj_digest_wa_link(array $lead): string
{
    $mobile = preg_replace('/\D/', '', (string) $lead['mobile']);
    $name = $lead['firm_name'] ?: $lead['contact_name'];
    $message = $lead['locale'] === 'en'
        ? "Hello {$name}, this is Manoja Agencies calling about your enquiry."
        : "नमस्कार {$name}, मनोजा एजन्सीकडून तुमच्या चौकशीबद्दल बोलतोय.";
    return 'https://wa.me/91' . $mobile . '?text=' . rawurlencode($message);
}

/** One lead row, inline-styled — Gmail's mobile app strips <style> blocks in <head>. */
function mnj_digest_row(array $lead): string
{
    $name = htmlspecialchars($lead['firm_name'] ?: $lead['contact_name']);
    $mobile = htmlspecialchars($lead['mobile']);
    $city = htmlspecialchars((string) $lead['city']);
    $products = htmlspecialchars((string) $lead['products'] ?: '—');
    $wa = htmlspecialchars(mnj_digest_wa_link($lead));

    return <<<HTML
    <tr>
      <td style="padding:10px 12px;border-bottom:1px solid #edebe4;font-size:14px;color:#14171a">
        <strong>{$name}</strong><br>
        <span style="color:#55595d;font-size:12.5px">{$mobile} · {$city} · {$products}</span>
      </td>
      <td style="padding:10px 12px;border-bottom:1px solid #edebe4;text-align:right">
        <a href="{$wa}" style="background:#007b3c;color:#fff;text-decoration:none;font-weight:700;font-size:13px;padding:8px 12px;display:inline-block">WhatsApp</a>
      </td>
    </tr>
    HTML;
}

$business = array_filter($leads, static fn($l) => $l['lead_type'] === 'business');
$individual = array_filter($leads, static fn($l) => $l['lead_type'] === 'individual');

function mnj_digest_section(string $title, array $leads): string
{
    if (!$leads) {
        return '';
    }
    $rows = implode('', array_map('mnj_digest_row', $leads));
    return <<<HTML
    <tr><td style="padding:18px 12px 4px;font-size:13px;font-weight:800;color:#04492a;text-transform:uppercase;letter-spacing:.04em">{$title}</td></tr>
    <tr><td style="padding:0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">{$rows}</table></td></tr>
    HTML;
}

$body = mnj_digest_section('Business — ' . count($business) . ' new', $business)
    . mnj_digest_section('Individual — ' . count($individual) . ' new', $individual);
$leadCount = count($leads);

$html = <<<HTML
<!doctype html>
<html>
<body style="margin:0;padding:0;background:#f7f6f2;font-family:Arial,Helvetica,sans-serif">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f6f2;padding:16px 0">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border:1px solid #e2e0d9">
          <tr>
            <td style="background:#04492a;color:#fff;padding:16px;font-size:16px;font-weight:800">
              Manoja Agencies — daily leads
            </td>
          </tr>
          <tr>
            <td style="padding:4px 12px 8px;font-size:12.5px;color:#55595d">
              Last 24 hours · {$leadCount} new lead(s)
            </td>
          </tr>
          {$body}
          <tr>
            <td style="padding:16px 12px;font-size:12px;color:#55595d;border-top:1px solid #edebe4">
              Full detail, filters and export: <a href="https://manojasolar.in/leads/" style="color:#007b3c">manojasolar.in/leads</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

mnj_send_mail(mnj_config()['mail_to'], 'Manoja Agencies — ' . count($leads) . ' new lead(s) today', $html);
