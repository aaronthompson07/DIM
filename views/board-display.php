<?php
declare(strict_types=1);

$companyKey = isset($_GET['company']) ? trim((string) $_GET['company']) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment board</title>
    <link rel="stylesheet" href="/resources/css/app.css">
</head>
<body class="theme-light board-display-page">
<main class="board-display-shell">
    <header class="board-display-header">
        <div>
            <h1 id="board-display-title">Appointment board</h1>
            <p id="board-display-subtitle" class="muted">Loading schedule…</p>
        </div>
        <p id="board-display-clock" class="board-display-clock muted" aria-live="polite"></p>
    </header>

    <div id="board-display-error" class="board-display-error hidden" role="alert"></div>

    <div class="board-display-table-wrap card">
        <table class="board-display-table" aria-label="Appointments">
            <thead>
                <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Type</th>
                    <th scope="col">Assigned to</th>
                    <th scope="col">Time</th>
                    <th scope="col">Done</th>
                </tr>
            </thead>
            <tbody id="board-display-body">
                <tr><td colspan="5" class="muted board-display-empty">Waiting for data…</td></tr>
            </tbody>
        </table>
    </div>

    <p class="board-display-legend muted">
        <span class="board-legend-item"><span class="board-swatch board-swatch-overdue"></span> Past due</span>
        <span class="board-legend-item"><span class="board-swatch board-swatch-soon"></span> Within 2 hours</span>
        <span class="board-legend-item"><span class="board-swatch board-swatch-upcoming"></span> Later today &amp; beyond</span>
        <span class="board-display-refresh-note">Auto-refreshes every 30 seconds</span>
    </p>
</main>
<script>
window.__BOARD_COMPANY__ = <?= json_encode($companyKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/resources/js/board-display.js"></script>
</body>
</html>
