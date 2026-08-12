<?php
/**
 * Lead intake. POST only, no session, no framework.
 *
 * Accepts both the B2B and B2C fieldsets in one request — `lead_type` decides
 * which fields are read and which are required. Mirrors the client-side
 * validation in LeadForm.astro exactly, because the client can never be
 * trusted: JS can be off, the honeypot can be bypassed manually, and a bot
 * can POST here directly without ever loading the page.
 *
 * Two response shapes, chosen by the request itself:
 *   - JS-enhanced submissions fetch() with `Accept: application/json` and get
 *     back {"ok":true} or {"ok":false,"errors":{...}}, which LeadForm.astro's
 *     script turns into inline field errors.
 *   - A no-JS submission is a plain browser form POST with no such header.
 *     It cannot render inline errors — there is no script to do it — so it
 *     gets a redirect on success (to the thank-you page the form told us
 *     about) and a minimal static error page on failure. This is what makes
 *     "either fieldset submits correctly without JS" actually true rather
 *     than a claim that only holds once you add a script tag.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/db.php';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function mnj_fail(int $status, array $errors, string $locale, bool $wantsJson): never
{
    http_response_code($status);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $heading = $locale === 'en' ? 'That did not go through' : 'ते पाठवलं गेलं नाही';
    $back = $locale === 'en' ? 'Go back' : 'मागे जा';
    echo '<!doctype html><html lang="' . htmlspecialchars($locale) . '"><meta charset="utf-8">'
        . '<title>' . htmlspecialchars($heading) . '</title>'
        . '<body style="font-family:sans-serif;max-width:32rem;margin:3rem auto;padding:0 1rem">'
        . '<h1>' . htmlspecialchars($heading) . '</h1><ul>';
    foreach ($errors as $text) {
        echo '<li>' . htmlspecialchars($text) . '</li>';
    }
    echo '</ul><p><a href="javascript:history.back()">' . htmlspecialchars($back) . '</a></p></body></html>';
    exit;
}

function mnj_ok(string $thanksPath, bool $wantsJson): never
{
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }
    header('Location: ' . ($thanksPath !== '' ? $thanksPath : '/thank-you'), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'errors' => ['_' => 'Method not allowed.']]);
    }
    exit;
}

$locale = ($_POST['locale'] ?? 'mr') === 'en' ? 'en' : 'mr';
$thanksPath = (string) ($_POST['thanks_path'] ?? ($locale === 'en' ? '/en/thank-you' : '/thank-you'));

// --- honeypot ----------------------------------------------------------------
// A filled hidden `website` field means a bot filled every input it could find.
// Return the same success shape a real submission gets and insert nothing —
// telling the bot it worked is cheaper than teaching it this form is guarded.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    mnj_ok($thanksPath, $wantsJson);
}

$msg = [
    'mr' => [
        'leadTypeRequired' => 'कृपया व्यवसाय की वैयक्तिक ते निवडा.',
        'firmNameRequired' => 'कृपया फर्मचं नाव लिहा.',
        'contactNameRequired' => 'कृपया नाव लिहा.',
        'mobileRequired' => 'कृपया मोबाईल नंबर लिहा.',
        'mobileInvalid' => 'सहा, सात, आठ किंवा नऊ ने सुरू होणारा १० अंकी मोबाईल नंबर लिहा.',
        'cityRequired' => 'कृपया शहराचं नाव लिहा.',
        'gstinInvalid' => 'हा जीएसटीआयएन बरोबर वाटत नाही.',
        'consentRequired' => 'पाठवण्यापूर्वी कृपया संमती द्या.',
        'rateLimited' => 'खूप विनंत्या झाल्या आहेत. कृपया थोड्या वेळाने पुन्हा प्रयत्न करा.',
        'server' => 'काहीतरी चुकलं. कृपया पुन्हा प्रयत्न करा किंवा व्हॉट्सअ‍ॅपवर संदेश पाठवा.',
    ],
    'en' => [
        'leadTypeRequired' => 'Please choose one of the two options.',
        'firmNameRequired' => 'Please enter the firm name.',
        'contactNameRequired' => 'Please enter a name.',
        'mobileRequired' => 'Please enter a mobile number.',
        'mobileInvalid' => 'Enter a 10-digit mobile number starting with 6, 7, 8 or 9.',
        'cityRequired' => 'Please enter a city.',
        'gstinInvalid' => 'That does not look like a valid GSTIN.',
        'consentRequired' => 'Please agree before sending.',
        'rateLimited' => 'Too many requests. Please try again in a little while.',
        'server' => 'Something went wrong. Please try again or message us on WhatsApp.',
    ],
][$locale];

$post = static fn(string $key): string => trim((string) ($_POST[$key] ?? ''));

$leadType = $post('lead_type');
$errors = [];

if (!in_array($leadType, ['business', 'individual'], true)) {
    $errors['lead_type'] = $msg['leadTypeRequired'];
}

// Field names are prefixed per audience (biz_/ind_) in the markup so that a
// no-JS submission — which renders both fieldsets at once — never has two
// same-named inputs colliding in the POST body. Only the fields for the
// selected lead_type are read; the other fieldset's values, if any leaked
// through, are ignored.
if ($leadType === 'business') {
    $firmName = $post('biz_firm_name');
    $contactName = $post('biz_contact_name');
    $mobile = preg_replace('/\D/', '', $post('biz_mobile'));
    $city = $post('biz_city');
    $gstin = strtoupper($post('biz_gstin'));
    $message = $post('biz_message');
    $products = array_values(array_filter(array_map('trim', $_POST['biz_products'] ?? [])));
    $billAmount = null;
    $usageUnits = null;
    $roofType = null;

    if ($firmName === '') $errors['firm_name'] = $msg['firmNameRequired'];
    if ($contactName === '') $errors['contact_name'] = $msg['contactNameRequired'];
    if ($city === '') $errors['city'] = $msg['cityRequired'];
    if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
        // GSTIN is always optional. If present, only the format is checked — never
        // the checksum — because rejecting a live lead over a checksum mismatch
        // costs more than it protects against.
        $errors['gstin'] = $msg['gstinInvalid'];
    }
} elseif ($leadType === 'individual') {
    $firmName = null;
    $contactName = $post('ind_contact_name');
    $mobile = preg_replace('/\D/', '', $post('ind_mobile'));
    $city = null;
    $gstin = null;
    $message = null;
    $products = [];
    $billAmount = $post('ind_bill_amount');
    $usageUnits = $post('ind_usage_units');
    $roofType = $post('ind_roof_type');

    if ($contactName === '') $errors['contact_name'] = $msg['contactNameRequired'];
} else {
    // lead_type itself is already invalid — nothing more to validate against it.
    $firmName = $contactName = $city = $gstin = $message = null;
    $mobile = '';
    $products = [];
    $billAmount = $usageUnits = $roofType = null;
}

if ($mobile === '') {
    $errors['mobile'] = $msg['mobileRequired'];
} elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
    $errors['mobile'] = $msg['mobileInvalid'];
}

if ($post('consent') === '') {
    $errors['consent'] = $msg['consentRequired'];
}

if ($errors) {
    mnj_fail(422, $errors, $locale, $wantsJson);
}

$productsStr = $products ? implode(',', $products) : null;
$sourcePage = substr($post('source_page'), 0, 120) ?: null;
$ipHash = mnj_ip_hash();

try {
    $pdo = mnj_db();

    // Rate limit: 3 submissions per IP hash per hour. A friendly message, not
    // a hard error — this is meant to slow down abuse, not lock out a genuine
    // household with one shared connection.
    $rate = $pdo->prepare(
        'SELECT COUNT(*) FROM leads WHERE ip_hash = :ip AND created_at > (NOW() - INTERVAL 1 HOUR)'
    );
    $rate->execute(['ip' => $ipHash]);
    if ((int) $rate->fetchColumn() >= 3) {
        mnj_fail(429, ['_' => $msg['rateLimited']], $locale, $wantsJson);
    }

    $insert = $pdo->prepare(
        'INSERT INTO leads
            (lead_type, firm_name, contact_name, mobile, city, gstin, products,
             bill_amount, usage_units, roof_type, message, locale, consent_at,
             source_page, ip_hash)
         VALUES
            (:lead_type, :firm_name, :contact_name, :mobile, :city, :gstin, :products,
             :bill_amount, :usage_units, :roof_type, :message, :locale, NOW(),
             :source_page, :ip_hash)'
    );
    $insert->execute([
        'lead_type' => $leadType,
        'firm_name' => $firmName !== '' ? $firmName : null,
        'contact_name' => $contactName,
        'mobile' => $mobile,
        'city' => $city !== '' ? $city : null,
        'gstin' => $gstin !== '' ? $gstin : null,
        'products' => $productsStr,
        'bill_amount' => $billAmount !== null && $billAmount !== '' ? (int) $billAmount : null,
        'usage_units' => $usageUnits !== null && $usageUnits !== '' ? (int) $usageUnits : null,
        'roof_type' => $roofType !== '' ? $roofType : null,
        'message' => $message !== '' ? $message : null,
        'locale' => $locale,
        'source_page' => $sourcePage,
        'ip_hash' => $ipHash,
    ]);

    // No per-lead email here on purpose — server/cron/digest.php sends one
    // digest a day so the owner's inbox does not get a message per enquiry.

    mnj_ok($thanksPath, $wantsJson);
} catch (Throwable $e) {
    error_log('lead.php: ' . $e->getMessage());
    mnj_fail(500, ['_' => $msg['server']], $locale, $wantsJson);
}
