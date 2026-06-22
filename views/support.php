<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Support') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer">
<header class="topbar">
    <div class="brand">Support</div>
    <div class="actions">
        <a href="/dashboard"><button type="button">Dashboard</button></a>
        <button id="theme-toggle-support" type="button" class="ghost">Toggle theme</button>
        <button id="logout-all-support" type="button">Logout</button>
    </div>
</header>
<main class="dashboard support-page">
    <?php if ($limitedMode): ?>
        <div class="banner warning">Maintenance mode is active. Creating tickets and replies is disabled.</div>
    <?php endif; ?>

    <section class="card">
        <h2>New ticket</h2>
        <p class="muted">Everyone in your company can see tickets for your company. Describe what went wrong; you can attach one image.</p>
        <form id="support-new-form" class="stack support-form">
            <label for="support-title">Title</label>
            <input type="text" id="support-title" name="title" required maxlength="255" autocomplete="off">
            <label for="support-priority">Priority</label>
            <select id="support-priority" name="priority">
                <option value="low">Low</option>
                <option value="normal" selected>Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
            <label for="support-desc">Message</label>
            <textarea id="support-desc" name="description" rows="6" required></textarea>
            <label for="support-image">Attach image (optional)</label>
            <input type="file" id="support-image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            <p class="muted form-hint">JPEG, PNG, GIF, or WebP. Max 2 MB.</p>
            <button type="submit" id="support-submit">Submit ticket</button>
        </form>
        <p id="support-new-msg" class="user-feedback hidden" role="alert"></p>
    </section>

    <section class="card">
        <h2>Your company’s tickets</h2>
        <ul id="support-ticket-list" class="support-ticket-list"></ul>
        <p id="support-list-empty" class="muted hidden">No tickets yet.</p>
    </section>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
