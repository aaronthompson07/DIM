<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to DoItMeow with your company username and password, then pick up with your personal PIN on the dashboard.">
    <title><?= htmlspecialchars($title ?? 'DoItMeow') ?> · Company sign-in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer auth-page">
<main class="auth-page-main">
    <div class="auth-page-hero">
        <div class="auth-page-hero-bg" aria-hidden="true"></div>
        <div class="auth-page-hero-inner">
            <a class="auth-page-back" href="/">← Back to home</a>
            <p class="auth-page-eyebrow">Company sign-in</p>
            <h1 class="auth-page-title">Welcome back</h1>
            <p class="auth-page-lead">Use your organization’s login. You’ll enter your 6-digit PIN on the next step.</p>
        </div>
    </div>

    <div class="auth-page-body">
        <section class="card auth-page-card">
            <?php if ($limitedMode): ?>
                <div class="banner warning">Maintenance mode is active. Some actions are temporarily disabled.</div>
            <?php endif; ?>

            <form id="company-form" class="stack auth-form">
                <label for="login-company-username">Company username</label>
                <input id="login-company-username" type="text" name="company_username" required autocomplete="username">

                <label for="login-company-password">Password</label>
                <input id="login-company-password" type="password" name="password" required autocomplete="current-password">

                <button type="submit" class="auth-submit">Continue to dashboard</button>
            </form>

            <div class="auth-alt-actions">
                <button id="demo-btn" class="ghost auth-demo" type="button">Try demo</button>
            </div>
            <p id="login-message" class="auth-message" role="alert" aria-live="assertive" aria-atomic="true"></p>

            <?php if (filter_var((string) env('ALLOW_PUBLIC_SIGNUP', 'true'), FILTER_VALIDATE_BOOL)): ?>
                <p class="auth-switch muted">New here? <a href="/register">Create a company account</a></p>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
