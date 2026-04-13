<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Create a DoItMeow company—shared company login, time slots, and an admin PIN to manage your team’s tasks.">
    <title><?= htmlspecialchars($title ?? 'DoItMeow') ?> · Create your company</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer auth-page auth-page-register">
<main class="auth-page-main">
    <div class="auth-page-hero">
        <div class="auth-page-hero-bg" aria-hidden="true"></div>
        <div class="auth-page-hero-inner">
            <a class="auth-page-back" href="/">← Back to home</a>
            <p class="auth-page-eyebrow">New company</p>
            <h1 class="auth-page-title">Create your workspace</h1>
            <p class="auth-page-lead">One company login for everyone, Morning / Afternoon / Evening columns, and your first admin PIN—ready in minutes.</p>
        </div>
    </div>

    <div class="auth-page-body">
        <section class="card auth-page-card">
            <?php if ($limitedMode): ?>
                <div class="banner warning">Maintenance mode is active. Registration may be unavailable.</div>
            <?php endif; ?>

            <form id="register-form" class="stack auth-form">
                <div class="auth-form-block">
                    <h2 class="auth-form-heading">Company</h2>
                    <label for="reg-company-name">Company name</label>
                    <input id="reg-company-name" type="text" name="company_name" required maxlength="120" autocomplete="organization">

                    <label for="reg-company-username">Company login</label>
                    <input id="reg-company-username" type="text" name="company_username" required maxlength="79" pattern="[a-zA-Z0-9][a-zA-Z0-9_-]*" title="Letters, numbers, underscore or hyphen" autocomplete="username">
                    <p class="muted form-hint">Shown on the sign-in screen (stored lowercase). Example: <code>acme-corp</code></p>

                    <label for="reg-company-password">Company password</label>
                    <input id="reg-company-password" type="password" name="company_password" required minlength="8" autocomplete="new-password">
                </div>

                <div class="auth-form-block">
                    <h2 class="auth-form-heading">Owner &amp; first admin</h2>
                    <label for="reg-owner-email">Owner email</label>
                    <input id="reg-owner-email" type="email" name="owner_email" required maxlength="190" autocomplete="email">

                    <label for="reg-admin-name">Your name (first admin)</label>
                    <input id="reg-admin-name" type="text" name="admin_display_name" required maxlength="120" autocomplete="name">

                    <label for="reg-admin-pin">Your 6-digit PIN</label>
                    <input id="reg-admin-pin" type="password" name="admin_pin" required maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code">
                </div>

                <div class="auth-form-block">
                    <h2 class="auth-form-heading">Region</h2>
                    <label for="register-timezone">Timezone</label>
                    <?php
                    $timezoneSelectId = 'register-timezone';
                    $timezoneSelectName = 'timezone';
                    $timezoneSelected = 'UTC';
                    require base_path('views/partials/timezone_select.php');
                    ?>
                </div>

                <button type="submit" class="auth-submit">Create company</button>
            </form>

            <p id="register-message" class="auth-message" role="alert" aria-live="polite" aria-atomic="true"></p>
            <p class="auth-switch muted"><a href="/login">Already have a company? Sign in</a></p>
        </section>
    </div>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
