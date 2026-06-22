<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'All tickets') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer">
<header class="topbar">
    <div class="brand">All support tickets</div>
    <div class="actions">
        <a href="/admin"><button type="button" class="ghost">Admin home</button></a>
        <a href="/support"><button type="button" class="ghost">Company support</button></a>
        <a href="/dashboard"><button type="button">Dashboard</button></a>
        <button id="theme-toggle-support-all" type="button" class="ghost">Toggle theme</button>
        <button id="logout-all-support-all" type="button">Logout</button>
    </div>
</header>
<main class="dashboard support-page">
    <?php if ($limitedMode): ?>
        <div class="banner warning">Maintenance mode is active.</div>
    <?php endif; ?>
    <section class="card">
        <p class="muted">All companies (owner / super admin). Open a ticket to reply or close.</p>
        <ul id="support-all-list" class="support-ticket-list"></ul>
        <p id="support-all-empty" class="muted hidden">No tickets.</p>
    </section>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
