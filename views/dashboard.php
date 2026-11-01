<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'DoItMeow') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer">
<header class="topbar">
    <div class="topbar-brand">
        <a href="/dashboard" class="product-logo-link" aria-label="DoItMeow">
            <img class="product-logo" src="/resources/images/doitmeow.png" alt="">
        </a>
        <div class="topbar-brand-tenant">
            <img id="tenant-logo" src="" alt="" class="tenant-logo hidden">
            <div class="brand" id="tenant-brand-text">DoItMeow</div>
        </div>
    </div>
    <div class="actions">
        <div id="topbar-user-row" class="topbar-user-row">
            <span id="user-display-name" class="topbar-user-name muted">Not signed in</span>
            <button type="button" id="switch-user-btn" class="ghost">Switch user</button>
        </div>
        <a href="/training" id="training-link-wrap" class="hidden"><button type="button">Training</button></a>
        <a href="/appointments" id="appointments-link-wrap" class="hidden"><button type="button">Appointments</button></a>
        <a href="/admin" id="admin-link-wrap" class="hidden"><button id="admin-link" type="button">Admin</button></a>
        <button id="theme-toggle" type="button">Toggle Theme</button>
        <button id="logout-all" type="button">Logout</button>
    </div>
</header>
<main class="dashboard">
    <?php require base_path('views/partials/migration-limited-banner.php'); ?>

    <section id="system-messages-section" class="card system-messages-card hidden" aria-label="System messages">
        <ul id="system-messages-list" class="system-messages-list"></ul>
    </section>

    <section id="weather-alerts-section" class="card weather-alerts-card hidden" aria-labelledby="weather-alerts-title">
        <h2 id="weather-alerts-title" class="weather-alerts-title">Weather alerts</h2>
        <p id="weather-alerts-meta" class="muted weather-alerts-meta"></p>
        <ul id="weather-alerts-list" class="weather-alerts-list"></ul>
    </section>

    <section class="card board-card">
        <div class="today-header board-card-header">
            <h2 class="today-title">Open tasks</h2>
            <div class="today-header-actions">
                <button id="add-task-btn" type="button" class="ghost btn-add-task hidden">Add task</button>
                <button id="show-assigned-to-me" type="button" class="ghost btn-assigned">Assigned to me (0)</button>
                <button id="show-completed" type="button" class="ghost btn-completed">Completed today (0)</button>
            </div>
        </div>
        <div id="task-board" class="task-board task-board-kanban" aria-label="Task columns by time of day"></div>
    </section>

    <aside id="dashboard-ad-slot" class="card ad-slot hidden" aria-label="Advertisement">
        <p class="muted ad-slot-label">Advertisement</p>
        <div id="dashboard-ad-ins"></div>
    </aside>

    <div id="completed-modal" class="modal hidden">
        <div class="modal-inner">
            <h3>Completed today</h3>
            <ul id="completed-tasks"></ul>
            <button id="close-completed" type="button">Close</button>
        </div>
    </div>

    <div id="assigned-modal" class="modal hidden">
        <div class="modal-inner">
            <h3>Assigned to me</h3>
            <p class="muted">Open tasks on today&apos;s board that are assigned to you.</p>
            <ul id="assigned-tasks-list" class="assigned-tasks-modal-list"></ul>
            <button id="close-assigned" type="button">Close</button>
        </div>
    </div>

    <div id="dash-task-modal" class="modal hidden">
        <div class="modal-inner modal-inner-wide dash-task-modal-inner">
            <h3 id="dash-task-modal-title">Add task</h3>
            <form id="dash-task-form" class="stack">
                <input type="hidden" id="dash-task-edit-id" value="">
                <label for="dash-task-title">Title</label>
                <input type="text" id="dash-task-title" required>
                <label for="dash-task-details">Details</label>
                <textarea id="dash-task-details" rows="3"></textarea>
                <label for="dash-task-priority">Priority</label>
                <select id="dash-task-priority" required></select>
                <label for="dash-task-slot">Time of day</label>
                <select id="dash-task-slot" required></select>
                <label for="dash-task-recurrence">Recurrence</label>
                <select id="dash-task-recurrence">
                    <option value="none">One-off</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="biweekly">Bi-weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                </select>
                <div id="dash-task-weekdays-block" class="hidden stack">
                    <span class="muted">Days of week (select any combination)</span>
                    <div class="task-weekdays-grid" role="group" aria-label="Weekdays">
                        <label class="inline-check task-wd-label"><input type="checkbox" class="dash-task-wd" data-day="0"> Sun</label>
                        <label class="inline-check task-wd-label"><input type="checkbox" class="dash-task-wd" data-day="1"> Mon</label>
                        <label class="inline-check task-wd-label"><input type="checkbox" class="dash-task-wd" data-day="2"> Tue</label>
                        <label class="inline-check task-wd-label"><input type="checkbox" class="dash-task-wd" data-day="3"> Wed</label>
                        <label class="inline-check task-wd-label"><input type="checkbox" class="dash-task-wd" data-day="4"> Thu</label>
                        <label class="inline-check task-wd-label"><input type="checkbox" class="dash-task-wd" data-day="5"> Fri</label>
                        <label class="inline-check task-wd-label"><input type="checkbox" class="dash-task-wd" data-day="6"> Sat</label>
                    </div>
                </div>
                <div id="dash-task-anchor-row" class="hidden stack">
                    <label for="dash-task-anchor-date">Anchor date</label>
                    <input type="date" id="dash-task-anchor-date">
                    <p class="muted form-hint">Anchor date marks when the cycle starts. Bi-weekly repeats every other week from this date; quarterly repeats every 3 months from this date.</p>
                </div>
                <div id="dash-task-monthly-weekday-row" class="hidden stack">
                    <label for="dash-task-monthly-weekday">Monthly/Quarterly weekday (optional)</label>
                    <select id="dash-task-monthly-weekday">
                        <option value="">Use day of month</option>
                        <option value="0">Sunday (first in month)</option>
                        <option value="1">Monday (first in month)</option>
                        <option value="2">Tuesday (first in month)</option>
                        <option value="3">Wednesday (first in month)</option>
                        <option value="4">Thursday (first in month)</option>
                        <option value="5">Friday (first in month)</option>
                        <option value="6">Saturday (first in month)</option>
                    </select>
                </div>
                <label id="dash-task-dom-label" class="hidden">Day of month (1–31)</label>
                <input type="number" id="dash-task-dom" class="hidden" min="1" max="31" value="1">
                <label for="dash-task-assignee">Assign to (optional)</label>
                <select id="dash-task-assignee">
                    <option value="">— None —</option>
                </select>
                <label class="inline-check"><input type="checkbox" id="dash-task-infinity"> Infinity (stays until done)</label>
                <label class="inline-check"><input type="checkbox" id="dash-task-active" checked> Active</label>
                <div class="row-actions">
                    <button type="submit" id="dash-task-save">Save</button>
                    <button type="button" id="dash-task-cancel" class="ghost">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</main>
<div id="user-auth-modal" class="modal hidden">
    <div class="modal-inner">
        <h3>Enter your 6-digit PIN</h3>
        <p class="muted">Your PIN identifies you for this company. No need to pick your name.</p>
        <p id="dashboard-user-message" class="user-feedback" role="alert" aria-live="assertive" aria-atomic="true"></p>
        <form id="dashboard-user-form" class="stack">
            <label>PIN</label>
            <input id="dashboard-pin-input" type="password" maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" required>
            <div class="keypad" id="dashboard-keypad"></div>
            <button type="submit">Continue</button>
        </form>
        <button id="close-user-auth-modal" type="button">Close</button>
    </div>
</div>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
