<?php
/**
 * /admin — owner only. Everything /leads has, plus edit, delete, status
 * changes, the export log, simple counts, and a manual archive trigger.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/leads.php';
require __DIR__ . '/../lib/panel.php';

mnj_session_start();
$loginError = mnj_handle_login_post();
$role = mnj_current_role();

if ($role === null) {
    mnj_render_login('Admin', $loginError);
    exit;
}

if ($role !== 'admin') {
    http_response_code(403);
    ?>
    <!doctype html><html lang="en"><head><meta charset="utf-8"><title>Admin</title>
    <style><?= mnj_panel_css() ?></style></head><body>
    <?= mnj_panel_nav($role, 'admin') ?>
    <main class="wrap"><h1>Owner only</h1><p>This area needs the admin login. <a href="/leads/">Back to Leads →</a></p></main>
    </body></html>
    <?php
    exit;
}

$pdo = mnj_db();

// --- row actions: status change / delete ------------------------------------
$actionMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!mnj_csrf_verify($_POST['csrf'] ?? null)) {
        $actionMessage = 'Session expired — try again.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($_POST['action'] === 'set_status') {
            $status = $_POST['status'] ?? '';
            if (in_array($status, ['new', 'exported', 'contacted'], true) && $id > 0) {
                $pdo->prepare('UPDATE leads SET status = :s WHERE id = :id')
                    ->execute(['s' => $status, 'id' => $id]);
                $actionMessage = "Lead #{$id} marked {$status}.";
            }
        } elseif ($_POST['action'] === 'delete') {
            if ($id > 0) {
                $pdo->prepare('DELETE FROM leads WHERE id = :id')->execute(['id' => $id]);
                $actionMessage = "Lead #{$id} deleted.";
            }
        } elseif ($_POST['action'] === 'update') {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE leads SET firm_name = :firm, contact_name = :contact, mobile = :mobile,
                     city = :city, gstin = :gstin, message = :message WHERE id = :id'
                )->execute([
                    'firm' => $_POST['firm_name'] ?: null,
                    'contact' => $_POST['contact_name'] ?? '',
                    'mobile' => preg_replace('/\D/', '', (string) ($_POST['mobile'] ?? '')),
                    'city' => $_POST['city'] ?: null,
                    'gstin' => $_POST['gstin'] ? strtoupper((string) $_POST['gstin']) : null,
                    'message' => $_POST['message'] ?: null,
                    'id' => $id,
                ]);
                $actionMessage = "Lead #{$id} updated.";
            }
        }
        $redirectQuery = $_GET;
        $redirectQuery['msg'] = (string) $actionMessage;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($redirectQuery));
        exit;
    }
}
$actionMessage = $_GET['msg'] ?? $actionMessage;

// --- counts ------------------------------------------------------------------
$byDay = $pdo->query(
    "SELECT DATE(created_at) d, COUNT(*) c FROM leads
     WHERE created_at > (NOW() - INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d DESC"
)->fetchAll();
$split = $pdo->query("SELECT lead_type, COUNT(*) c FROM leads GROUP BY lead_type")->fetchAll();
$splitMap = ['business' => 0, 'individual' => 0];
foreach ($split as $row) {
    $splitMap[$row['lead_type']] = (int) $row['c'];
}
$totalLeads = $splitMap['business'] + $splitMap['individual'];

// --- filtered, editable table ------------------------------------------------
$filters = mnj_lead_filters($_GET);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads {$filters['sql']}");
$countStmt->execute($filters['params']);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$listStmt = $pdo->prepare(
    "SELECT * FROM leads {$filters['sql']} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
);
foreach ($filters['params'] as $k => $v) {
    $listStmt->bindValue($k, $v);
}
$listStmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue('offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$leads = $listStmt->fetchAll();

$qs = $_GET;
unset($qs['page'], $qs['msg']);
$pageLink = static fn(int $p) => '?' . http_build_query($qs + ['page' => $p]);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — Manoja Agencies</title>
<style>
<?= mnj_panel_css() ?>
.edit-row td { background: #fbfaf7; }
.row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.row-actions form { display: inline; }
.row-actions select { font-size: 12px; padding: 4px; }
.row-actions button { font-size: 12px; padding: 5px 8px; cursor: pointer; }
details.edit-details summary { cursor: pointer; color: #007b3c; font-weight: 600; font-size: 12px; }
.edit-form { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 8px; }
.edit-form input, .edit-form textarea { padding: 8px; border: 1.5px solid #d8d5cc; font-size: 13px; }
.edit-form textarea { grid-column: 1 / -1; }
</style>
</head>
<body>
<?= mnj_panel_nav($role, 'admin') ?>

<main class="wrap">
  <div class="head-row">
    <h1>Admin</h1>
    <div style="display:flex;gap:10px">
      <a class="btn secondary" href="export-log.php">Export log</a>
      <a class="btn secondary" href="archive.php">Monthly archive</a>
    </div>
  </div>

  <?php if ($actionMessage): ?><p class="muted" style="color:#007b3c;font-weight:600"><?= htmlspecialchars((string) $actionMessage) ?></p><?php endif; ?>

  <div class="counts">
    <div class="count-tile"><div class="n"><?= $totalLeads ?></div><div class="l">Total leads</div></div>
    <div class="count-tile"><div class="n"><?= $splitMap['business'] ?></div><div class="l">B2B</div></div>
    <div class="count-tile"><div class="n"><?= $splitMap['individual'] ?></div><div class="l">B2C</div></div>
  </div>

  <div class="table-scroll" style="margin-bottom:20px">
    <table class="leads">
      <thead><tr><th>Day</th><th>Leads</th></tr></thead>
      <tbody>
        <?php foreach ($byDay as $row): ?>
        <tr><td><?= htmlspecialchars($row['d']) ?></td><td><?= $row['c'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$byDay): ?><tr><td colspan="2" class="muted">No leads in the last 7 days.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <form class="filters" method="get">
    <label>From <input type="date" name="date_from" value="<?= htmlspecialchars((string) ($_GET['date_from'] ?? '')) ?>"></label>
    <label>To <input type="date" name="date_to" value="<?= htmlspecialchars((string) ($_GET['date_to'] ?? '')) ?>"></label>
    <label>Type
      <select name="lead_type">
        <option value="">All</option>
        <option value="business" <?= ($_GET['lead_type'] ?? '') === 'business' ? 'selected' : '' ?>>Business</option>
        <option value="individual" <?= ($_GET['lead_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Individual</option>
      </select>
    </label>
    <label>Status
      <select name="status">
        <option value="">All</option>
        <option value="new" <?= ($_GET['status'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
        <option value="exported" <?= ($_GET['status'] ?? '') === 'exported' ? 'selected' : '' ?>>Exported</option>
        <option value="contacted" <?= ($_GET['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contacted</option>
      </select>
    </label>
    <button class="btn" type="submit">Filter</button>
  </form>

  <p class="muted"><?= $total ?> lead<?= $total === 1 ? '' : 's' ?></p>

  <div class="table-scroll">
    <table class="leads">
      <thead>
        <tr><th>Date</th><th>Type</th><th>Firm / Name</th><th>Mobile</th><th>City</th><th>Status</th><th>Manage</th></tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
        <tr>
          <td><?= htmlspecialchars(date('d M, H:i', strtotime($lead['created_at']))) ?></td>
          <td><?= htmlspecialchars(mnj_lead_type_label($lead['lead_type'])) ?></td>
          <td><?= htmlspecialchars($lead['firm_name'] ?: $lead['contact_name']) ?></td>
          <td><?= htmlspecialchars($lead['mobile']) ?></td>
          <td><?= htmlspecialchars((string) $lead['city']) ?></td>
          <td><span class="pill st-<?= htmlspecialchars($lead['status']) ?>"><?= htmlspecialchars(mnj_status_label($lead['status'])) ?></span></td>
          <td>
            <div class="row-actions">
              <form method="post">
                <?= mnj_csrf_field() ?>
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                <select name="status" onchange="this.form.submit()">
                  <option value="new" <?= $lead['status'] === 'new' ? 'selected' : '' ?>>New</option>
                  <option value="exported" <?= $lead['status'] === 'exported' ? 'selected' : '' ?>>Exported</option>
                  <option value="contacted" <?= $lead['status'] === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                </select>
              </form>
              <form method="post" onsubmit="return confirm('Delete lead #<?= (int) $lead['id'] ?>? This cannot be undone.')">
                <?= mnj_csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                <button class="btn danger" type="submit">Delete</button>
              </form>
            </div>
            <details class="edit-details">
              <summary>Edit</summary>
              <form method="post" class="edit-form">
                <?= mnj_csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                <input name="firm_name" value="<?= htmlspecialchars((string) $lead['firm_name']) ?>" placeholder="Firm">
                <input name="contact_name" value="<?= htmlspecialchars($lead['contact_name']) ?>" placeholder="Contact name" required>
                <input name="mobile" value="<?= htmlspecialchars($lead['mobile']) ?>" placeholder="Mobile" required>
                <input name="city" value="<?= htmlspecialchars((string) $lead['city']) ?>" placeholder="City">
                <input name="gstin" value="<?= htmlspecialchars((string) $lead['gstin']) ?>" placeholder="GSTIN">
                <textarea name="message" placeholder="Message" rows="2"><?= htmlspecialchars((string) $lead['message']) ?></textarea>
                <button class="btn secondary" type="submit" style="grid-column:1/-1">Save changes</button>
              </form>
            </details>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$leads): ?>
        <tr><td colspan="7" class="muted" style="text-align:center;padding:24px">No leads match these filters.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <nav class="pager">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a class="<?= $p === $page ? 'active' : '' ?>" href="<?= htmlspecialchars($pageLink($p)) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </nav>
</main>
</body>
</html>
