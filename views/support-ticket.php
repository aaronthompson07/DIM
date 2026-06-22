<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Ticket') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer">
<header class="topbar">
    <div class="brand">Ticket #<?= (int) ($supportTicketId ?? 0) ?></div>
    <div class="actions">
        <a href="/support"><button type="button" class="ghost">Back to support</button></a>
        <a href="/dashboard"><button type="button">Dashboard</button></a>
        <button id="theme-toggle-support-ticket" type="button" class="ghost">Toggle theme</button>
        <button id="logout-all-support-ticket" type="button">Logout</button>
    </div>
</header>
<main class="dashboard support-page" id="support-ticket-main" data-ticket-id="<?= (int) ($supportTicketId ?? 0) ?>">
    <?php if ($limitedMode): ?>
        <div class="banner warning">Maintenance mode is active. Replies and closing are disabled.</div>
    <?php endif; ?>

    <p id="support-ticket-error" class="user-feedback user-feedback--error hidden" role="alert"></p>

    <section class="card" id="support-ticket-card">
        <div class="support-ticket-meta muted" id="support-ticket-meta"></div>
        <h2 id="support-ticket-title"></h2>
        <p id="support-ticket-priority" class="support-priority-badge"></p>
        <div id="support-ticket-desc" class="support-ticket-body"></div>
        <div id="support-ticket-initial-img" class="support-attachment-wrap hidden"></div>
        <div class="support-ticket-times muted" id="support-ticket-times"></div>
        <div id="support-ticket-close-wrap" class="support-actions hidden">
            <button type="button" id="support-ticket-close-btn" class="ghost">Close ticket</button>
        </div>
    </section>

    <section class="card">
        <h3>Conversation</h3>
        <ul id="support-messages-list" class="support-messages-list"></ul>
    </section>

    <section class="card hidden" id="support-reply-card">
        <h3>Reply</h3>
        <form id="support-reply-form" class="stack support-form">
            <label for="support-reply-body">Message</label>
            <textarea id="support-reply-body" rows="4" required></textarea>
            <label for="support-reply-image">Attach image (optional)</label>
            <input type="file" id="support-reply-image" accept="image/jpeg,image/png,image/gif,image/webp">
            <button type="submit" id="support-reply-submit">Send reply</button>
        </form>
        <p id="support-reply-msg" class="user-feedback hidden" role="alert"></p>
    </section>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
