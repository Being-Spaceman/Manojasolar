<?php
/**
 * Shared chrome for /leads and /admin: one stylesheet, one login screen, one
 * nav bar. Kept framework-free like everything else in server/ — this is
 * plain PHP producing plain HTML, not a template engine.
 */

declare(strict_types=1);

function mnj_panel_css(): string
{
    return <<<CSS
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
      margin: 0;
      background: #f7f6f2;
      color: #14171a;
    }
    a { color: #007b3c; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 16px; }
    .nav {
      background: #04492a;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .nav a { color: #fff; text-decoration: none; font-weight: 600; margin-right: 16px; }
    .nav .role { opacity: .75; font-size: 13px; }
    .head-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin: 18px 0; }
    h1 { font-size: 22px; margin: 0; color: #04492a; }
    .btn {
      display: inline-block;
      background: #e1261c;
      color: #fff;
      text-decoration: none;
      font-weight: 700;
      padding: 10px 16px;
      border: 0;
      cursor: pointer;
      font-size: 14px;
    }
    .btn.secondary { background: #fff; color: #04492a; border: 1.5px solid #d8d5cc; }
    .btn.danger { background: #e1261c; }
    .filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; background: #fff; border: 1px solid #e2e0d9; padding: 12px; margin-bottom: 12px; }
    .filters label { display: flex; flex-direction: column; font-size: 12px; font-weight: 600; color: #55595d; gap: 4px; }
    .filters input, .filters select { padding: 8px; border: 1.5px solid #d8d5cc; font-size: 14px; min-height: 38px; }
    .muted { color: #55595d; font-size: 13px; }
    .table-scroll { overflow-x: auto; background: #fff; border: 1px solid #e2e0d9; }
    table.leads { border-collapse: collapse; width: 100%; min-width: 720px; font-size: 14px; }
    table.leads th, table.leads td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #edebe4; white-space: nowrap; }
    table.leads th { background: #eaf3ed; color: #04492a; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
    tr.st-new { background: #fdecea; }
    tr.st-exported { background: #fff6e0; }
    tr.st-contacted { background: #eaf7ee; }
    .pill { padding: 3px 9px; font-size: 12px; font-weight: 700; border-radius: 0; display: inline-block; }
    .pill.st-new { background: #e1261c; color: #fff; }
    .pill.st-exported { background: #b8860b; color: #fff; }
    .pill.st-contacted { background: #007b3c; color: #fff; }
    .act a { text-decoration: none; font-size: 18px; margin-right: 8px; }
    .pager { display: flex; gap: 6px; margin: 16px 0; flex-wrap: wrap; }
    .pager a { padding: 6px 11px; border: 1px solid #d8d5cc; text-decoration: none; color: #14171a; }
    .pager a.active { background: #04492a; color: #fff; border-color: #04492a; }
    .login-wrap { max-width: 360px; margin: 15vh auto; padding: 24px; background: #fff; border: 1px solid #e2e0d9; }
    .login-wrap h1 { font-size: 18px; }
    .login-wrap input[type=password] { width: 100%; padding: 12px; font-size: 16px; border: 1.5px solid #d8d5cc; margin: 10px 0; }
    .login-wrap .btn { width: 100%; padding: 12px; font-size: 15px; }
    .error { color: #e1261c; font-size: 13px; font-weight: 600; }
    form.inline { display: inline; }
    .counts { display: flex; gap: 14px; flex-wrap: wrap; margin: 16px 0; }
    .count-tile { background: #fff; border: 1px solid #e2e0d9; padding: 14px 18px; min-width: 140px; }
    .count-tile .n { font-size: 26px; font-weight: 800; color: #04492a; }
    .count-tile .l { font-size: 12px; color: #55595d; text-transform: uppercase; letter-spacing: .04em; }
    @media print {
      .nav, .filters, .pager, .no-print { display: none !important; }
    }
    CSS;
}

function mnj_panel_nav(string $role, string $active): void
{
    ?>
    <nav class="nav">
      <div>
        <a href="/leads/">Leads</a>
        <a href="/leads/downloads.php">Downloads</a>
        <?php if ($role === 'admin'): ?><a href="/admin/">Admin</a><?php endif; ?>
      </div>
      <div>
        <span class="role">Signed in as <?= htmlspecialchars($role) ?></span>
        <a href="/leads/logout.php">Log out</a>
      </div>
    </nav>
    <?php
}

function mnj_render_login(string $title, ?string $error): void
{
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= htmlspecialchars($title) ?> — Manoja Agencies</title>
      <style><?= mnj_panel_css() ?></style>
    </head>
    <body>
      <div class="login-wrap">
        <h1><?= htmlspecialchars($title) ?> — sign in</h1>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
          <?= mnj_csrf_field() ?>
          <input type="password" name="password" placeholder="Password" required autofocus>
          <button class="btn" type="submit">Sign in</button>
        </form>
      </div>
    </body>
    </html>
    <?php
}
