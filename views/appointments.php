<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Appointments', ENT_QUOTES, 'UTF-8') ?></title>
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
        <a href="/dashboard"><button type="button">Dashboard</button></a>
        <button id="theme-toggle" type="button">Toggle Theme</button>
        <button id="logout-all" type="button">Logout</button>
    </div>
</header>
<main class="dashboard appointments-page">
    <?php require base_path('views/partials/migration-limited-banner.php'); ?>

    <section class="card appointments-intro">
        <h1 class="today-title">Appointment board</h1>
        <p class="muted">Add patients to the live display board. Enter a patient name (or leave blank for a random privacy label), choose Room, Treatment, or Anesthesia, assign staff, and set the appointment time.</p>
        <p class="muted appointments-public-link-wrap hidden" id="appointments-public-link-wrap">
            Public display URL:
            <a href="#" id="appointments-public-link" target="_blank" rel="noopener noreferrer"></a>
        </p>
    </section>

    <section class="card">
        <h2>New appointment</h2>
        <form id="appointment-form" class="stack appointments-form">
            <div>
                <label for="appt-patient">Patient name</label>
                <input type="text" id="appt-patient" maxlength="120" placeholder="e.g. Bailey (leave blank for random label)">
            </div>
            <div>
                <span class="legend-label">Type</span>
                <div class="appointments-type-row">
                    <label class="inline-check"><input type="radio" name="appt-type" value="room" checked> Room</label>
                    <label class="inline-check"><input type="radio" name="appt-type" value="treatment"> Treatment</label>
                    <label class="inline-check"><input type="radio" name="appt-type" value="anesthesia"> Anesthesia</label>
                </div>
            </div>
            <div>
                <label for="appt-assigned">Assigned team member</label>
                <select id="appt-assigned" required></select>
            </div>
            <div>
                <label for="appt-time">Appointment date &amp; time</label>
                <input type="datetime-local" id="appt-time" required>
                <p class="muted form-hint">Times use your company time zone. The public board shows the time only.</p>
            </div>
            <button type="submit" id="appt-submit">Add to board</button>
        </form>
        <p id="appointment-form-message" class="muted" role="status"></p>
    </section>

    <section class="card hidden" id="appt-edit-card">
        <h2>Edit appointment</h2>
        <form id="appointment-edit-form" class="stack appointments-form">
            <input type="hidden" id="appt-edit-id" value="">
            <div>
                <label for="appt-edit-patient">Patient name</label>
                <input type="text" id="appt-edit-patient" maxlength="120" required>
            </div>
            <div>
                <span class="legend-label">Type</span>
                <div class="appointments-type-row">
                    <label class="inline-check"><input type="radio" name="appt-edit-type" value="room"> Room</label>
                    <label class="inline-check"><input type="radio" name="appt-edit-type" value="treatment"> Treatment</label>
                    <label class="inline-check"><input type="radio" name="appt-edit-type" value="anesthesia"> Anesthesia</label>
                </div>
            </div>
            <div>
                <label for="appt-edit-assigned">Assigned team member</label>
                <select id="appt-edit-assigned" required></select>
            </div>
            <div>
                <label for="appt-edit-time">Appointment date &amp; time</label>
                <input type="datetime-local" id="appt-edit-time" required>
            </div>
            <div class="row-actions row-actions-spaced">
                <button type="submit" id="appt-edit-save">Save changes</button>
                <button type="button" id="appt-edit-cancel" class="ghost">Cancel</button>
            </div>
        </form>
        <p id="appointment-edit-message" class="muted" role="status"></p>
    </section>

    <section class="card">
        <h2>On the board now</h2>
        <p class="muted">Rows stay on the board until you mark them done (here or on the public display).</p>
        <div id="appointments-active-list" class="appointments-active-list muted">Loading…</div>
    </section>
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
