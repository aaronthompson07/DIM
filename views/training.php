<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Training') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer">
<header class="topbar">
    <div class="brand">Training</div>
    <div class="actions">
        <a href="/dashboard"><button type="button">Dashboard</button></a>
        <a href="/admin" id="training-admin-link" class="hidden"><button type="button" class="ghost">Admin</button></a>
        <button id="theme-toggle-training" type="button" class="ghost">Toggle theme</button>
        <button id="logout-all-training" type="button">Logout</button>
    </div>
</header>
<main class="dashboard">
    <?php if ($limitedMode): ?>
        <div class="banner warning">Maintenance mode is active.</div>
    <?php endif; ?>

    <p id="training-error" class="user-feedback user-feedback--error hidden" role="alert" aria-live="assertive"></p>
    <p id="training-disabled" class="card muted hidden">Training is not enabled for your plan. Ask an owner to enable it on a paid tier in the feature matrix.</p>

    <section class="card hidden" id="training-enabled-wrap">
        <h2>My training</h2>
        <p class="muted">Managers sign off See 1, Do 1, and Teach 1. Open items appear first.</p>
        <ul id="training-my-list" class="training-list"></ul>
    </section>

    <section class="card hidden" id="training-team-wrap">
        <h2>Team training (managers)</h2>
        <p class="muted">Each step must be signed off by a manager or higher (See 1, Do 1, Teach 1). Expand a person to see their trainings.</p>
        <div id="training-team-accordion" class="training-team-accordion"></div>
    </section>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
