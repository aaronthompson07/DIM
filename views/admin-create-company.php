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
        <div class="brand">Create company</div>
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
        <h2>Create new company</h2>
        <p class="muted">Creates a tenant with default time slots and a tenant admin. They sign in with the company login + admin PIN.</p>
        <form id="create-tenant-form" class="stack form-grid-2">
            <div>
                <label>Company name</label>
                <input type="text" id="ct-name" required maxlength="120" autocomplete="organization">
            </div>
            <div>
                <label>Company login</label>
                <input type="text" id="ct-username" required maxlength="79" pattern="[a-zA-Z0-9][a-zA-Z0-9_-]*" autocomplete="off">
            </div>
            <div>
                <label>Company password</label>
                <input type="password" id="ct-password" required minlength="8" autocomplete="new-password">
            </div>
            <div>
                <label>Owner email</label>
                <input type="email" id="ct-email" required maxlength="190" autocomplete="email">
            </div>
            <div>
                <label>Admin display name</label>
                <input type="text" id="ct-admin-name" required maxlength="120" autocomplete="name">
            </div>
            <div>
                <label>Admin 6-digit PIN</label>
                <input type="password" id="ct-admin-pin" required maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code">
            </div>
            <div class="form-grid-full">
                <label>Timezone</label>
                <?php
                $timezoneSelectId = 'ct-tz';
                $timezoneSelectName = null;
                $timezoneSelected = 'UTC';
                require base_path('views/partials/timezone_select.php');
                ?>
            </div>
            <div class="form-grid-full row-actions row-actions-spaced">
                <button type="submit" id="ct-submit">Create company</button>
            </div>
        </form>
    </section>

    <p id="admin-platform-message" class="admin-footer-msg user-feedback" role="status" aria-live="polite" aria-atomic="true"></p>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
