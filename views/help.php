<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Help') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light has-site-footer">
<header class="topbar">
    <div class="brand">Help</div>
    <div class="actions">
        <a href="/dashboard"><button type="button">Dashboard</button></a>
        <a href="/login"><button type="button" class="ghost">Login</button></a>
        <button id="theme-toggle-help" type="button" class="ghost">Toggle theme</button>
    </div>
</header>
<main class="dashboard help-page">
    <section class="card help-intro">
        <h1>How DoItMeow works</h1>
        <p class="muted">DoItMeow is a task board for your workplace: your company signs in once, then each person uses a short PIN on shared devices. This guide describes current behavior across the app (tasks, admin, time zones, weather, and more).</p>
    </section>

    <nav class="card help-toc" aria-label="On this page">
        <h2 class="help-toc-title">On this page</h2>
        <ol class="help-toc-list">
            <li><a href="#signing-in">Signing in</a></li>
            <li><a href="#company-time">Company time, weather &amp; footer</a></li>
            <li><a href="#dashboard">Dashboard &amp; tasks</a></li>
            <li><a href="#appointment-board">Appointment board</a></li>
            <li><a href="#roles">Roles &amp; permissions</a></li>
            <li><a href="#admin">Admin</a></li>
            <li><a href="#reports">Reports</a></li>
            <li><a href="#training">Training</a></li>
            <li><a href="#support">Support tickets</a></li>
            <li><a href="#plans">Plans &amp; features</a></li>
            <li><a href="#changelog">Changelog &amp; version</a></li>
            <li><a href="#other">Other</a></li>
        </ol>
    </nav>

    <article class="help-article">
        <section class="card help-section" id="signing-in">
            <h2>Signing in</h2>
            <h3>Two steps</h3>
            <ol>
                <li><strong>Company login</strong> — On the login screen, enter your organization’s username and password. This identifies <em>which company</em> you belong to.</li>
                <li><strong>Your PIN</strong> — On the dashboard, enter your personal 6-digit PIN on the keypad. This identifies <em>you</em> as a user. You can use “Switch user” to sign out your PIN and let someone else sign in on the same browser without re-entering the company password.</li>
            </ol>
            <h3>Tips</h3>
            <ul>
                <li>Company session and user PIN are separate: logging out “all the way” uses <strong>Logout</strong> and returns you to the company login page.</li>
                <li>After too many wrong PIN attempts, your PIN may lock briefly for security.</li>
                <li>The footer can show <strong>live local time</strong> for your company’s time zone once you are company-signed-in. <strong>Current weather</strong> in the footer (from your company’s US ZIP) loads after you complete PIN sign-in, because that data is tied to your user session. After PIN sign-in you also get <strong>Support &amp; tickets</strong> in the footer to open your company’s support page.</li>
            </ul>
        </section>

        <section class="card help-section" id="company-time">
            <h2>Company time, weather &amp; footer</h2>
            <p class="muted">The site footer stays at the bottom of the window while you scroll long pages, so version, help, the pet feeding calculator, and (when signed in) the clock and weather line stay visible.</p>
            <h3>Time zone (not the server clock)</h3>
            <p>Your organization’s <strong>time zone</strong> is set under <strong>Admin → Company</strong> (IANA name, e.g. <code>America/New_York</code>). It is chosen from a dropdown so it is always valid.</p>
            <ul>
                <li><strong>“Today” for tasks</strong> — Open tasks, due dates, recurrence (day of week, day of month), and “completed today” use this calendar day in the <em>company</em> time zone, not the hosting server’s time zone.</li>
                <li><strong>Reports</strong> — When you pick a date range, defaults align with company-local dates when your session provides the time zone.</li>
                <li><strong>Footer clock</strong> — After company sign-in, the footer shows the <strong>current date and time</strong> in that same time zone and updates every second.</li>
                <li><strong>Seasonal background</strong> — After PIN sign-in, many pages use a very light seasonal tint around <strong>US federal public holidays</strong> (four days before and after each holiday, in your company’s calendar). There is no separate holiday list page; which day counts as “today” still follows this same time zone.</li>
            </ul>
            <h3>US ZIP and weather</h3>
            <p>Admins can enter a <strong>US ZIP code</strong> (5 digits or ZIP+4) in <strong>Admin → Company</strong>. Leave it blank to turn weather off.</p>
            <ul>
                <li><strong>Dashboard — Weather alerts</strong> — When the ZIP is set and PIN sign-in is active, if the National Weather Service has <strong>active alerts</strong> for that area, a card appears <strong>above “Open tasks”</strong> with headline, area, expiry, and a link to details on weather.gov. The app refreshes alert data on a schedule (and caches results server-side briefly so the weather service isn’t overloaded).</li>
                <li><strong>Footer — Current conditions</strong> — After PIN sign-in, the footer can show <strong>approximate temperature (°F)</strong> and a short description (e.g. “Partly Cloudy”) from the nearest NWS observation site. This updates on a slower schedule than the clock.</li>
                <li>Weather features require a <strong>valid US ZIP</strong> and depend on third-party services (geocoding and NOAA/NWS). If something is unavailable, the card or footer line may stay hidden until service returns.</li>
                <li><strong>Pet feeding calculator</strong> — Opens in a separate window from the footer. Enter body weight, food kcal/cup, animal type, and lifestyle to estimate daily cups and per-meal portions.</li>
            </ul>
        </section>

        <section class="card help-section" id="dashboard">
            <h2>Dashboard &amp; tasks</h2>
            <h3>The board</h3>
            <p>Open tasks for <strong>today</strong> (in the <a href="#company-time">company time zone</a>) appear in columns by <strong>time of day</strong> (for example Morning, Afternoon, Evening). Admins define those columns under Admin → Time of day. Each column grows with its open tasks; use normal page scroll to move through the board (on wide screens you can still scroll sideways if there are many columns).</p>
            <ul>
                <li><strong>Done</strong> — Marks today’s occurrence complete. Completed work shows in “Completed today.”</li>
                <li><strong>Add task / Edit</strong> — Users with task-edit permission can add a task from the board header and edit a task inline from each card without leaving the dashboard.</li>
                <li><strong>Infinity</strong> — Some tasks carry forward until done; the board may label them as carried over.</li>
                <li><strong>Assigned to me</strong> — Opens a list of today’s open tasks assigned to you; you can complete them from there without hunting the columns. If that count <strong>increases while you stay on the dashboard</strong> (someone assigns you more work), the list can open automatically so the new items are visible.</li>
                <li><strong>Completed today</strong> — Lists what was finished today; managers can sometimes undo a completion from the same day. Completion times are shown in the <a href="#company-time">company time zone</a> (not the web server’s clock).</li>
                <li><strong>Live updates</strong> — While your PIN is active, the task board refreshes periodically so you see changes from teammates. Recurring tasks (including weekly and infinity) get their “today” row automatically when you open the dashboard so they can appear even if they were first saved on a different weekday.</li>
                <li><strong>PIN errors</strong> — If your PIN is wrong or invalid, a highlighted message appears above the keypad so it’s easy to notice; try again or ask an admin to confirm your PIN.</li>
            </ul>
            <h3>Weather alerts (when configured)</h3>
            <p>If your company saved a US ZIP, see <a href="#company-time">Company time, weather &amp; footer</a>. Active NWS alerts appear in a highlighted card above the task columns when present.</p>
            <h3>System messages</h3>
            <p>Notices can appear above weather alerts on the dashboard. Tenant admins only publish for their own company. Owner/super admin can also publish global notices and maintenance mode (global; view-only for everyone except owner/super admin until removed).</p>
            <h3>Recurrence (in admin)</h3>
            <p>Tasks can be one-off, daily, weekly, bi-weekly (with an anchor date), monthly, or quarterly. Monthly and quarterly can use either a day of month or the <strong>first chosen weekday</strong> in that month. Admins set recurrence when creating or editing a task. Which day counts as “today” follows the company time zone.</p>
        </section>

        <section class="card help-section" id="appointment-board">
            <h2>Appointment board</h2>
            <p>After PIN sign-in, open <strong>Appointments</strong> from the dashboard header to add rows to a live schedule for waiting areas or treatment rooms.</p>
            <ul>
                <li><strong>Privacy</strong> — Patient names are generated automatically (random labels); you choose the team member, appointment date/time, and optional notes.</li>
                <li><strong>Public display</strong> — Under <strong>Admin → Company</strong>, copy the public board URL (or open it from the Appointments page). Open that link on a tablet or TV with <code>?company=…</code> in the address bar.</li>
                <li><strong>Colors</strong> — Past-due appointments are red, those within the next two hours are yellow, and later ones are green. Rows sort with the most overdue at the top and the furthest appointment at the bottom.</li>
                <li><strong>Refresh &amp; cleanup</strong> — The public page refreshes every 30 seconds. Rows disappear 30 minutes after their appointment time.</li>
            </ul>
        </section>

        <section class="card help-section" id="roles">
            <h2>Roles &amp; permissions</h2>
            <ul>
                <li><strong>Owner / Super admin</strong> — Full platform or multi-tenant control (where enabled), including impersonating another company for support.</li>
                <li><strong>Tenant admin</strong> — Full company setup: users, tasks, slots, priorities, company profile (time zone, ZIP, logo, theme), billing-related settings where applicable.</li>
                <li><strong>Tenant manager</strong> — Can manage day-to-day tasks and team training sign-offs; may have a narrower user list when creating users.</li>
                <li><strong>Tenant task</strong> — Day-to-day user access plus task create/edit permissions, without broader admin controls.</li>
                <li><strong>Tenant user</strong> — Uses the dashboard and assigned work; typically cannot open Admin or Reports.</li>
            </ul>
            <p>Exact buttons depend on your subscription’s <strong>feature matrix</strong> (see below).</p>
        </section>

        <section class="card help-section" id="admin">
            <h2>Admin</h2>
            <p>The Admin area is for people who configure the company. You start on a <strong>home</strong> page of cards; each card opens its own page. Use <strong>Admin home</strong> in the header to return to the cards. What you see depends on your role and plan.</p>
            <ul>
                <li><strong>Home</strong> — Quick entry to tasks, people, company settings, and (when your role and plan allow) <strong>task reports</strong>, <strong>support tickets</strong> for your organization, <strong>all companies’ tickets</strong>, <strong>view as tenant</strong>, and <strong>create company</strong> for owner/super admin.</li>
                <li><strong>Tasks</strong> — Create and edit tasks: title, details, priority, time-of-day column, recurrence, optional assignee, infinity, and active/inactive. Edit can open <strong>inline under the task row</strong> so you don’t scroll back to the top. <strong>Delete</strong> soft-deletes a task: it disappears from the board and task list but past completions stay available for reporting.</li>
                <li><strong>Time of day</strong> — Name and order the columns on the dashboard; you can deactivate a slot you no longer need. Task assignments use the slot saved on each task; the board keeps tasks aligned with your columns.</li>
                <li><strong>Priorities</strong> — Colors and labels used on the board.</li>
                <li><strong>Messages</strong> — Tenant admins see only tenant-wide notices for their company. Owner/super admin additionally see options for global notices and maintenance mode (global-only; most users become view-only until removed).</li>
                <li><strong>People</strong> — Create users, roles, and PINs.</li>
                <li><strong>Company</strong> — Company name, owner email, <strong>time zone</strong> (dropdown), <strong>US ZIP for weather</strong> (optional), header logo URL, default theme, and (for privileged roles) company login, plan, and account active flag.</li>
                <li><strong>Plans / Feature matrix</strong> — Subscription level and which optional features (reports, training, chat, ads, etc.) are on for your tenant.</li>
                <li><strong>Training</strong> (when enabled) — Definitions, templates, and assigning training to users.</li>
                <li><strong>Audit logs</strong> (when enabled) — History of important actions.</li>
            </ul>
            <p>New companies created from the public registration form also pick a <strong>time zone</strong> from the same kind of dropdown.</p>
        </section>

        <section class="card help-section" id="reports">
            <h2>Reports</h2>
            <p>When your plan includes <strong>Reports</strong> and your role is <strong>manager or higher</strong>, open <strong>Reports</strong> from the <strong>Admin home</strong> cards (or bookmark <code>/reports</code>). You’ll see completions by date range: totals per day, per person, and a detail list. Historical data includes work from tasks that were later soft-deleted. Dates and completion times in the detail table use the <a href="#company-time">company time zone</a>.</p>
            <p>Use the <strong>Rows</strong> control to load more or fewer completions in the detail table (including “all” up to 50,000). <strong>Export CSV</strong> downloads the detail rows currently loaded (full task details in the file, not the shortened table preview).</p>
            <p>Default date ranges use <strong>company-local dates</strong> when your session includes the company time zone.</p>
        </section>

        <section class="card help-section" id="training">
            <h2>Training</h2>
            <p>If <strong>Employee training</strong> is enabled for your plan, users see a Training link. Managers assign training items or bundles (templates). Each assignment needs three manager sign-offs: <strong>See 1</strong>, <strong>Do 1</strong>, and <strong>Teach 1</strong>. The team view groups people in expandable sections so sign-offs aren’t cramped in one wide table.</p>
        </section>

        <section class="card help-section" id="support">
            <h2>Support tickets</h2>
            <p>After you sign in with your PIN, use <strong>Support &amp; tickets</strong> in the <a href="#company-time">site footer</a> to open your company’s support page: submit a problem report (title, message, optional image, and priority), see open items, and open a thread. Managers and admins can also reach the same page from the <strong>Admin home</strong> cards.</p>
            <p><strong>Close</strong> a ticket when the issue is resolved (anyone in the company can close their company’s tickets, or owner/super admin can close any ticket). While you view a ticket, new replies appear automatically without refreshing the page.</p>
            <p><strong>Owner</strong> and <strong>super admin</strong> use <strong>All companies’ tickets</strong> on <strong>Admin home</strong> to browse and respond to tickets from every company.</p>
        </section>

        <section class="card help-section" id="plans">
            <h2>Plans &amp; features</h2>
            <p>Your organization’s <strong>subscription level</strong> controls optional modules: task reports, custom themes, employee training, in-app chat (ArrowChat), advertisements on the dashboard, and more. Admins with access adjust these in the <strong>feature matrix</strong> and company profile. If something is missing from your menu, it may be turned off for your plan.</p>
            <p class="muted">When Google AdSense is configured on the server, the public home page may load the publisher script only (no ad units there) so Google can recognize the site; dashboard ads still follow the feature matrix. If you use AdSense, review <strong>Auto ads</strong> in your AdSense account so unwanted placements do not appear on the home page.</p>
            <p><strong>ArrowChat</strong> loads from a single autoload script in the <strong>footer</strong> after your company is signed in, when the <strong>ArrowChat</strong> feature is turned <strong>on</strong> in the feature matrix for your plan. The server may set <code>ARROWCHAT_AUTOLOAD_URL</code> if the chat host URL differs from the default.</p>
            <p>If the chat bar appears but <strong>no users</strong> show or ArrowChat endpoints (such as <code>receive_search.php</code>) return errors: the chat scripts run on the <strong>ArrowChat host</strong>, which does not share your DoItMeow login cookie unless you configure that. Options: set <code>SESSION_COOKIE_DOMAIN</code> in <code>.env</code> (e.g. <code>.doitmeow.com</code>) so the <code>doitmeow_session</code> cookie is visible to ArrowChat on another subdomain <strong>and</strong> both apps use the same session storage; or use the optional <strong>bridge cookie</strong> (<code>ARROWCHAT_BRIDGE_*</code>) and the sample in <code>app/Core/ArrowChatBridge.php</code>. On the ArrowChat server, <code>includes/config.php</code> must use the same MySQL database and column names as DoItMeow’s <code>users</code> table (<code>id</code>, <code>display_name</code>, <code>tenant_id</code>). Check the PHP error log on the ArrowChat host for the exact cause of HTTP 500 responses.</p>
        </section>

        <section class="card help-section" id="changelog">
            <h2>Changelog &amp; version</h2>
            <p>The site footer shows the <strong>deployed app version</strong> (from the <code>VERSION</code> file at the project root, unless <code>APP_VERSION</code> is set in server configuration) and a link to the <a href="/changelog">changelog page</a>. That page lists release notes from the database, which are updated when new migrations run on deploy. Versions are ordered with <strong>newest first</strong> using proper version numbers (for example 1.0.11 after 1.0.10), not as plain text.</p>
        </section>

        <section class="card help-section" id="other">
            <h2>Other</h2>
            <h3>Maintenance mode</h3>
            <p>During maintenance, writes may be disabled across the app. You can still browse, but saving or completing tasks might fail until the system is back to normal.</p>
            <h3>Theme</h3>
            <p>Light and dark themes are available wherever you see <strong>Toggle theme</strong>. Logged-in users can have a personal preference stored when supported.</p>
            <h3>This help page</h3>
            <p>The product team intends to keep this guide aligned with shipped behavior (tasks, admin, time zones, weather, reports, training, and plans). If something here doesn’t match what you see, tell your admin so they can confirm your plan settings or report a discrepancy.</p>
            <h3>Need more detail?</h3>
            <p>Ask your <strong>tenant admin</strong> or <strong>owner</strong> for company-specific rules (PIN policy, which columns to use, time zone and ZIP choices, and training expectations).</p>
        </section>
    </article>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
