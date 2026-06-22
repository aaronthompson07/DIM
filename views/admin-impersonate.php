<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Admin') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light admin-body has-site-footer">
<header class="topbar">
    <div class="topbar-brand">
        <a href="/dashboard" class="product-logo-link" aria-label="DoItMeow">
            <img class="product-logo" src="/resources/images/doitmeow.png" alt="">
        </a>
        <div class="brand">View as tenant</div>
    </div>
    <div class="actions topbar-actions">
        <a href="/admin"><button type="button" class="ghost">← Admin home</button></a>
        <button type="button" id="admin-platform-theme-toggle" class="ghost">Toggle theme</button>
        <a href="/dashboard"><button type="button">Back to Dashboard</button></a>
    </div>
</header>
<main class="admin-main">
    <?php if ($limitedMode): ?>
        <div class="banner warning">Maintenance mode is active. Write actions are disabled.</div>
    <?php endif; ?>

    <section class="card">
        <h2>View another tenant</h2>
        <p class="muted">Your login session stays on your home tenant. Task and admin data switch to the tenant you select until you stop viewing.</p>
        <div class="stack">
            <label for="impersonate-tenant-select">Tenant</label>
            <select id="impersonate-tenant-select"></select>
            <div class="row-actions row-actions-spaced">
                <button type="button" id="impersonate-go">View this tenant</button>
                <button type="button" id="impersonate-clear" class="ghost">Stop viewing</button>
            </div>
            <p id="impersonation-status" class="muted"></p>
        </div>
    </section>

    <p id="admin-platform-message" class="admin-footer-msg user-feedback" role="status" aria-live="polite" aria-atomic="true"></p>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
