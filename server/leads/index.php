<?php
/**
 * /leads — shared desk login. VIEW AND EXPORT ONLY: no editing, no status
 * changes, no deletion. Admins can also see this page (a superset role), but
 * nothing here writes to a lead beyond what downloads.php does when it marks
 * rows exported.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/leads.php';
require __DIR__ . '/../lib/panel.php';

mnj_session_start();
$loginError = mnj_handle_login_post();
$role = mnj_current_role();

if ($role === null) {
    mnj_render_login('Leads', $loginError);
    exit;
}

// --- authenticated: build the filtered, paginated table --------------------

$pdo = mnj_db();
$filters = mnj_lead_filters($_GET);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads {$filters['sql']}");
$countStmt->execute($filters['params']);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$listStmt = $pdo->prepare(
    "SELECT id, lead_type, firm_name, contact_name, mobile, city, products, status, created_at
     FROM leads {$filters['sql']}
     ORDER BY created_at DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($filters['params'] as $k => $v) {
    $listStmt->bindValue($k, $v);
}
$listStmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue('offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$leads = $listStmt->fetchAll();

$qs = $_GET;
unset($qs['page']);
$baseQuery = http_build_query($qs);
$pageLink = static fn(int $p) => '?' . http_build_query($qs + ['page' => $p]);

function mnj_status_class(string $status): string
{
    return match ($status) {
        'new' => 'st-new',
        'exported' => 'st-exported',
        'contacted' => 'st-contacted',
        default => '',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leads — Manoja Agencies</title>
<style><?= mnj_panel_css() ?></style>
</head>
<body>
<?= mnj_panel_nav($role, 'leads') ?>

<main class="wrap">
  <div class="head-row">
    <h1>Leads</h1>
    <a class="btn" href="downloads.php">Downloads →</a>
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
        <tr>
          <th>Date</th><th>Type</th><th>Firm / Name</th><th>Mobile</th>
          <th>City</th><th>Products</th><th>Status</th><th>Act</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
        <tr class="<?= mnj_status_class($lead['status']) ?>">
          <td data-label="Date"><?= htmlspecialchars(date('d M, H:i', strtotime($lead['created_at']))) ?></td>
          <td data-label="Type"><?= htmlspecialchars(mnj_lead_type_label($lead['lead_type'])) ?></td>
          <td data-label="Firm / Name"><?= htmlspecialchars($lead['firm_name'] ?: $lead['contact_name']) ?></td>
          <td data-label="Mobile"><?= htmlspecialchars($lead['mobile']) ?></td>
          <td data-label="City"><?= htmlspecialchars((string) $lead['city']) ?></td>
          <td data-label="Products"><?= htmlspecialchars((string) $lead['products']) ?></td>
          <td data-label="Status"><span class="pill <?= mnj_status_class($lead['status']) ?>"><?= htmlspecialchars(mnj_status_label($lead['status'])) ?></span></td>
          <td data-label="Act" class="act">
            <a href="https://wa.me/91<?= htmlspecialchars($lead['mobile']) ?>" target="_blank" rel="noopener" title="WhatsApp">💬</a>
            <a href="tel:+91<?= htmlspecialchars($lead['mobile']) ?>" title="Call">📞</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$leads): ?>
        <tr><td colspan="8" class="muted" style="text-align:center;padding:24px">No leads match these filters.</td></tr>
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
