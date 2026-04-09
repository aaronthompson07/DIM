<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'DoItMeow Admin') ?></title>
    <link rel="stylesheet" href="/resources/css/app.css">
    <?php require base_path('views/partials/ga.php'); ?>
</head>
<body class="theme-light admin-body has-site-footer">
<header class="topbar">
    <div class="topbar-brand">
        <a href="/dashboard" class="product-logo-link" aria-label="DoItMeow">
            <img class="product-logo" src="/resources/images/doitmeow.png" alt="">
        </a>
        <div class="brand">DoItMeow Admin</div>
    </div>
    <div class="actions topbar-actions">
        <button type="button" id="admin-theme-toggle" class="ghost">Toggle theme</button>
        <a href="/dashboard"><button type="button">Back to Dashboard</button></a>
    </div>
</header>
<main class="admin-main">
    <?php if ($limitedMode): ?>
        <div class="banner warning">Maintenance mode is active. Write actions are disabled.</div>
    <?php endif; ?>

    <section class="card hidden" id="impersonation-card">
        <h2>Companies (owner / super admin)</h2>
        <p class="muted">View another tenant’s data, or create a new company. Your login session stays on your home tenant.</p>
        <div class="stack">
            <label>View tenant</label>
            <select id="impersonate-tenant-select"></select>
            <div class="row-actions row-actions-spaced">
                <button type="button" id="impersonate-go">View this tenant</button>
                <button type="button" id="impersonate-clear" class="ghost">Stop viewing</button>
            </div>
            <p id="impersonation-status" class="muted"></p>
        </div>
        <hr class="admin-divider">
        <h3>Create new company</h3>
        <p class="muted">Creates a tenant with default time slots and a tenant admin. They sign in with the company login + admin PIN.</p>
        <form id="create-tenant-form" class="stack form-grid-2">
            <div>
                <label>Company name</label>
                <input type="text" id="ct-name" required maxlength="120" autocomplete="organization">
            </div>
            <div>
                <label>Company login</label>
                <input type="text" id="ct-username" required maxlength="79" pattern="[a-zA-Z0-9][a-zA-Z0-9_-]*" autocomplete="off">
            </div>
            <div>
                <label>Company password</label>
                <input type="password" id="ct-password" required minlength="8" autocomplete="new-password">
            </div>
            <div>
                <label>Owner email</label>
                <input type="email" id="ct-email" required maxlength="190" autocomplete="email">
            </div>
            <div>
                <label>Admin display name</label>
                <input type="text" id="ct-admin-name" required maxlength="120" autocomplete="name">
            </div>
            <div>
                <label>Admin 6-digit PIN</label>
                <input type="password" id="ct-admin-pin" required maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code">
            </div>
            <div class="form-grid-full">
                <label>Timezone</label>
                <?php
                $timezoneSelectId = 'ct-tz';
                $timezoneSelectName = null;
                $timezoneSelected = 'UTC';
                require base_path('views/partials/timezone_select.php');
                ?>
            </div>
            <div class="form-grid-full row-actions row-actions-spaced">
                <button type="submit" id="ct-submit">Create company</button>
            </div>
        </form>
    </section>

    <nav class="admin-tabs" id="admin-tabs" aria-label="Admin sections"></nav>

    <div class="admin-panels">
        <div class="admin-panel hidden" id="panel-tasks" data-panel="tasks">
            <div class="card">
                <h2>Tasks</h2>
                <p class="muted">Assign each task to a time-of-day column (Morning, Afternoon, etc.).</p>
                <form id="task-form" class="stack">
                    <input type="hidden" id="task-edit-id" value="">
                    <label>Title</label>
                    <input type="text" id="task-title" required>
                    <label>Details</label>
                    <textarea id="task-details" rows="3"></textarea>
                    <label>Priority</label>
                    <select id="task-priority" required></select>
                    <label>Time of day</label>
                    <select id="task-slot" required></select>
                    <label>Recurrence</label>
                    <select id="task-recurrence">
                        <option value="none">One-off</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="biweekly">Bi-weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                    </select>
                    <div id="task-weekdays-block" class="hidden stack">
                        <span class="muted">Days of week (select any combination)</span>
                        <div class="task-weekdays-grid" role="group" aria-label="Weekdays">
                            <label class="inline-check task-wd-label"><input type="checkbox" class="task-wd" data-day="0"> Sun</label>
                            <label class="inline-check task-wd-label"><input type="checkbox" class="task-wd" data-day="1"> Mon</label>
                            <label class="inline-check task-wd-label"><input type="checkbox" class="task-wd" data-day="2"> Tue</label>
                            <label class="inline-check task-wd-label"><input type="checkbox" class="task-wd" data-day="3"> Wed</label>
                            <label class="inline-check task-wd-label"><input type="checkbox" class="task-wd" data-day="4"> Thu</label>
                            <label class="inline-check task-wd-label"><input type="checkbox" class="task-wd" data-day="5"> Fri</label>
                            <label class="inline-check task-wd-label"><input type="checkbox" class="task-wd" data-day="6"> Sat</label>
                        </div>
                    </div>
                    <div id="task-anchor-row" class="hidden stack">
                        <label for="task-anchor-date">Anchor date</label>
                        <input type="date" id="task-anchor-date">
                        <p class="muted form-hint">Anchor date marks when the cycle starts. Bi-weekly repeats every other week from this date; quarterly repeats every 3 months from this date.</p>
                    </div>
                    <div id="task-monthly-weekday-row" class="hidden stack">
                        <label for="task-monthly-weekday">Monthly/Quarterly weekday (optional)</label>
                        <select id="task-monthly-weekday">
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
                    <label id="task-dom-label" class="hidden">Day of month (1–31)</label>
                    <input type="number" id="task-dom" class="hidden" min="1" max="31" value="1">
                    <label>Assign to (optional)</label>
                    <select id="task-assignee">
                        <option value="">— None —</option>
                    </select>
                    <label class="inline-check"><input type="checkbox" id="task-infinity"> Infinity (stays until done)</label>
                    <label class="inline-check"><input type="checkbox" id="task-active" checked> Active</label>
                    <div class="row-actions">
                        <button type="submit" id="task-save">Save task</button>
                        <button type="button" id="task-new" class="ghost">New task</button>
                    </div>
                </form>
                <h3>Existing tasks</h3>
                <p class="muted form-hint">Edit opens the same fields below that task. Delete hides the task from the dashboard and this list; past completions still appear in reports.</p>
                <ul id="task-list" class="task-admin-list"></ul>
                <div id="task-inline-wrap" class="task-inline-wrap">
                    <div id="task-inline-shell" class="task-inline-shell hidden">
                        <form id="task-inline-form" class="stack">
                            <input type="hidden" id="inline-task-edit-id" value="">
                            <label>Title</label>
                            <input type="text" id="inline-task-title" required>
                            <label>Details</label>
                            <textarea id="inline-task-details" rows="3"></textarea>
                            <label>Priority</label>
                            <select id="inline-task-priority" required></select>
                            <label>Time of day</label>
                            <select id="inline-task-slot" required></select>
                            <label>Recurrence</label>
                            <select id="inline-task-recurrence">
                                <option value="none">One-off</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                            </select>
                            <div id="inline-task-weekdays-block" class="hidden stack">
                                <span class="muted">Days of week (select any combination)</span>
                                <div class="task-weekdays-grid" role="group" aria-label="Weekdays">
                                    <label class="inline-check task-wd-label"><input type="checkbox" class="inline-task-wd" data-day="0"> Sun</label>
                                    <label class="inline-check task-wd-label"><input type="checkbox" class="inline-task-wd" data-day="1"> Mon</label>
                                    <label class="inline-check task-wd-label"><input type="checkbox" class="inline-task-wd" data-day="2"> Tue</label>
                                    <label class="inline-check task-wd-label"><input type="checkbox" class="inline-task-wd" data-day="3"> Wed</label>
                                    <label class="inline-check task-wd-label"><input type="checkbox" class="inline-task-wd" data-day="4"> Thu</label>
                                    <label class="inline-check task-wd-label"><input type="checkbox" class="inline-task-wd" data-day="5"> Fri</label>
                                    <label class="inline-check task-wd-label"><input type="checkbox" class="inline-task-wd" data-day="6"> Sat</label>
                                </div>
                            </div>
                            <div id="inline-task-anchor-row" class="hidden stack">
                                <label for="inline-task-anchor-date">Anchor date</label>
                                <input type="date" id="inline-task-anchor-date">
                                <p class="muted form-hint">Anchor date marks when the cycle starts. Bi-weekly repeats every other week from this date; quarterly repeats every 3 months from this date.</p>
                            </div>
                            <div id="inline-task-monthly-weekday-row" class="hidden stack">
                                <label for="inline-task-monthly-weekday">Monthly/Quarterly weekday (optional)</label>
                                <select id="inline-task-monthly-weekday">
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
                            <label id="inline-task-dom-label" class="hidden">Day of month (1–31)</label>
                            <input type="number" id="inline-task-dom" class="hidden" min="1" max="31" value="1">
                            <label>Assign to (optional)</label>
                            <select id="inline-task-assignee">
                                <option value="">— None —</option>
                            </select>
                            <label class="inline-check"><input type="checkbox" id="inline-task-infinity"> Infinity (stays until done)</label>
                            <label class="inline-check"><input type="checkbox" id="inline-task-active" checked> Active</label>
                            <div class="row-actions">
                                <button type="submit" id="inline-task-save">Save changes</button>
                                <button type="button" id="inline-task-cancel" class="ghost">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-panel hidden" id="panel-slots" data-panel="slots">
            <div class="card">
                <h2>Time of day</h2>
                <p class="muted">Names appear as columns on the dashboard (left to right by sort order). Disabled slots stay off the board but keep existing task links.</p>
                <div class="stack row-actions">
                    <input type="text" id="new-slot-name" placeholder="e.g. Midday">
                    <button type="button" id="new-slot-add">Add slot</button>
                </div>
                <ul id="slot-editor-list" class="slot-editor-list"></ul>
            </div>
        </div>

        <div class="admin-panel hidden" id="panel-messages" data-panel="messages">
            <div class="card">
                <h2>System messages</h2>
                <p class="muted">Display notices to all users on the dashboard. Maintenance messages switch most users to read-only mode.</p>
                <form id="system-message-form" class="stack">
                    <label for="system-message-text">Message</label>
                    <textarea id="system-message-text" rows="3" maxlength="2000" required></textarea>
                    <label id="system-message-scope-row" class="inline-check hidden">
                        <input type="checkbox" id="system-message-global">
                        Global message (all tenants)
                    </label>
                    <label class="inline-check">
                        <input type="checkbox" id="system-message-maintenance">
                        Maintenance mode (always global; view-only for everyone except owner / super admin)
                    </label>
                    <p class="muted form-hint" id="system-message-maintenance-hint">Tenant admins can publish tenant-only notices. Owner/super admin can publish global notices and maintenance mode.</p>
                    <div class="row-actions">
                        <button type="submit">Publish message</button>
                    </div>
                </form>
                <h3>Active + recent messages</h3>
                <ul id="system-message-list" class="task-admin-list"></ul>
            </div>
        </div>

        <div class="admin-panel hidden" id="panel-users" data-panel="users">
            <div class="card">
                <h2>People</h2>
                <p class="muted">Create and edit users, set PINs, or disable access (users are not deleted).</p>
                <h3>Add user</h3>
                <form id="user-create-form" class="stack">
                    <label>Display name</label>
                    <input type="text" id="new-user-name" required>
                    <label>Email (optional)</label>
                    <input type="email" id="new-user-email">
                    <label>Role</label>
                    <select id="new-user-role"></select>
                    <label>6-digit PIN</label>
                    <input type="password" id="new-user-pin" maxlength="6" pattern="\d{6}" inputmode="numeric" required>
                    <button type="submit">Create user</button>
                </form>
                <h3>Team</h3>
                <div id="user-list-wrap" class="user-list-wrap"></div>
            </div>
        </div>

        <div class="admin-panel hidden" id="panel-company" data-panel="company">
            <div class="card">
                <h2>Company settings</h2>
                <p class="muted">Applies to the tenant you are viewing (including when impersonating another company).</p>
                <form id="company-profile-form" class="stack">
                    <label>Company name</label>
                    <input type="text" id="co-name" maxlength="120" required>
                    <label>Owner email</label>
                    <input type="email" id="co-owner-email" maxlength="190" required>
                    <label>Timezone</label>
                    <?php
                    $timezoneSelectId = 'co-timezone';
                    $timezoneSelectName = null;
                    $timezoneSelected = 'UTC';
                    require base_path('views/partials/timezone_select.php');
                    ?>
                    <label>US ZIP code (weather alerts)</label>
                    <input type="text" id="co-zip-code" maxlength="10" pattern="\d{5}(-\d{4})?" inputmode="numeric" placeholder="e.g. 90210" autocomplete="postal-code">
                    <p class="muted form-hint">Used to show active NOAA / National Weather Service alerts on the team dashboard. Leave blank to disable.</p>
                    <label>Header logo URL</label>
                    <input type="url" id="co-logo-url" maxlength="255" placeholder="https://… (optional)">
                    <p class="muted form-hint">Shown next to the company name in the dashboard header. Use a direct image link (PNG/SVG).</p>
                    <label>Default theme</label>
                    <select id="co-theme">
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                    </select>
                    <label>New company password</label>
                    <input type="password" id="co-password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current">
                    <div id="co-platform-fields" class="stack hidden">
                        <label>Company login (username)</label>
                        <input type="text" id="co-company-username" maxlength="79" pattern="[a-zA-Z0-9][a-zA-Z0-9_-]*" autocomplete="off">
                        <label>Subscription plan</label>
                        <select id="co-plan-key"></select>
                        <label class="inline-check"><input type="checkbox" id="co-is-active" checked> Company account active</label>
                    </div>
                    <button type="submit" id="save-company-profile">Save company settings</button>
                </form>
            </div>
        </div>

        <div class="admin-panel hidden" id="panel-priorities" data-panel="priorities">
            <div class="card">
                <h2>Priorities</h2>
                <p class="muted">Define levels and colors. Each open task shows a colored bar on the left matching its priority.</p>
                <div class="stack row-actions">
                    <input type="text" id="new-priority-name" placeholder="e.g. Urgent">
                    <input type="color" id="new-priority-color" value="#64748b" title="Color" aria-label="Priority color">
                    <button type="button" id="new-priority-add">Add priority</button>
                </div>
                <ul id="priority-editor-list" class="slot-editor-list"></ul>
            </div>
        </div>

        <div class="admin-panel hidden" id="panel-plans" data-panel="plans">
            <div class="card">
                <h2>Subscription plans</h2>
                <p class="muted">Owner / super admin: create plans and map features on the Plans matrix tab.</p>
                <h3>Add plan</h3>
                <form id="plan-create-form" class="stack form-grid-2">
                    <div>
                        <label>Plan key (slug)</label>
                        <input type="text" id="plan-level-key" required maxlength="79" pattern="[a-z0-9][a-z0-9_]*" placeholder="e.g. pro_monthly">
                    </div>
                    <div>
                        <label>Display name</label>
                        <input type="text" id="plan-name" required maxlength="120">
                    </div>
                    <div>
                        <label>Monthly price (optional)</label>
                        <input type="number" id="plan-price" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="inline-check" style="align-self:end;padding-top:1.5rem">
                        <label class="inline-check"><input type="checkbox" id="plan-is-paid"> Paid plan</label>
                        <label class="inline-check"><input type="checkbox" id="plan-show-ads" checked> Show ads</label>
                    </div>
                    <div class="form-grid-full">
                        <button type="submit">Create plan</button>
                    </div>
                </form>
                <h3>Existing plans</h3>
                <div id="plans-editor-wrap" class="stack"></div>
            </div>
        </div>

        <div class="admin-panel hidden" id="panel-logs" data-panel="logs">
            <div class="card">
                <h2>Activity logs</h2>
                <p class="muted">Recent audit events for this company (newest first, up to 150).</p>
                <div class="reports-table-wrap">
                    <table class="matrix-table" id="audit-logs-table">
                        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="admin-panel admin-panel-training hidden" id="panel-training" data-panel="training">
            <div class="card">
                <h2>Training items</h2>
                <p class="muted">Reusable tasks (e.g. “Fire safety video”). Save, then add them to templates or assign directly.</p>
                <form id="tr-def-form" class="stack">
                    <input type="hidden" id="tr-def-id" value="">
                    <label>Title</label>
                    <input type="text" id="tr-def-title" required maxlength="200">
                    <label>Details</label>
                    <textarea id="tr-def-details" rows="2"></textarea>
                    <label>Sort order</label>
                    <input type="number" id="tr-def-sort" value="0">
                    <label class="inline-check"><input type="checkbox" id="tr-def-active" checked> Active</label>
                    <div class="row-actions">
                        <button type="submit">Save definition</button>
                        <button type="button" id="tr-def-new" class="ghost">New</button>
                    </div>
                </form>
                <ul id="tr-def-list" class="task-admin-list"></ul>
            </div>
            <div class="card">
                <h2>Training templates</h2>
                <p class="muted">Named bundles of items. Assign a whole template to a user in one step.</p>
                <form id="tr-tpl-form" class="stack">
                    <input type="hidden" id="tr-tpl-id" value="">
                    <label>Name</label>
                    <input type="text" id="tr-tpl-name" required maxlength="200">
                    <label>Description</label>
                    <textarea id="tr-tpl-desc" rows="2"></textarea>
                    <label class="inline-check"><input type="checkbox" id="tr-tpl-active" checked> Active</label>
                    <div class="row-actions">
                        <button type="submit">Save template</button>
                        <button type="button" id="tr-tpl-new" class="ghost">New</button>
                    </div>
                </form>
                <ul id="tr-tpl-list" class="task-admin-list"></ul>
                <hr class="admin-divider">
                <h3>Template contents</h3>
                <p class="muted">Choose a template, add training items one at a time, reorder as needed, then save.</p>
                <div class="stack">
                    <label>Template</label>
                    <select id="tr-items-template"></select>
                    <label>Add training item</label>
                    <div class="row-actions">
                        <select id="tr-item-pick-def" class="tr-item-pick-grow"></select>
                        <button type="button" id="tr-item-add">Add</button>
                    </div>
                    <p class="muted form-hint">Items in this list are included in order (top = first).</p>
                    <ul id="tr-template-items-order" class="tr-template-order-list"></ul>
                    <div class="row-actions">
                        <button type="button" id="tr-items-save">Save template items</button>
                    </div>
                </div>
            </div>
            <div class="card">
                <h2>Assign training</h2>
                <p class="muted">Assign one item or every item in a template. Skips items the user already has open.</p>
                <div class="stack form-grid-2">
                    <div>
                        <label>User</label>
                        <select id="tr-assign-user"></select>
                    </div>
                    <div>
                        <label>Single training item</label>
                        <select id="tr-assign-def"></select>
                    </div>
                    <div class="form-grid-full row-actions">
                        <button type="button" id="tr-assign-one">Assign item</button>
                    </div>
                    <div>
                        <label>Template</label>
                        <select id="tr-assign-tpl"></select>
                    </div>
                    <div class="form-grid-full row-actions">
                        <button type="button" id="tr-assign-bundle">Assign template</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-panel hidden" id="panel-matrix" data-panel="matrix">
            <div class="card">
                <h2>Feature matrix</h2>
                <p class="muted">Map product features to each billing plan (owner / super admin).</p>
                <p class="muted form-hint">Turn on <strong>Reports</strong> for the reports page (managers and above only). Turn on <strong>Employee training</strong> (<code>training.employee</code>) for training definitions, templates, assignments, and the Training link. For <strong>ArrowChat</strong>: enable the ArrowChat row here for the plan—the app injects the autoload script in the footer when a company session is active (optional env override <code>ARROWCHAT_AUTOLOAD_URL</code>). For Google ads: set <code>GOOGLE_ADSENSE_CLIENT</code> and <code>GOOGLE_ADSENSE_SLOT_DASHBOARD</code>, keep <strong>Show ads</strong> on the billing plan, and leave <strong>Ad-Free Experience</strong> unchecked for ad-supported tiers.</p>
                <div id="feature-matrix-wrap" class="stack"></div>
                <button type="button" id="save-feature-matrix">Save matrix</button>
            </div>
        </div>
    </div>

    <p id="admin-message" class="admin-footer-msg muted"></p>
</main>
<?php require base_path('views/partials/footer.php'); ?>
<script src="/resources/js/app.js"></script>
</body>
</html>
