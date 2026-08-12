<?php
/** /admin/archive — owner only. Lists existing monthly archives and can trigger one manually. */

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/archive.php';
require __DIR__ . '/../lib/panel.php';

mnj_session_start();
$loginError = mnj_handle_login_post();
$role = mnj_current_role();
if ($role === null) {
    mnj_render_login('Archive', $loginError);
    exit;
}
if ($role !== 'admin') {
    http_response_code(403);
    exit('Owner only. See /leads/ instead.');
}

$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mnj_csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Session expired — try again.';
    } else {
        $ym = trim((string) ($_POST['year_month'] ?? ''));
        try {
            $result = mnj_run_monthly_archive($ym !== '' ? $ym : null);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$dir = __DIR__ . '/../archive';
$files = is_dir($dir)
    ? array_values(array_filter(scandir($dir), static fn($f) => str_ends_with($f, '.xlsx')))
    : [];
rsort($files);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Archive — Manoja Agencies</title>
<style><?= mnj_panel_css() ?></style>
</head>
<body>
<?= mnj_panel_nav($role, 'admin') ?>
<main class="wrap">
  <div class="head-row"><h1>Monthly archive</h1><a class="btn secondary" href="/admin/">← Admin</a></div>

  <p class="muted">
    Runs automatically on the 1st of each month via <code>cron/monthly-archive.php</code>
    (see server/README.md for the cron expression). This button re-runs it on demand —
    useful for backfilling a month or checking the output before trusting the cron.
    MySQL stays the permanent record either way; this is a convenience copy only,
    stored outside the web root's reach (server/archive/, .htaccess-denied).
  </p>

  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($result): ?>
    <p class="muted" style="color:#007b3c;font-weight:600">
      Wrote <?= htmlspecialchars($result['filename']) ?> — <?= $result['count'] ?> leads.
    </p>
  <?php endif; ?>

  <form method="post" style="display:flex;gap:10px;align-items:end;margin:16px 0">
    <?= mnj_csrf_field() ?>
    <label style="display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:4px">
      Month (blank = last month)
      <input type="month" name="year_month">
    </label>
    <button class="btn" type="submit">Run archive now</button>
  </form>

  <div class="table-scroll">
    <table class="leads">
      <thead><tr><th>File</th></tr></thead>
      <tbody>
        <?php foreach ($files as $f): ?>
          <tr><td><?= htmlspecialchars($f) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$files): ?><tr><td class="muted">No archives yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted">Files are not downloadable from here — they're outside the web root's reach by design. Pull them over (S)FTP from hPanel's File Manager if you need a copy.</p>
</main>
</body>
</html>
