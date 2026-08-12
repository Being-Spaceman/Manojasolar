<?php
/**
 * /leads/downloads — export screen. Same `desk`/`admin` login as /leads.
 * Every export writes a row to export_log (accountability for a shared
 * login: no per-user identity, but every pull is logged) and marks the
 * exported leads `exported`.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/leads.php';
require __DIR__ . '/../lib/export.php';
require __DIR__ . '/../lib/panel.php';

mnj_session_start();
$loginError = mnj_handle_login_post();
$role = mnj_current_role();
if ($role === null) {
    mnj_render_login('Downloads', $loginError);
    exit;
}

[$defaultFrom, $defaultTo] = mnj_current_month_range();

// --- handle an export submission --------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['format'])) {
    if (!mnj_csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Session expired — go back and try again.');
    }

    $format = $_POST['format'] === 'xlsx' ? 'xlsx' : 'csv';
    $input = [
        'date_from' => $_POST['date_from'] ?? $defaultFrom,
        'date_to' => $_POST['date_to'] ?? $defaultTo,
        'lead_type' => $_POST['lead_type'] ?? '',
        'status' => $_POST['status'] ?? '',
    ];
    $filters = mnj_lead_filters($input);

    $pdo = mnj_db();
    $stmt = $pdo->prepare(
        "SELECT id, lead_type, firm_name, contact_name, mobile, city, gstin, products,
                bill_amount, usage_units, roof_type, message, status, locale, created_at
         FROM leads {$filters['sql']} ORDER BY created_at ASC"
    );
    $stmt->execute($filters['params']);
    $leads = $stmt->fetchAll();

    $headers = ['Date', 'Type', 'Firm', 'Contact', 'Mobile', 'City', 'GSTIN', 'Products',
        'Bill (₹)', 'Usage (units)', 'Roof', 'Message', 'Status'];
    $rows = array_map(static fn($l) => [
        $l['created_at'], mnj_lead_type_label($l['lead_type']), (string) $l['firm_name'],
        $l['contact_name'], $l['mobile'], (string) $l['city'], (string) $l['gstin'],
        (string) $l['products'], (string) $l['bill_amount'], (string) $l['usage_units'],
        (string) $l['roof_type'], (string) $l['message'], mnj_status_label($l['status']),
    ], $leads);

    $stamp = date('Ymd-His');

    // Log the export and mark the rows exported before streaming the file —
    // if a browser aborts the download partway, the log/status still reflect
    // that the pull was made, which is the whole point of export_log.
    $pdo->prepare(
        'INSERT INTO export_log (format, row_count, range_from, range_to, ip_hash)
         VALUES (:format, :count, :from, :to, :ip)'
    )->execute([
        'format' => $format,
        'count' => count($leads),
        'from' => $input['date_from'],
        'to' => $input['date_to'],
        'ip' => mnj_ip_hash(),
    ]);

    if ($leads) {
        $ids = array_column($leads, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE leads SET status = 'exported' WHERE id IN ($placeholders) AND status = 'new'")
            ->execute($ids);
    }

    if ($format === 'xlsx') {
        mnj_send_xlsx($headers, $rows, "leads-{$stamp}.xlsx");
    }
    mnj_send_csv($headers, $rows, "leads-{$stamp}.csv");
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Downloads — Manoja Agencies</title>
<style><?= mnj_panel_css() ?></style>
</head>
<body>
<?= mnj_panel_nav($role, 'downloads') ?>

<main class="wrap">
  <div class="head-row">
    <h1>Downloads</h1>
    <a class="btn secondary" href="call-sheet.php">Print call sheet →</a>
  </div>

  <form class="filters" method="post" style="flex-direction:column;align-items:stretch;max-width:420px">
    <?= mnj_csrf_field() ?>
    <label>From <input type="date" name="date_from" value="<?= htmlspecialchars($defaultFrom) ?>"></label>
    <label>To <input type="date" name="date_to" value="<?= htmlspecialchars($defaultTo) ?>"></label>
    <label>Lead type
      <select name="lead_type">
        <option value="">All</option>
        <option value="business">Business</option>
        <option value="individual">Individual</option>
      </select>
    </label>
    <label>Status
      <select name="status">
        <option value="">All</option>
        <option value="new">New</option>
        <option value="exported">Exported</option>
        <option value="contacted">Contacted</option>
      </select>
    </label>
    <div style="display:flex;gap:10px;margin-top:6px">
      <button class="btn" type="submit" name="format" value="csv">Download CSV</button>
      <button class="btn secondary" type="submit" name="format" value="xlsx">Download XLSX</button>
    </div>
  </form>

  <p class="muted">Exporting marks matching <strong>new</strong> leads as <strong>exported</strong> and logs the pull (row count, range, format) to the export log — visible to the owner under /admin.</p>
</main>
</body>
</html>
