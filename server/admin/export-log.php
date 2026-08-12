<?php
/** /admin/export-log — owner only. Read-only view of every export pulled from /leads/downloads. */

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/panel.php';

mnj_session_start();
$loginError = mnj_handle_login_post();
$role = mnj_current_role();
if ($role === null) {
    mnj_render_login('Export log', $loginError);
    exit;
}
if ($role !== 'admin') {
    http_response_code(403);
    exit('Owner only. See /leads/ instead.');
}

$pdo = mnj_db();
$rows = $pdo->query('SELECT * FROM export_log ORDER BY exported_at DESC LIMIT 200')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Export log — Manoja Agencies</title>
<style><?= mnj_panel_css() ?></style>
</head>
<body>
<?= mnj_panel_nav($role, 'admin') ?>
<main class="wrap">
  <div class="head-row"><h1>Export log</h1><a class="btn secondary" href="/admin/">← Admin</a></div>
  <p class="muted">Every CSV/XLSX pull from /leads/downloads, most recent first. There is no per-user login on /leads, so this — plus the IP hash — is the accountability trail.</p>
  <div class="table-scroll">
    <table class="leads">
      <thead><tr><th>When</th><th>Format</th><th>Rows</th><th>Range</th><th>IP hash</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['exported_at']) ?></td>
          <td><?= htmlspecialchars(strtoupper($r['format'])) ?></td>
          <td><?= (int) $r['row_count'] ?></td>
          <td><?= htmlspecialchars(($r['range_from'] ?? '—') . ' → ' . ($r['range_to'] ?? '—')) ?></td>
          <td style="font-family:monospace;font-size:11px"><?= htmlspecialchars(substr((string) $r['ip_hash'], 0, 12)) ?>…</td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">No exports yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
