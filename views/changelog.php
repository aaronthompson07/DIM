<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Changelog') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer">
<header class="topbar">
    <div class="brand">Changelog</div>
    <div class="actions">
        <a href="/dashboard"><button type="button">Dashboard</button></a>
        <a href="/login"><button type="button" class="ghost">Login</button></a>
        <button id="theme-toggle-changelog" type="button" class="ghost">Toggle theme</button>
    </div>
</header>
<main class="dashboard help-page">
    <section class="card help-intro">
        <h1>Release notes</h1>
        <p class="muted">
            Deployed version: <strong>v<?= htmlspecialchars((string) ($appVersion ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            (from the <code>VERSION</code> file in the project root; optional <code>APP_VERSION</code> in <code>.env</code> overrides it).
        </p>
    </section>

    <article class="changelog">
        <?php if (empty($changelogGroups)) { ?>
            <section class="card help-section">
                <p class="muted">No release notes are available yet.</p>
            </section>
        <?php } else { ?>
            <?php foreach ($changelogGroups as $group) { ?>
                <section class="card help-section changelog-version">
                    <h2>v<?= htmlspecialchars((string) $group['version'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="muted changelog-date">
                        <?php
                        $rd = $group['released_at'] ?? '';
                        echo $rd !== '' ? htmlspecialchars((string) $rd, ENT_QUOTES, 'UTF-8') : '';
                        ?>
                    </p>
                    <ul class="changelog-list">
                        <?php foreach ($group['items'] as $item) { ?>
                            <li><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>
        <?php } ?>
    </article>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
