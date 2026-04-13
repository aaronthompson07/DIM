<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Reports') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer">
<header class="topbar">
    <div class="brand">Reports</div>
    <div class="actions">
        <a href="/dashboard"><button type="button">Dashboard</button></a>
        <button id="theme-toggle-reports" type="button" class="ghost">Toggle theme</button>
        <button id="logout-all-reports" type="button">Logout</button>
    </div>
</header>
<main class="dashboard">
    <?php if ($limitedMode): ?>
        <div class="banner warning">Maintenance mode is active.</div>
    <?php endif; ?>

    <section class="card">
        <h2>Task activity</h2>
        <p class="muted">Pick a date range (up to 366 days). Completed counts use the completion date.</p>
        <form id="reports-range-form" class="reports-range-form row-actions">
            <label class="inline-check reports-date-label">From
                <input type="date" id="reports-start" required>
            </label>
            <label class="inline-check reports-date-label">To
                <input type="date" id="reports-end" required>
            </label>
            <button type="submit">Apply</button>
        </form>
        <p class="muted" id="reports-period"></p>
        <p id="reports-error" class="user-feedback user-feedback--error hidden" role="alert" aria-live="assertive"></p>
        <div id="reports-summary" class="stats stats-today"></div>
    </section>

    <section class="card">
        <h3>Completed by day</h3>
        <div class="reports-table-wrap">
            <table class="matrix-table" id="reports-by-day">
                <thead><tr><th>Date</th><th>Completed</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3>Completed by person</h3>
        <div class="reports-table-wrap">
            <table class="matrix-table" id="reports-by-user">
                <thead><tr><th>Person</th><th>Completed</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3>Completed tasks (detail)</h3>
        <p class="muted">Up to 250 completions in this date range, newest first.</p>
        <div class="reports-table-wrap reports-detail-wrap">
            <table class="matrix-table" id="reports-detail">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Task</th>
                        <th>By</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
