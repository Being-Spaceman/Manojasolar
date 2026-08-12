<?php
/**
 * /leads/call-sheet — print-only A4 call sheet. Same login as /leads. Not
 * meant to be read on screen: it opens straight into the browser print
 * dialog's preview via @media print, roughly 12 leads per page, with a wide
 * ruled column for handwritten notes from the call.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/leads.php';
require __DIR__ . '/../lib/panel.php';

mnj_session_start();
$loginError = mnj_handle_login_post();
$role = mnj_current_role();
if ($role === null) {
    mnj_render_login('Call sheet', $loginError);
    exit;
}

[$defaultFrom, $defaultTo] = mnj_current_month_range();
$input = [
    'date_from' => $_GET['date_from'] ?? $defaultFrom,
    'date_to' => $_GET['date_to'] ?? $defaultTo,
    'lead_type' => $_GET['lead_type'] ?? '',
    'status' => $_GET['status'] ?? '',
];
$filters = mnj_lead_filters($input);

$pdo = mnj_db();
$stmt = $pdo->prepare(
    "SELECT firm_name, contact_name, mobile, city, products
     FROM leads {$filters['sql']} ORDER BY created_at ASC"
);
$stmt->execute($filters['params']);
$leads = $stmt->fetchAll();

$perPage = 12;
$pages = array_chunk($leads, $perPage);
if (!$pages) {
    $pages = [[]];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Call sheet — Manoja Agencies</title>
<style>
  @page { size: A4 portrait; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; color: #14171a; }
  .toolbar { padding: 12px 16px; background: #04492a; display: flex; gap: 12px; align-items: center; }
  .toolbar a, .toolbar button { color: #fff; background: transparent; border: 1px solid rgba(255,255,255,.5); padding: 8px 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
  .sheet-page { padding: 8mm 0; page-break-after: always; }
  .sheet-page:last-child { page-break-after: auto; }
  h1 { font-size: 16px; margin: 0 0 4px; }
  .meta { font-size: 11px; color: #55595d; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #999; padding: 6px 8px; font-size: 11.5px; text-align: left; vertical-align: top; }
  th { background: #eaf3ed; text-transform: uppercase; letter-spacing: .03em; font-size: 10px; }
  td.notes { width: 34%; }
  tr { height: 30px; }
  @media print {
    .toolbar { display: none; }
    .sheet-page { padding: 0; }
  }
</style>
</head>
<body>
  <div class="toolbar no-print">
    <a href="downloads.php">← Downloads</a>
    <button onclick="window.print()">Print</button>
    <span style="color:#fff;font-size:13px"><?= count($leads) ?> leads · <?= htmlspecialchars($input['date_from']) ?> to <?= htmlspecialchars($input['date_to']) ?></span>
  </div>

  <?php foreach ($pages as $pageLeads): ?>
  <section class="sheet-page">
    <h1>Manoja Agencies — call sheet</h1>
    <p class="meta"><?= htmlspecialchars($input['date_from']) ?> to <?= htmlspecialchars($input['date_to']) ?> · printed <?= date('d M Y, H:i') ?></p>
    <table>
      <thead>
        <tr>
          <th style="width:22%">Name / Firm</th>
          <th style="width:14%">Mobile</th>
          <th style="width:14%">City</th>
          <th style="width:16%">Product interest</th>
          <th class="notes">Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pageLeads as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['firm_name'] ?: $l['contact_name']) ?></td>
          <td><?= htmlspecialchars($l['mobile']) ?></td>
          <td><?= htmlspecialchars((string) $l['city']) ?></td>
          <td><?= htmlspecialchars((string) $l['products']) ?></td>
          <td class="notes"></td>
        </tr>
        <?php endforeach; ?>
        <?php for ($i = count($pageLeads); $i < $perPage; $i++): ?>
        <tr><td colspan="5">&nbsp;</td></tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </section>
  <?php endforeach; ?>
</body>
</html>
