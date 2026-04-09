<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="DoItMeow — team tasks, recurring work, and live dashboards for every company. Company login plus fast PIN sign-in. Built for real operations.">
    <title><?= htmlspecialchars($title ?? 'DoItMeow') ?> · Team tasks that actually ship</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer home-landing">
<main class="landing-main">
    <?php if (!empty($limitedMode)): ?>
        <div class="landing-banner banner warning">Maintenance mode is on — sign-in works; some write actions may be limited.</div>
    <?php endif; ?>

    <header class="landing-hero" id="top">
        <div class="landing-hero-bg" aria-hidden="true"></div>
        <div class="landing-hero-inner">
            <p class="landing-eyebrow">Team operations · One place</p>
            <h1 class="landing-title">The calm way to run <span class="landing-title-accent">today’s work</span></h1>
            <p class="landing-lead">DoItMeow gives every company its own space—tasks, recurrences, roles, and live updates—without drowning in spreadsheets or group chats.</p>
            <div class="landing-cta-row">
                <a class="landing-btn landing-btn-primary" href="/login">Sign in to your company</a>
                <?php if (filter_var((string) env('ALLOW_PUBLIC_SIGNUP', 'true'), FILTER_VALIDATE_BOOL)): ?>
                    <a class="landing-btn landing-btn-secondary" href="/register">Create a company account</a>
                <?php endif; ?>
            </div>
            <p class="landing-micro">Free to explore · Company login, then a 6-digit PIN per person—no username pickers.</p>
        </div>
    </header>

    <section class="landing-strip" aria-label="Highlights">
        <div class="landing-strip-inner">
            <span class="landing-pill">Live dashboard</span>
            <span class="landing-pill">Recurring &amp; one-off tasks</span>
            <span class="landing-pill">Tenant-safe by design</span>
            <span class="landing-pill">Roles &amp; audits</span>
        </div>
    </section>

    <section class="landing-section" id="features">
        <div class="landing-section-head">
            <h2 class="landing-h2">Everything your floor needs—nothing extra</h2>
            <p class="landing-sub">One product for managers, admins, and frontline teams. Pick what your plan enables; grow when you’re ready.</p>
        </div>
        <ul class="landing-grid">
            <li class="landing-feature">
                <span class="landing-feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                </span>
                <h3 class="landing-feature-title">Today-first tasks</h3>
                <p>See what’s due now, what’s recurring, and what slipped—without rebuilding a report every morning.</p>
            </li>
            <li class="landing-feature">
                <span class="landing-feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </span>
                <h3 class="landing-feature-title">Built-in tenant isolation</h3>
                <p>Each company is its own universe—data stays scoped, admins stay in control.</p>
            </li>
            <li class="landing-feature">
                <span class="landing-feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                </span>
                <h3 class="landing-feature-title">PIN sign-in, fast</h3>
                <p>Company login once, then a keypad PIN—perfect for shared devices and busy shifts.</p>
            </li>
            <li class="landing-feature">
                <span class="landing-feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                </span>
                <h3 class="landing-feature-title">Keeps itself fresh</h3>
                <p>The board updates on a short rhythm so “refresh the page” isn’t part of anyone’s job.</p>
            </li>
            <li class="landing-feature">
                <span class="landing-feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                </span>
                <h3 class="landing-feature-title">Audit-ready</h3>
                <p>Action logging and structured roles so accountability isn’t an afterthought.</p>
            </li>
            <li class="landing-feature">
                <span class="landing-feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                </span>
                <h3 class="landing-feature-title">Plans that fit</h3>
                <p>Feature matrix per subscription tier—turn on training, chat, reports, and more when you need them.</p>
            </li>
        </ul>
    </section>

    <section class="landing-section landing-how">
        <div class="landing-section-head">
            <h2 class="landing-h2">Three steps to a quieter day</h2>
        </div>
        <ol class="landing-steps">
            <li class="landing-step">
                <span class="landing-step-num">1</span>
                <div>
                    <h3 class="landing-step-title">Create or join your company</h3>
                    <p>One shared login for the organization; everyone gets their own PIN.</p>
                </div>
            </li>
            <li class="landing-step">
                <span class="landing-step-num">2</span>
                <div>
                    <h3 class="landing-step-title">See today at a glance</h3>
                    <p>Tasks, weather where it matters, and assignments that stay in sync.</p>
                </div>
            </li>
            <li class="landing-step">
                <span class="landing-step-num">3</span>
                <div>
                    <h3 class="landing-step-title">Close the loop</h3>
                    <p>Complete work, track patterns, and scale features as the team grows.</p>
                </div>
            </li>
        </ol>
    </section>

    <section class="landing-final card">
        <div class="landing-final-inner">
            <div>
                <h2 class="landing-final-title">Bring your team into focus</h2>
                <p class="landing-final-copy">Start with company sign-in—your people pick up where they left off, every shift.</p>
            </div>
            <div class="landing-final-actions">
                <a class="landing-btn landing-btn-primary" href="/login">Go to login</a>
                <?php if (filter_var((string) env('ALLOW_PUBLIC_SIGNUP', 'true'), FILTER_VALIDATE_BOOL)): ?>
                    <a class="landing-btn landing-btn-ghost" href="/register">Register a company</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
<?php require base_path('views/partials/footer.php'); ?>
</body>
</html>
