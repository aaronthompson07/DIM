<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ArrowChatBridge;
use App\Core\ArrowChatIntegration;
use App\Core\Auth;
use App\Core\TenantTime;
use App\Services\AuditLogger;
use App\Services\WeatherAlertsService;
use PDO;

final class ApiController
{
    public function __construct(private readonly PDO $pdo, private readonly bool $limitedMode)
    {
    }

    public function companyLogin(): void
    {
        $input = $this->input();
        $ok = Auth::companyLogin($this->pdo, (string) ($input['company_username'] ?? ''), (string) ($input['password'] ?? ''));

        if (!$ok) {
            json_response(['ok' => false, 'message' => 'Invalid company credentials.'], 401);
            return;
        }

        AuditLogger::log($this->pdo, (int) $_SESSION['tenant_id'], null, 'auth.company_login');
        ArrowChatBridge::clearBridgeCookie();
        json_response(['ok' => true]);
    }

    public function pinLogin(): void
    {
        $input = $this->input();
        $pin = (string) ($input['pin'] ?? '');
        if (!preg_match('/^\d{6}$/', $pin)) {
            json_response(['ok' => false, 'message' => 'PIN must be 6 digits.'], 422);
            return;
        }

        $result = Auth::pinLoginByPin($this->pdo, $pin);
        if (!$result['ok']) {
            json_response($result, 401);
            return;
        }

        AuditLogger::log($this->pdo, Auth::homeTenantId(), (int) $_SESSION['user_id'], 'auth.user_pin_login');
        ArrowChatBridge::setBridgeCookie((int) $_SESSION['user_id'], Auth::homeTenantId());
        json_response(['ok' => true, 'user' => ['name' => $_SESSION['display_name'], 'role' => $_SESSION['role']]]);
    }

    public function companyUsers(): void
    {
        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, display_name, role
             FROM users
             WHERE tenant_id = :tenant_id AND is_active = 1
             ORDER BY display_name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        json_response(['ok' => true, 'users' => $stmt->fetchAll()]);
    }

    public function demoLogin(): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id AS tenant_id, u.id AS user_id, u.role, u.display_name
             FROM tenants t
             INNER JOIN users u ON u.tenant_id = t.id
             WHERE t.company_username = :company_username AND t.is_demo = 1
             ORDER BY u.id ASC
             LIMIT 1'
        );
        $stmt->execute(['company_username' => 'demo']);
        $row = $stmt->fetch();

        if (!$row) {
            json_response(['ok' => false, 'message' => 'Demo account is unavailable.'], 404);
            return;
        }

        $_SESSION['tenant_id'] = (int) $row['tenant_id'];
        $_SESSION['company_authenticated'] = true;
        $_SESSION['user_id'] = (int) $row['user_id'];
        $_SESSION['role'] = (string) $row['role'];
        $_SESSION['display_name'] = (string) $row['display_name'];

        AuditLogger::log($this->pdo, (int) $row['tenant_id'], (int) $row['user_id'], 'auth.demo_login');
        ArrowChatBridge::setBridgeCookie((int) $row['user_id'], (int) $row['tenant_id']);
        json_response(['ok' => true]);
    }

    public function todaySummary(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $date = TenantTime::todayYmd($this->pdo, $tenantId);

        $openStmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NULL
               AND t.deleted_at IS NULL
               AND (
                    o.due_date = :due_date_exact
                    OR (t.is_infinity = 1 AND o.due_date <= :due_date_upto)
               )'
        );
        $openStmt->execute([
            'tenant_id' => $tenantId,
            'due_date_exact' => $date,
            'due_date_upto' => $date,
        ]);

        $doneStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id
             WHERE o.tenant_id = :tenant_id AND o.due_date = :due_date AND o.completed_at IS NOT NULL
               AND t.deleted_at IS NULL'
        );
        $doneStmt->execute(['tenant_id' => $tenantId, 'due_date' => $date]);

        json_response([
            'ok' => true,
            'today' => $date,
            'open' => (int) $openStmt->fetchColumn(),
            'completed' => (int) $doneStmt->fetchColumn(),
            'limited_mode' => $this->isWriteBlockedForTenant($tenantId),
        ]);
    }

    /** Dashboard: summary + active time slots + open tasks (group client-side by slot). */
    public function todayBoard(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $date = TenantTime::todayYmd($this->pdo, $tenantId);

        $this->ensureTodayBoardOccurrences($tenantId, $date);

        $openStmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NULL
               AND t.deleted_at IS NULL
               AND (
                    o.due_date = :due_date_exact
                    OR (t.is_infinity = 1 AND o.due_date <= :due_date_upto)
               )'
        );
        $openStmt->execute([
            'tenant_id' => $tenantId,
            'due_date_exact' => $date,
            'due_date_upto' => $date,
        ]);

        $doneStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id
             WHERE o.tenant_id = :tenant_id AND o.due_date = :due_date AND o.completed_at IS NOT NULL
               AND t.deleted_at IS NULL'
        );
        $doneStmt->execute(['tenant_id' => $tenantId, 'due_date' => $date]);

        $slotStmt = $this->pdo->prepare(
            'SELECT id, name, sort_order
             FROM tenant_time_slots
             WHERE tenant_id = :tenant_id AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $slotStmt->execute(['tenant_id' => $tenantId]);
        $slots = $slotStmt->fetchAll();

        $taskStmt = $this->pdo->prepare(
            'SELECT
                o.id,
                t.id AS task_id,
                t.title,
                t.details,
                t.priority_key,
                t.priority_id,
                t.is_infinity,
                o.due_date,
                COALESCE(t.time_slot_id, o.time_slot_id) AS time_slot_id,
                ts.name AS time_slot_name,
                ts.sort_order AS time_slot_sort,
                t.assigned_user_id,
                au.display_name AS assignee_name,
                pr.name AS priority_name,
                pr.color_hex AS priority_color
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id
             LEFT JOIN tenant_time_slots ts ON ts.id = COALESCE(t.time_slot_id, o.time_slot_id) AND ts.tenant_id = o.tenant_id
             LEFT JOIN tenant_priorities pr ON pr.id = t.priority_id AND pr.tenant_id = t.tenant_id
             LEFT JOIN users au ON au.id = t.assigned_user_id AND au.tenant_id = t.tenant_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NULL
               AND t.deleted_at IS NULL
               AND (
                    o.due_date = :due_date_exact
                    OR (t.is_infinity = 1 AND o.due_date <= :due_date_upto)
               )
             ORDER BY COALESCE(ts.sort_order, 999) ASC, ts.id ASC, o.due_date ASC, o.id ASC'
        );
        $taskStmt->execute([
            'tenant_id' => $tenantId,
            'due_date_exact' => $date,
            'due_date_upto' => $date,
        ]);
        $tasks = $taskStmt->fetchAll();

        $slotOpenCounts = [];
        foreach ($slots as $s) {
            $slotOpenCounts[] = [
                'id' => (int) $s['id'],
                'name' => (string) $s['name'],
                'open' => 0,
            ];
        }
        $idxBySlot = [];
        foreach ($slotOpenCounts as $i => $row) {
            $idxBySlot[(string) $row['id']] = $i;
        }
        foreach ($tasks as $t) {
            $sid = $t['time_slot_id'] !== null ? (string) $t['time_slot_id'] : '';
            if ($sid !== '' && isset($idxBySlot[$sid])) {
                $slotOpenCounts[$idxBySlot[$sid]]['open']++;
            }
        }

        $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
        $assignedToMeStmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id AND t.tenant_id = o.tenant_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NULL
               AND t.deleted_at IS NULL
               AND t.assigned_user_id = :user_id
               AND (
                    o.due_date = :due_date_exact
                    OR (t.is_infinity = 1 AND o.due_date <= :due_date_upto)
               )'
        );
        $assignedToMeStmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $sessionUserId,
            'due_date_exact' => $date,
            'due_date_upto' => $date,
        ]);
        $assignedToMeOpen = (int) $assignedToMeStmt->fetchColumn();

        json_response([
            'ok' => true,
            'today' => $date,
            'open' => (int) $openStmt->fetchColumn(),
            'completed' => (int) $doneStmt->fetchColumn(),
            'assigned_to_me_open' => $assignedToMeOpen,
            'limited_mode' => $this->isWriteBlockedForTenant($tenantId),
            'system_messages' => $this->fetchActiveSystemMessages($tenantId),
            'slots' => $slots,
            'slot_open_counts' => $slotOpenCounts,
            'tasks' => $tasks,
        ]);
    }

    /** Open occurrences for today where the task is assigned to the current user. */
    public function assignedToMe(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $userId = (int) $_SESSION['user_id'];
        $date = TenantTime::todayYmd($this->pdo, $tenantId);

        $stmt = $this->pdo->prepare(
            'SELECT
                o.id,
                t.title,
                t.details,
                t.priority_key,
                t.priority_id,
                t.is_infinity,
                o.due_date,
                COALESCE(t.time_slot_id, o.time_slot_id) AS time_slot_id,
                ts.name AS time_slot_name,
                pr.name AS priority_name,
                pr.color_hex AS priority_color
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id AND t.tenant_id = o.tenant_id
             LEFT JOIN tenant_time_slots ts ON ts.id = COALESCE(t.time_slot_id, o.time_slot_id) AND ts.tenant_id = o.tenant_id
             LEFT JOIN tenant_priorities pr ON pr.id = t.priority_id AND pr.tenant_id = t.tenant_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NULL
               AND t.deleted_at IS NULL
               AND t.assigned_user_id = :user_id
               AND (
                    o.due_date = :due_date_exact
                    OR (t.is_infinity = 1 AND o.due_date <= :due_date_upto)
               )
             ORDER BY COALESCE(ts.sort_order, 999) ASC, ts.id ASC, o.due_date ASC, o.id ASC'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'due_date_exact' => $date,
            'due_date_upto' => $date,
        ]);

        json_response(['ok' => true, 'today' => $date, 'tasks' => $stmt->fetchAll()]);
    }

    public function todayTasks(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $date = TenantTime::todayYmd($this->pdo, $tenantId);

        $stmt = $this->pdo->prepare(
            'SELECT
                o.id,
                t.title,
                t.details,
                t.priority_key,
                t.priority_id,
                t.is_infinity,
                o.due_date,
                o.due_time,
                COALESCE(t.time_slot_id, o.time_slot_id) AS time_slot_id,
                ts.name AS time_slot_name,
                ts.sort_order AS time_slot_sort,
                t.assigned_user_id,
                au.display_name AS assignee_name,
                pr.name AS priority_name,
                pr.color_hex AS priority_color
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id
             LEFT JOIN tenant_time_slots ts ON ts.id = COALESCE(t.time_slot_id, o.time_slot_id) AND ts.tenant_id = o.tenant_id
             LEFT JOIN tenant_priorities pr ON pr.id = t.priority_id AND pr.tenant_id = t.tenant_id
             LEFT JOIN users au ON au.id = t.assigned_user_id AND au.tenant_id = t.tenant_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NULL
               AND t.deleted_at IS NULL
               AND (
                    o.due_date = :due_date_exact
                    OR (t.is_infinity = 1 AND o.due_date <= :due_date_upto)
               )
             ORDER BY COALESCE(ts.sort_order, 999) ASC, o.due_date ASC, o.id ASC'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'due_date_exact' => $date,
            'due_date_upto' => $date,
        ]);

        json_response(['ok' => true, 'tasks' => $stmt->fetchAll()]);
    }

    public function completeTask(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $occurrenceId = (int) ($input['occurrence_id'] ?? 0);
        if ($occurrenceId < 1) {
            json_response(['ok' => false, 'message' => 'Invalid task occurrence.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $userId = (int) $_SESSION['user_id'];

        $chk = $this->pdo->prepare(
            'SELECT o.id FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id AND t.tenant_id = o.tenant_id
             WHERE o.id = :id AND o.tenant_id = :tenant_id AND o.completed_at IS NULL
               AND t.deleted_at IS NULL'
        );
        $chk->execute(['id' => $occurrenceId, 'tenant_id' => $tenantId]);
        if (!$chk->fetch()) {
            json_response(['ok' => false, 'message' => 'Task not found or no longer available.'], 404);
            return;
        }

        $completedAt = TenantTime::nowMysqlInTenantTimezone($this->pdo, $tenantId);
        $stmt = $this->pdo->prepare(
            'UPDATE task_occurrences
             SET completed_at = :completed_at, completed_by_user_id = :user_id
             WHERE id = :id AND tenant_id = :tenant_id AND completed_at IS NULL'
        );
        $stmt->execute([
            'completed_at' => $completedAt,
            'user_id' => $userId,
            'id' => $occurrenceId,
            'tenant_id' => $tenantId,
        ]);

        AuditLogger::log($this->pdo, $tenantId, $userId, 'tasks.complete', ['occurrence_id' => $occurrenceId]);
        json_response(['ok' => true]);
    }

    /** Clear completion for today’s occurrence (completer or manager+). */
    public function uncompleteTask(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $occurrenceId = (int) ($input['occurrence_id'] ?? 0);
        if ($occurrenceId < 1) {
            json_response(['ok' => false, 'message' => 'Invalid task occurrence.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $actorId = (int) $_SESSION['user_id'];
        $role = (string) ($_SESSION['role'] ?? '');
        $managerish = in_array($role, ['owner', 'super_admin', 'tenant_admin', 'tenant_manager'], true);
        $today = TenantTime::todayYmd($this->pdo, $tenantId);

        $row = $this->pdo->prepare(
            'SELECT o.completed_by_user_id, o.due_date, o.completed_at
             FROM task_occurrences o
             WHERE o.id = :id AND o.tenant_id = :tenant_id
             LIMIT 1'
        );
        $row->execute(['id' => $occurrenceId, 'tenant_id' => $tenantId]);
        $occ = $row->fetch();
        if (!$occ) {
            json_response(['ok' => false, 'message' => 'Task not found.'], 404);
            return;
        }
        if ($occ['completed_at'] === null) {
            json_response(['ok' => false, 'message' => 'This task is not completed.'], 422);
            return;
        }
        if ((string) $occ['due_date'] !== $today) {
            json_response(['ok' => false, 'message' => 'Only completions for today can be undone from here.'], 422);
            return;
        }

        $completedBy = $occ['completed_by_user_id'] !== null ? (int) $occ['completed_by_user_id'] : 0;
        if (!$managerish && $completedBy !== $actorId) {
            json_response(['ok' => false, 'message' => 'You can only undo tasks you completed.'], 403);
            return;
        }

        $upd = $this->pdo->prepare(
            'UPDATE task_occurrences
             SET completed_at = NULL, completed_by_user_id = NULL
             WHERE id = :id AND tenant_id = :tenant_id AND due_date = :due_date AND completed_at IS NOT NULL'
        );
        $upd->execute(['id' => $occurrenceId, 'tenant_id' => $tenantId, 'due_date' => $today]);

        AuditLogger::log($this->pdo, $tenantId, $actorId, 'tasks.uncomplete', ['occurrence_id' => $occurrenceId]);
        json_response(['ok' => true]);
    }

    public function completedToday(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $date = TenantTime::todayYmd($this->pdo, $tenantId);

        $stmt = $this->pdo->prepare(
            'SELECT o.id, t.title, o.completed_at, u.display_name AS completed_by
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id
             LEFT JOIN users u ON u.id = o.completed_by_user_id
             WHERE o.tenant_id = :tenant_id AND o.due_date = :due_date AND o.completed_at IS NOT NULL
               AND t.deleted_at IS NULL
             ORDER BY o.completed_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'due_date' => $date]);

        $tasks = array_map(function (array $r) use ($tenantId): array {
            $ca = $r['completed_at'] !== null && $r['completed_at'] !== '' ? (string) $r['completed_at'] : '';

            return [
                'id' => (int) $r['id'],
                'title' => (string) $r['title'],
                'completed_at' => $ca !== ''
                    ? (TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, $tenantId, $ca) ?? $ca)
                    : null,
                'completed_by' => $r['completed_by'] !== null ? (string) $r['completed_by'] : '',
            ];
        }, $stmt->fetchAll());

        json_response(['ok' => true, 'tasks' => $tasks]);
    }

    /** Active NOAA/NWS weather alerts for the tenant US ZIP (cached server-side ~5 minutes). */
    public function weatherAlerts(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $svc = new WeatherAlertsService();
        $out = $svc->getAlertsForTenant($this->pdo, $tenantId);
        json_response($out);
    }

    /** Current conditions near tenant US ZIP via NWS (cached server-side ~10 minutes). */
    public function weatherCurrent(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $svc = new WeatherAlertsService();
        $out = $svc->getCurrentConditionsForTenant($this->pdo, $tenantId);
        json_response($out);
    }

    public function logout(bool $companyToo): void
    {
        ArrowChatBridge::clearBridgeCookie();
        if ($companyToo) {
            session_destroy();
            json_response(['ok' => true]);
            return;
        }

        $_SESSION['user_id'] = null;
        $_SESSION['role'] = null;
        $_SESSION['display_name'] = null;
        json_response(['ok' => true]);
    }

    public function me(): void
    {
        $homeTenantId = Auth::homeTenantId();
        $companyAuthenticated = !empty($_SESSION['company_authenticated']) && $homeTenantId > 0;
        if (!$companyAuthenticated) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $impersonateId = isset($_SESSION['impersonate_tenant_id']) ? (int) $_SESSION['impersonate_tenant_id'] : null;
        $effectiveId = Auth::effectiveTenantId();

        $themeTenantId = !empty($_SESSION['user_id']) ? $effectiveId : $homeTenantId;
        $tstmt = $this->pdo->prepare('SELECT theme_default FROM tenants WHERE id = :id LIMIT 1');
        $tstmt->execute(['id' => $themeTenantId]);
        $trow = $tstmt->fetch();
        $tenantThemeDefault = ($trow && ($trow['theme_default'] === 'dark' || $trow['theme_default'] === 'light'))
            ? (string) $trow['theme_default']
            : 'light';

        $userThemeOverride = null;
        if (!empty($_SESSION['user_id'])) {
            $ustmt = $this->pdo->prepare(
                'SELECT user_theme_override FROM users WHERE id = :id AND tenant_id = :tid LIMIT 1'
            );
            $ustmt->execute(['id' => (int) $_SESSION['user_id'], 'tid' => $homeTenantId]);
            $urow = $ustmt->fetch();
            if ($urow && ($urow['user_theme_override'] === 'dark' || $urow['user_theme_override'] === 'light')) {
                $userThemeOverride = (string) $urow['user_theme_override'];
            }
        }

        $sessionUi = $_SESSION['ui_theme'] ?? null;
        if ($sessionUi !== 'light' && $sessionUi !== 'dark') {
            $sessionUi = null;
        }

        $resolvedTheme = $userThemeOverride ?? $sessionUi ?? $tenantThemeDefault;

        $capTenantId = !empty($_SESSION['user_id']) ? Auth::effectiveTenantId() : $homeTenantId;
        $integrations = $this->resolveIntegrationsForTenant($capTenantId);

        $brandStmt = $this->pdo->prepare('SELECT name, logo_url, timezone FROM tenants WHERE id = :id LIMIT 1');
        $brandStmt->execute(['id' => $capTenantId]);
        $brandRow = $brandStmt->fetch();
        $tenantBrand = [
            'name' => $brandRow ? (string) $brandRow['name'] : '',
            'logo_url' => $brandRow && $brandRow['logo_url'] !== null && $brandRow['logo_url'] !== ''
                ? (string) $brandRow['logo_url']
                : null,
        ];
        $companyTimezone = TenantTime::validateTimezoneIdentifier(
            $brandRow && isset($brandRow['timezone']) ? (string) $brandRow['timezone'] : 'UTC'
        );
        $systemMessages = $this->fetchActiveSystemMessages($capTenantId);

        json_response([
            'ok' => true,
            'company_authenticated' => true,
            'company_timezone' => $companyTimezone,
            'home_tenant_id' => $homeTenantId,
            'effective_tenant_id' => $effectiveId,
            'impersonate_tenant_id' => $impersonateId && $impersonateId > 0 ? $impersonateId : null,
            'can_impersonate' => Auth::canImpersonate(),
            'can_view_reports' => Auth::canViewReports(),
            'tenant_id' => $homeTenantId,
            'tenant_brand' => $tenantBrand,
            'tenant_theme_default' => $tenantThemeDefault,
            'user_theme_override' => $userThemeOverride,
            'resolved_theme' => $resolvedTheme,
            'integrations' => $integrations,
            'limited_mode' => $this->isWriteBlockedForTenant($capTenantId),
            'maintenance_mode_active' => $this->isTenantMaintenanceActive($capTenantId),
            'system_messages' => $systemMessages,
            'user' => !empty($_SESSION['user_id']) ? [
                'id' => (int) $_SESSION['user_id'],
                'role' => (string) ($_SESSION['role'] ?? ''),
                'display_name' => (string) ($_SESSION['display_name'] ?? ''),
                'tenant_id' => $homeTenantId,
                'can_edit_tasks' => $this->isTaskEditor(),
                'can_delete_tasks' => $this->isTaskDeleter(),
            ] : null,
        ]);
    }

    /** Completed-task stats for tenants with the tasks.reports plan feature. */
    public function reportsSummary(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $info = $this->resolveIntegrationsForTenant($tenantId);
        if (empty($info['features']['tasks_reports'])) {
            json_response(['ok' => false, 'message' => 'Reports are not enabled for your subscription plan.'], 403);
            return;
        }
        if (!Auth::canViewReports()) {
            json_response(['ok' => false, 'message' => 'Reports are only available to managers and above.'], 403);
            return;
        }

        $startIn = isset($_GET['start_date']) ? trim((string) $_GET['start_date']) : '';
        $endIn = isset($_GET['end_date']) ? trim((string) $_GET['end_date']) : '';
        if ($startIn === '' || $endIn === '') {
            $tz = TenantTime::timezoneObject($this->pdo, $tenantId);
            $end = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
            $start = (new \DateTimeImmutable('now', $tz))->modify('-30 days')->format('Y-m-d');
        } else {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endIn)) {
                json_response(['ok' => false, 'message' => 'Use start_date and end_date as YYYY-MM-DD.'], 422);
                return;
            }
            $start = $startIn;
            $end = $endIn;
        }
        if (strcmp($start, $end) > 0) {
            json_response(['ok' => false, 'message' => 'Start date must be on or before end date.'], 422);
            return;
        }
        $startTs = strtotime($start . ' 00:00:00');
        $endTs = strtotime($end . ' 23:59:59');
        if ($startTs === false || $endTs === false) {
            json_response(['ok' => false, 'message' => 'Invalid date range.'], 422);
            return;
        }
        if (($endTs - $startTs) > 366 * 86400) {
            json_response(['ok' => false, 'message' => 'Date range cannot exceed 366 days.'], 422);
            return;
        }

        $byDay = $this->pdo->prepare(
            'SELECT DATE(completed_at) AS d, COUNT(*) AS c
             FROM task_occurrences
             WHERE tenant_id = :tenant_id
               AND completed_at IS NOT NULL
               AND DATE(completed_at) >= :start_d
               AND DATE(completed_at) <= :end_d
             GROUP BY DATE(completed_at)
             ORDER BY d ASC'
        );
        $byDay->execute(['tenant_id' => $tenantId, 'start_d' => $start, 'end_d' => $end]);
        $byDayRows = $byDay->fetchAll();

        $byUser = $this->pdo->prepare(
            'SELECT COALESCE(u.display_name, \'Unknown\') AS display_name, COUNT(*) AS c
             FROM task_occurrences o
             LEFT JOIN users u ON u.id = o.completed_by_user_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NOT NULL
               AND DATE(o.completed_at) >= :start_d
               AND DATE(o.completed_at) <= :end_d
             GROUP BY o.completed_by_user_id, u.display_name
             ORDER BY c DESC
             LIMIT 40'
        );
        $byUser->execute(['tenant_id' => $tenantId, 'start_d' => $start, 'end_d' => $end]);

        $detailStmt = $this->pdo->prepare(
            'SELECT o.id AS occurrence_id, t.title, t.details, o.completed_at,
                    COALESCE(u.display_name, \'Unknown\') AS completed_by
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id AND t.tenant_id = o.tenant_id
             LEFT JOIN users u ON u.id = o.completed_by_user_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NOT NULL
               AND DATE(o.completed_at) >= :start_d
               AND DATE(o.completed_at) <= :end_d
             ORDER BY o.completed_at DESC
             LIMIT 250'
        );
        $detailStmt->execute(['tenant_id' => $tenantId, 'start_d' => $start, 'end_d' => $end]);
        $detailRows = $detailStmt->fetchAll();

        $totalDone = $this->pdo->prepare(
            'SELECT COUNT(*) FROM task_occurrences
             WHERE tenant_id = :tenant_id AND completed_at IS NOT NULL
             AND DATE(completed_at) >= :start_d AND DATE(completed_at) <= :end_d'
        );
        $totalDone->execute(['tenant_id' => $tenantId, 'start_d' => $start, 'end_d' => $end]);

        $today = TenantTime::todayYmd($this->pdo, $tenantId);
        $openToday = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM task_occurrences o
             INNER JOIN tasks t ON t.id = o.task_id
             WHERE o.tenant_id = :tenant_id
               AND o.completed_at IS NULL
               AND t.deleted_at IS NULL
               AND (
                    o.due_date = :today
                    OR (t.is_infinity = 1 AND o.due_date <= :today2)
               )'
        );
        $openToday->execute(['tenant_id' => $tenantId, 'today' => $today, 'today2' => $today]);

        json_response([
            'ok' => true,
            'start_date' => $start,
            'end_date' => $end,
            'completed_total' => (int) $totalDone->fetchColumn(),
            'open_today' => (int) $openToday->fetchColumn(),
            'completed_by_day' => array_map(static fn ($r) => ['date' => $r['d'], 'count' => (int) $r['c']], $byDayRows),
            'completed_by_user' => array_map(
                static fn ($r) => ['display_name' => $r['display_name'], 'count' => (int) $r['c']],
                $byUser->fetchAll()
            ),
            'completed_tasks_detail' => array_map(function ($r) use ($tenantId) {
                $ca = $r['completed_at'] !== null && $r['completed_at'] !== '' ? (string) $r['completed_at'] : '';

                return [
                    'occurrence_id' => (int) $r['occurrence_id'],
                    'title' => (string) $r['title'],
                    'details' => $r['details'] !== null && $r['details'] !== '' ? (string) $r['details'] : null,
                    'completed_at' => $ca !== ''
                        ? (TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, $tenantId, $ca) ?? $ca)
                        : '',
                    'completed_by' => (string) $r['completed_by'],
                ];
            }, $detailRows),
        ]);
    }

    /** Persist light/dark: logged-in users store override; company-only session uses $_SESSION. */
    public function themePreference(): void
    {
        $homeTenantId = Auth::homeTenantId();
        $companyOk = !empty($_SESSION['company_authenticated']) && $homeTenantId > 0;
        if (!$companyOk) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $input = $this->input();
        $theme = (string) ($input['theme'] ?? '');
        if (!in_array($theme, ['light', 'dark'], true)) {
            json_response(['ok' => false, 'message' => 'Invalid theme.'], 422);
            return;
        }

        if (Auth::requireUser()) {
            $this->pdo->prepare(
                'UPDATE users SET user_theme_override = :t WHERE id = :id AND tenant_id = :tid'
            )->execute(['t' => $theme, 'id' => (int) $_SESSION['user_id'], 'tid' => $homeTenantId]);
        } else {
            $_SESSION['ui_theme'] = $theme;
        }

        json_response(['ok' => true, 'resolved_theme' => $theme]);
    }

    /** Public self-service: new company + first tenant admin (optional env ALLOW_PUBLIC_SIGNUP=false to disable). */
    public function registerTenant(): void
    {
        if (!filter_var((string) \env('ALLOW_PUBLIC_SIGNUP', 'true'), FILTER_VALIDATE_BOOL)) {
            json_response(['ok' => false, 'message' => 'New sign-ups are disabled.'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'Unavailable right now.'], 503);
            return;
        }

        $input = $this->input();
        $result = $this->provisionTenantAccount(
            trim((string) ($input['company_name'] ?? '')),
            strtolower(trim((string) ($input['company_username'] ?? ''))),
            (string) ($input['company_password'] ?? ''),
            trim((string) ($input['owner_email'] ?? '')),
            trim((string) ($input['admin_display_name'] ?? '')),
            (string) ($input['admin_pin'] ?? ''),
            trim((string) ($input['timezone'] ?? '')) ?: 'UTC'
        );

        if (!$result['ok']) {
            json_response(['ok' => false, 'message' => $result['message'] ?? 'Registration failed.'], 422);
            return;
        }

        AuditLogger::log($this->pdo, (int) $result['tenant_id'], null, 'auth.tenant_register', [
            'company_username' => strtolower(trim((string) ($input['company_username'] ?? ''))),
        ]);
        json_response(['ok' => true, 'tenant_id' => (int) $result['tenant_id']]);
    }

    /** Owner / super_admin: create another tenant (same provisioning as public register). */
    public function createTenantAdmin(): void
    {
        if (!Auth::requireUser() || !Auth::canImpersonate()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $result = $this->provisionTenantAccount(
            trim((string) ($input['company_name'] ?? '')),
            strtolower(trim((string) ($input['company_username'] ?? ''))),
            (string) ($input['company_password'] ?? ''),
            trim((string) ($input['owner_email'] ?? '')),
            trim((string) ($input['admin_display_name'] ?? '')),
            (string) ($input['admin_pin'] ?? ''),
            trim((string) ($input['timezone'] ?? '')) ?: 'UTC'
        );

        if (!$result['ok']) {
            json_response(['ok' => false, 'message' => $result['message'] ?? 'Could not create company.'], 422);
            return;
        }

        AuditLogger::log($this->pdo, Auth::homeTenantId(), (int) $_SESSION['user_id'], 'admin.tenant.create', [
            'new_tenant_id' => (int) $result['tenant_id'],
        ]);
        json_response(['ok' => true, 'tenant_id' => (int) $result['tenant_id']]);
    }

    public function adminConfig(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $role = (string) ($_SESSION['role'] ?? '');
        $tenantId = Auth::effectiveTenantId();

        $tenantStmt = $this->pdo->prepare(
            'SELECT id, name, company_username, owner_email, timezone, zip_code, theme_default, plan_key, is_active, logo_url
             FROM tenants WHERE id = :id LIMIT 1'
        );
        $tenantStmt->execute(['id' => $tenantId]);
        $tenant = $tenantStmt->fetch();

        $levels = $this->pdo->query(
            'SELECT id, level_key, name, is_paid, monthly_price, show_ads, is_active
             FROM subscription_levels
             ORDER BY id ASC'
        )->fetchAll();
        $features = $this->pdo->query(
            'SELECT id, feature_key, name, description
             FROM feature_flags
             ORDER BY id ASC'
        )->fetchAll();
        $mapRows = $this->pdo->query(
            'SELECT level_id, feature_id, is_enabled
             FROM subscription_level_features'
        )->fetchAll();

        $map = [];
        foreach ($mapRows as $row) {
            $map[$row['level_id'] . ':' . $row['feature_id']] = (int) $row['is_enabled'];
        }

        $integrations = $this->resolveIntegrationsForTenant($tenantId);
        $managerish = in_array($role, ['owner', 'super_admin', 'tenant_admin', 'tenant_manager'], true);

        json_response([
            'ok' => true,
            'role' => $role,
            'tenant' => $tenant,
            'home_tenant_id' => Auth::homeTenantId(),
            'effective_tenant_id' => $tenantId,
            'impersonate_tenant_id' => isset($_SESSION['impersonate_tenant_id']) ? (int) $_SESSION['impersonate_tenant_id'] : null,
            'levels' => $levels,
            'features' => $features,
            'matrix' => $map,
            'can_manage_matrix' => in_array($role, ['owner', 'super_admin'], true),
            'can_manage_tenant' => in_array($role, ['owner', 'super_admin', 'tenant_admin'], true),
            'can_manage_time_slots' => in_array($role, ['owner', 'super_admin', 'tenant_admin'], true),
            'can_manage_priorities' => in_array($role, ['owner', 'super_admin', 'tenant_admin'], true),
            'can_manage_system_messages' => in_array($role, ['owner', 'super_admin', 'tenant_admin'], true),
            'can_manage_plans' => in_array($role, ['owner', 'super_admin'], true),
            'can_manage_platform_tenant' => in_array($role, ['owner', 'super_admin'], true),
            'can_view_audit_logs' => in_array($role, ['owner', 'super_admin', 'tenant_admin'], true),
            'can_manage_training' => $managerish && !empty($integrations['features']['training_employee']),
            'can_impersonate' => Auth::canImpersonate(),
            'integrations' => $integrations,
        ]);
    }

    public function updateTenantTheme(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, ['owner', 'super_admin', 'tenant_admin'], true)) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $theme = (string) ($input['theme_default'] ?? '');
        if (!in_array($theme, ['light', 'dark'], true)) {
            json_response(['ok' => false, 'message' => 'Invalid theme value.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $stmt = $this->pdo->prepare('UPDATE tenants SET theme_default = :theme WHERE id = :id');
        $stmt->execute(['theme' => $theme, 'id' => $tenantId]);

        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.tenant_theme.update', ['theme_default' => $theme]);
        json_response(['ok' => true]);
    }

    public function listSystemMessagesAdmin(): void
    {
        if (!Auth::requireUser() || !$this->canManageSystemMessages()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        $role = (string) ($_SESSION['role'] ?? '');
        $isGlobalAdmin = in_array($role, ['owner', 'super_admin'], true);
        $stmt = $this->pdo->prepare(
            'SELECT id, message_text, is_maintenance, is_active, created_at, scope
             FROM system_messages
             WHERE (scope = \'tenant\' AND tenant_id = :tenant_id)
                OR (:global_admin = 1 AND scope = \'global\')
             ORDER BY is_active DESC, is_maintenance DESC, created_at DESC, id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'global_admin' => $isGlobalAdmin ? 1 : 0]);
        json_response(['ok' => true, 'messages' => $stmt->fetchAll()]);
    }

    public function createSystemMessageAdmin(): void
    {
        if (!Auth::requireUser() || !$this->canManageSystemMessages()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        $input = $this->input();
        $text = trim((string) ($input['message_text'] ?? ''));
        $isMaintenance = !empty($input['is_maintenance']) ? 1 : 0;
        $role = (string) ($_SESSION['role'] ?? '');
        $isGlobalAdmin = in_array($role, ['owner', 'super_admin'], true);
        $scopeIn = trim((string) ($input['scope'] ?? 'tenant'));
        if (!in_array($scopeIn, ['tenant', 'global'], true)) {
            $scopeIn = 'tenant';
        }
        if ($isMaintenance === 1) {
            if (!$isGlobalAdmin) {
                json_response(['ok' => false, 'message' => 'Only owner/super admin can set maintenance mode.'], 403);
                return;
            }
            $scopeIn = 'global';
        } elseif ($scopeIn === 'global' && !$isGlobalAdmin) {
            json_response(['ok' => false, 'message' => 'Only owner/super admin can create global messages.'], 403);
            return;
        }
        if ($text === '') {
            json_response(['ok' => false, 'message' => 'Message text is required.'], 422);
            return;
        }
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($len > 2000) {
            json_response(['ok' => false, 'message' => 'Message is too long (max 2000 characters).'], 422);
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO system_messages (tenant_id, scope, message_text, is_maintenance, is_active, created_by_user_id)
             VALUES (:tenant_id, :scope, :message_text, :is_maintenance, 1, :user_id)'
        );
        $stmt->execute([
            'tenant_id' => $scopeIn === 'global' ? null : $tenantId,
            'scope' => $scopeIn,
            'message_text' => $text,
            'is_maintenance' => $isMaintenance,
            'user_id' => (int) $_SESSION['user_id'],
        ]);
        $id = (int) $this->pdo->lastInsertId();
        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.system_message.create', [
            'message_id' => $id,
            'is_maintenance' => $isMaintenance,
            'scope' => $scopeIn,
        ]);
        json_response(['ok' => true, 'id' => $id]);
    }

    public function deleteSystemMessageAdmin(): void
    {
        if (!Auth::requireUser() || !$this->canManageSystemMessages()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        $role = (string) ($_SESSION['role'] ?? '');
        $isGlobalAdmin = in_array($role, ['owner', 'super_admin'], true);
        $id = (int) (($this->input()['id'] ?? 0));
        if ($id < 1) {
            json_response(['ok' => false, 'message' => 'Invalid message id.'], 422);
            return;
        }
        $stmt = $this->pdo->prepare(
            'DELETE FROM system_messages
             WHERE id = :id
               AND (
                    (scope = \'tenant\' AND tenant_id = :tenant_id)
                    OR (:global_admin = 1 AND scope = \'global\')
               )'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId, 'global_admin' => $isGlobalAdmin ? 1 : 0]);
        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.system_message.delete', [
            'message_id' => $id,
        ]);
        json_response(['ok' => true]);
    }

    /** Update company profile for the effective tenant (owner/super_admin may also set plan, login id, active flag). */
    public function updateTenantProfile(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, ['owner', 'super_admin', 'tenant_admin'], true)) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $platform = in_array($role, ['owner', 'super_admin'], true);
        $tenantId = Auth::effectiveTenantId();
        $input = $this->input();

        if (array_key_exists('name', $input)) {
            $n = trim((string) $input['name']);
            $nlen = function_exists('mb_strlen') ? mb_strlen($n) : strlen($n);
            if ($n === '' || $nlen > 120) {
                json_response(['ok' => false, 'message' => 'Company name is required (max 120 characters).'], 422);
                return;
            }
            $this->pdo->prepare('UPDATE tenants SET name = :n WHERE id = :id')->execute(['n' => $n, 'id' => $tenantId]);
        }

        if (array_key_exists('owner_email', $input)) {
            $e = trim((string) $input['owner_email']);
            if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
                json_response(['ok' => false, 'message' => 'Valid owner email is required.'], 422);
                return;
            }
            $this->pdo->prepare('UPDATE tenants SET owner_email = :e WHERE id = :id')->execute(['e' => $e, 'id' => $tenantId]);
        }

        if (array_key_exists('timezone', $input)) {
            $tz = trim((string) $input['timezone']);
            if (!TenantTime::isValidTimezone($tz)) {
                json_response(['ok' => false, 'message' => 'Invalid timezone.'], 422);
                return;
            }
            $this->pdo->prepare('UPDATE tenants SET timezone = :tz WHERE id = :id')->execute(['tz' => $tz, 'id' => $tenantId]);
            TenantTime::clearZoneCache();
        }

        if (array_key_exists('zip_code', $input)) {
            $z = trim((string) $input['zip_code']);
            if ($z === '') {
                $this->pdo->prepare('UPDATE tenants SET zip_code = NULL WHERE id = :id')->execute(['id' => $tenantId]);
                WeatherAlertsService::invalidateCache($tenantId);
            } else {
                if (!WeatherAlertsService::isValidUsZipInput($z)) {
                    json_response(['ok' => false, 'message' => 'ZIP must be 5 digits or ZIP+4 (e.g. 12345 or 12345-6789).'], 422);
                    return;
                }
                $norm = preg_replace('/\s+/', '', $z) ?? '';
                $this->pdo->prepare('UPDATE tenants SET zip_code = :z WHERE id = :id')->execute(['z' => $norm, 'id' => $tenantId]);
                WeatherAlertsService::invalidateCache($tenantId);
            }
        }

        if (array_key_exists('theme_default', $input)) {
            $th = (string) $input['theme_default'];
            if (!in_array($th, ['light', 'dark'], true)) {
                json_response(['ok' => false, 'message' => 'Invalid theme.'], 422);
                return;
            }
            $this->pdo->prepare('UPDATE tenants SET theme_default = :t WHERE id = :id')->execute(['t' => $th, 'id' => $tenantId]);
        }

        if (array_key_exists('logo_url', $input)) {
            $lu = trim((string) $input['logo_url']);
            if ($lu === '') {
                $this->pdo->prepare('UPDATE tenants SET logo_url = NULL WHERE id = :id')->execute(['id' => $tenantId]);
            } else {
                if (strlen($lu) > 255 || !filter_var($lu, FILTER_VALIDATE_URL)) {
                    json_response(['ok' => false, 'message' => 'Logo must be a valid URL (max 255 characters).'], 422);
                    return;
                }
                if (!preg_match('#^https?://#i', $lu)) {
                    json_response(['ok' => false, 'message' => 'Logo URL must start with http:// or https://'], 422);
                    return;
                }
                $this->pdo->prepare('UPDATE tenants SET logo_url = :u WHERE id = :id')->execute(['u' => $lu, 'id' => $tenantId]);
            }
        }

        if (array_key_exists('company_password', $input)) {
            $pw = (string) $input['company_password'];
            if ($pw !== '') {
                if (strlen($pw) < 8) {
                    json_response(['ok' => false, 'message' => 'Company password must be at least 8 characters.'], 422);
                    return;
                }
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $this->pdo->prepare('UPDATE tenants SET password_hash = :h WHERE id = :id')->execute(['h' => $hash, 'id' => $tenantId]);
            }
        }

        if ($platform) {
            if (array_key_exists('plan_key', $input)) {
                $pk = trim((string) $input['plan_key']);
                $chk = $this->pdo->prepare(
                    'SELECT level_key FROM subscription_levels WHERE level_key = :k AND is_active = 1 LIMIT 1'
                );
                $chk->execute(['k' => $pk]);
                if (!$chk->fetch()) {
                    json_response(['ok' => false, 'message' => 'Unknown or inactive plan.'], 422);
                    return;
                }
                $this->pdo->prepare('UPDATE tenants SET plan_key = :p WHERE id = :id')->execute(['p' => $pk, 'id' => $tenantId]);
            }

            if (array_key_exists('is_active', $input)) {
                $act = !empty($input['is_active']) ? 1 : 0;
                $this->pdo->prepare('UPDATE tenants SET is_active = :a WHERE id = :id')->execute(['a' => $act, 'id' => $tenantId]);
            }

            if (array_key_exists('company_username', $input)) {
                $cu = strtolower(trim((string) $input['company_username']));
                if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,78}$/', $cu)) {
                    json_response(['ok' => false, 'message' => 'Invalid company login format.'], 422);
                    return;
                }
                $dup = $this->pdo->prepare('SELECT id FROM tenants WHERE company_username = :u AND id != :id LIMIT 1');
                $dup->execute(['u' => $cu, 'id' => $tenantId]);
                if ($dup->fetch()) {
                    json_response(['ok' => false, 'message' => 'That company login is already in use.'], 422);
                    return;
                }
                $this->pdo->prepare('UPDATE tenants SET company_username = :u WHERE id = :id')->execute(['u' => $cu, 'id' => $tenantId]);
            }
        }

        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.tenant_profile.update', array_keys($input));
        json_response(['ok' => true]);
    }

    public function updateFeatureMatrix(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, ['owner', 'super_admin'], true)) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $entries = $input['entries'] ?? null;
        if (!is_array($entries)) {
            json_response(['ok' => false, 'message' => 'Invalid matrix payload.'], 422);
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO subscription_level_features (level_id, feature_id, is_enabled)
                 VALUES (:level_id, :feature_id, :is_enabled)
                 ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)'
            );

            foreach ($entries as $entry) {
                $levelId = (int) ($entry['level_id'] ?? 0);
                $featureId = (int) ($entry['feature_id'] ?? 0);
                $enabled = (int) (!empty($entry['is_enabled']));
                if ($levelId < 1 || $featureId < 1) {
                    continue;
                }
                $stmt->execute([
                    'level_id' => $levelId,
                    'feature_id' => $featureId,
                    'is_enabled' => $enabled,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            json_response(['ok' => false, 'message' => 'Unable to save matrix: ' . $e->getMessage()], 500);
            return;
        }

        AuditLogger::log($this->pdo, Auth::homeTenantId(), (int) $_SESSION['user_id'], 'admin.feature_matrix.update', [
            'entries_count' => count($entries),
        ]);
        json_response(['ok' => true]);
    }

    public function listTenants(): void
    {
        if (!Auth::requireUser() || !Auth::canImpersonate()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $stmt = $this->pdo->query(
            'SELECT id, name, company_username, is_demo, is_active
             FROM tenants
             ORDER BY name ASC'
        );
        json_response(['ok' => true, 'tenants' => $stmt->fetchAll()]);
    }

    public function impersonateTenant(): void
    {
        if (!Auth::requireUser() || !Auth::canImpersonate()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $targetId = (int) ($input['tenant_id'] ?? 0);
        if ($targetId < 1) {
            json_response(['ok' => false, 'message' => 'Invalid tenant.'], 422);
            return;
        }

        $check = $this->pdo->prepare('SELECT id FROM tenants WHERE id = :id AND is_active = 1 LIMIT 1');
        $check->execute(['id' => $targetId]);
        if (!$check->fetch()) {
            json_response(['ok' => false, 'message' => 'Tenant not found.'], 404);
            return;
        }

        Auth::setImpersonateTenant($targetId);
        AuditLogger::log($this->pdo, Auth::homeTenantId(), (int) $_SESSION['user_id'], 'admin.impersonate.start', ['target_tenant_id' => $targetId]);
        json_response(['ok' => true, 'effective_tenant_id' => Auth::effectiveTenantId()]);
    }

    public function clearImpersonate(): void
    {
        if (!Auth::requireUser() || !Auth::canImpersonate()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $prev = isset($_SESSION['impersonate_tenant_id']) ? (int) $_SESSION['impersonate_tenant_id'] : null;
        Auth::setImpersonateTenant(null);
        AuditLogger::log($this->pdo, Auth::homeTenantId(), (int) $_SESSION['user_id'], 'admin.impersonate.clear', ['previous_tenant_id' => $prev]);
        json_response(['ok' => true, 'effective_tenant_id' => Auth::effectiveTenantId()]);
    }

    public function tenantUsersForAdmin(): void
    {
        if (!Auth::requireUser() || !$this->isTaskEditor()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $stmt = $this->pdo->prepare(
            'SELECT id, display_name, role, is_active
             FROM users
             WHERE tenant_id = :tenant_id AND is_active = 1
             ORDER BY display_name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        json_response(['ok' => true, 'users' => $stmt->fetchAll()]);
    }

    public function adminTasksList(): void
    {
        if (!Auth::requireUser() || !$this->isTaskEditor()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.title, t.details, t.priority_key, t.priority_id, t.recurrence_type, t.recurrence_weekday,
                    t.recurrence_weekdays, t.recurrence_anchor_date,
                    t.recurrence_day_of_month, t.time_slot_id, ts.name AS time_slot_name,
                    pr.name AS priority_name, pr.color_hex AS priority_color,
                    t.task_time, t.is_infinity, t.is_active, t.assigned_user_id
             FROM tasks t
             LEFT JOIN tenant_time_slots ts ON ts.id = t.time_slot_id AND ts.tenant_id = t.tenant_id
             LEFT JOIN tenant_priorities pr ON pr.id = t.priority_id AND pr.tenant_id = t.tenant_id
             WHERE t.tenant_id = :tenant_id AND t.deleted_at IS NULL
             ORDER BY t.is_active DESC, t.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        json_response(['ok' => true, 'tasks' => $stmt->fetchAll()]);
    }

    public function adminTaskCreate(): void
    {
        if (!Auth::requireUser() || !$this->isTaskEditor()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $row = $this->normalizeTaskInput($this->input(), true, $tenantId);
        if (isset($row['error'])) {
            json_response(['ok' => false, 'message' => $row['error']], 422);
            return;
        }
        $uid = (int) $_SESSION['user_id'];

        $stmt = $this->pdo->prepare(
            'INSERT INTO tasks (
                tenant_id, title, details, assigned_user_id, priority_id, priority_key,
                recurrence_type, recurrence_weekday, recurrence_weekdays, recurrence_anchor_date, recurrence_day_of_month,
                time_slot_id, task_time, is_infinity, is_active, created_by_user_id
            ) VALUES (
                :tenant_id, :title, :details, :assigned_user_id, :priority_id, :priority_key,
                :recurrence_type, :recurrence_weekday, :recurrence_weekdays, :recurrence_anchor_date, :recurrence_day_of_month,
                :time_slot_id, NULL, :is_infinity, :is_active, :created_by_user_id
            )'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'title' => $row['title'],
            'details' => $row['details'],
            'assigned_user_id' => $row['assigned_user_id'],
            'priority_id' => $row['priority_id'],
            'priority_key' => $row['priority_key'],
            'recurrence_type' => $row['recurrence_type'],
            'recurrence_weekday' => $row['recurrence_weekday'],
            'recurrence_weekdays' => $row['recurrence_weekdays'],
            'recurrence_anchor_date' => $row['recurrence_anchor_date'],
            'recurrence_day_of_month' => $row['recurrence_day_of_month'],
            'time_slot_id' => $row['time_slot_id'],
            'is_infinity' => $row['is_infinity'],
            'is_active' => $row['is_active'],
            'created_by_user_id' => $uid,
        ]);
        $taskId = (int) $this->pdo->lastInsertId();
        $this->syncTodayOccurrence($tenantId, $taskId);

        AuditLogger::log($this->pdo, $tenantId, $uid, 'tasks.create', ['task_id' => $taskId]);
        json_response(['ok' => true, 'task_id' => $taskId]);
    }

    public function adminTaskUpdate(): void
    {
        if (!Auth::requireUser() || !$this->isTaskEditor()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $taskId = (int) ($input['id'] ?? 0);
        if ($taskId < 1) {
            json_response(['ok' => false, 'message' => 'Invalid task id.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $own = $this->pdo->prepare(
            'SELECT id FROM tasks WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1'
        );
        $own->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        if (!$own->fetch()) {
            json_response(['ok' => false, 'message' => 'Task not found.'], 404);
            return;
        }

        $row = $this->normalizeTaskInput($input, false, $tenantId);
        if (isset($row['error'])) {
            json_response(['ok' => false, 'message' => $row['error']], 422);
            return;
        }

        $uid = (int) $_SESSION['user_id'];
        $stmt = $this->pdo->prepare(
            'UPDATE tasks SET
                title = :title,
                details = :details,
                assigned_user_id = :assigned_user_id,
                priority_id = :priority_id,
                priority_key = :priority_key,
                recurrence_type = :recurrence_type,
                recurrence_weekday = :recurrence_weekday,
                recurrence_weekdays = :recurrence_weekdays,
                recurrence_anchor_date = :recurrence_anchor_date,
                recurrence_day_of_month = :recurrence_day_of_month,
                time_slot_id = :time_slot_id,
                task_time = NULL,
                is_infinity = :is_infinity,
                is_active = :is_active
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $taskId,
            'tenant_id' => $tenantId,
            'title' => $row['title'],
            'details' => $row['details'],
            'assigned_user_id' => $row['assigned_user_id'],
            'priority_id' => $row['priority_id'],
            'priority_key' => $row['priority_key'],
            'recurrence_type' => $row['recurrence_type'],
            'recurrence_weekday' => $row['recurrence_weekday'],
            'recurrence_weekdays' => $row['recurrence_weekdays'],
            'recurrence_anchor_date' => $row['recurrence_anchor_date'],
            'recurrence_day_of_month' => $row['recurrence_day_of_month'],
            'time_slot_id' => $row['time_slot_id'],
            'is_infinity' => $row['is_infinity'],
            'is_active' => $row['is_active'],
        ]);
        $this->syncTodayOccurrence($tenantId, $taskId);

        AuditLogger::log($this->pdo, $tenantId, $uid, 'tasks.update', ['task_id' => $taskId]);
        json_response(['ok' => true]);
    }

    /** Soft-delete: task hidden from board and admin list; occurrences kept for reporting. */
    public function adminTaskDelete(): void
    {
        if (!Auth::requireUser() || !$this->isTaskDeleter()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $taskId = (int) ($input['id'] ?? 0);
        if ($taskId < 1) {
            json_response(['ok' => false, 'message' => 'Invalid task id.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $chk = $this->pdo->prepare(
            'SELECT id FROM tasks WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1'
        );
        $chk->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        if (!$chk->fetch()) {
            json_response(['ok' => false, 'message' => 'Task not found.'], 404);
            return;
        }

        $uid = (int) $_SESSION['user_id'];
        $this->pdo->prepare(
            'UPDATE tasks SET deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL'
        )->execute(['id' => $taskId, 'tenant_id' => $tenantId]);

        AuditLogger::log($this->pdo, $tenantId, $uid, 'tasks.soft_delete', ['task_id' => $taskId]);
        json_response(['ok' => true]);
    }

    private function isTaskEditor(): bool
    {
        $r = (string) ($_SESSION['role'] ?? '');

        return in_array($r, ['owner', 'super_admin', 'tenant_admin', 'tenant_manager', 'tenant_task'], true);
    }

    /** Soft-delete tasks (Admin UI); not granted to tenant_task. */
    private function isTaskDeleter(): bool
    {
        $r = (string) ($_SESSION['role'] ?? '');

        return in_array($r, ['owner', 'super_admin', 'tenant_admin', 'tenant_manager'], true);
    }

    private function canManageSystemMessages(): bool
    {
        $r = (string) ($_SESSION['role'] ?? '');

        return in_array($r, ['owner', 'super_admin', 'tenant_admin'], true);
    }

    private function canBypassTenantMaintenanceWriteLock(): bool
    {
        $r = (string) ($_SESSION['role'] ?? '');

        return in_array($r, ['owner', 'super_admin'], true);
    }

    private function isTenantMaintenanceActive(int $tenantId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id
                 FROM system_messages
                 WHERE scope = \'global\' AND is_active = 1 AND is_maintenance = 1
                 LIMIT 1'
            );
            $stmt->execute();

            return (bool) $stmt->fetch();
        } catch (\Throwable) {
            return false;
        }
    }

    private function isWriteBlockedForTenant(int $tenantId): bool
    {
        if ($this->limitedMode) {
            return true;
        }
        if ($this->canBypassTenantMaintenanceWriteLock()) {
            return false;
        }

        return $this->isTenantMaintenanceActive($tenantId);
    }

    /** @return list<array{id:int,message_text:string,is_maintenance:int,created_at:string,scope:string}> */
    private function fetchActiveSystemMessages(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, message_text, is_maintenance, created_at, scope
                 FROM system_messages
                 WHERE is_active = 1
                   AND (
                        (scope = \'tenant\' AND tenant_id = :tenant_id AND is_maintenance = 0)
                        OR (scope = \'global\')
                   )
                 ORDER BY is_maintenance DESC, created_at DESC, id DESC'
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            return array_map(static fn ($r) => [
                'id' => (int) $r['id'],
                'message_text' => (string) $r['message_text'],
                'is_maintenance' => (int) $r['is_maintenance'],
                'created_at' => (string) $r['created_at'],
                'scope' => (string) $r['scope'],
            ], $stmt->fetchAll());
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|array{error:string}
     */
    private function normalizeTaskInput(array $input, bool $isCreate, int $tenantId): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['error' => 'Title is required.'];
        }

        $rec = (string) ($input['recurrence_type'] ?? 'none');
        if (!in_array($rec, ['none', 'daily', 'weekly', 'biweekly', 'monthly', 'quarterly'], true)) {
            return ['error' => 'Invalid recurrence type.'];
        }

        $weekdaysParsed = null;
        $rawWd = $input['recurrence_weekdays'] ?? null;
        if (is_array($rawWd)) {
            $weekdaysParsed = array_map(static fn ($x) => (int) $x, $rawWd);
        } elseif (is_string($rawWd) && trim($rawWd) !== '') {
            $weekdaysParsed = array_map(
                static fn ($x) => (int) trim($x),
                array_filter(explode(',', $rawWd), static fn ($x) => trim($x) !== '')
            );
        }
        if (is_array($weekdaysParsed)) {
            $weekdaysParsed = array_values(array_unique(array_filter(
                $weekdaysParsed,
                static function ($d): bool {
                    $n = (int) $d;

                    return $n >= 0 && $n <= 6;
                }
            )));
            sort($weekdaysParsed);
            if ($weekdaysParsed === []) {
                $weekdaysParsed = null;
            }
        }

        $legacyWeekday = $input['recurrence_weekday'] ?? null;
        $legacyWeekday = $legacyWeekday === null || $legacyWeekday === '' ? null : (int) $legacyWeekday;

        if ($rec === 'weekly' || $rec === 'biweekly') {
            if ($weekdaysParsed === null || $weekdaysParsed === []) {
                if ($legacyWeekday !== null && $legacyWeekday >= 0 && $legacyWeekday <= 6) {
                    $weekdaysParsed = [$legacyWeekday];
                }
            }
            if ($weekdaysParsed === null || $weekdaysParsed === []) {
                return ['error' => 'Select at least one day of the week.'];
            }
        } elseif ($rec === 'monthly' || $rec === 'quarterly') {
            if ($legacyWeekday !== null && $legacyWeekday >= 0 && $legacyWeekday <= 6) {
                $weekdaysParsed = [$legacyWeekday];
            } elseif ($weekdaysParsed !== null && $weekdaysParsed !== []) {
                $weekdaysParsed = [reset($weekdaysParsed)];
            } else {
                $weekdaysParsed = null;
            }
        } else {
            $weekdaysParsed = null;
        }

        $weekdaysCsv = ($weekdaysParsed !== null && $weekdaysParsed !== [])
            ? implode(',', $weekdaysParsed)
            : null;

        $recurrenceWeekdayOut = null;
        if (
            ($rec === 'weekly' || $rec === 'biweekly' || $rec === 'monthly' || $rec === 'quarterly')
            && $weekdaysParsed !== null
            && count($weekdaysParsed) === 1
        ) {
            $recurrenceWeekdayOut = $weekdaysParsed[0];
        }

        $anchorOut = null;
        if ($rec === 'biweekly' || $rec === 'quarterly') {
            $anchor = trim((string) ($input['recurrence_anchor_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
                if ($rec === 'quarterly') {
                    return ['error' => 'Quarterly tasks need an anchor date (YYYY-MM-DD) to set the quarter cycle.'];
                }

                return ['error' => 'Bi-weekly tasks need an anchor date (YYYY-MM-DD) for the starting week.'];
            }
            if (strtotime($anchor . ' 00:00:00') === false) {
                return ['error' => 'Invalid anchor date.'];
            }
            $anchorOut = $anchor;
        }

        $dom = $input['recurrence_day_of_month'] ?? null;
        $dom = $dom === null || $dom === '' ? null : (int) $dom;
        if (($rec === 'monthly' || $rec === 'quarterly') && $recurrenceWeekdayOut === null && ($dom === null || $dom < 1 || $dom > 31)) {
            if ($rec === 'quarterly') {
                return ['error' => 'Quarterly tasks need day of month (1-31) or weekday.'];
            }

            return ['error' => 'Monthly tasks need day of month (1-31) or weekday.'];
        }
        if ($rec !== 'monthly' && $rec !== 'quarterly') {
            $dom = null;
        }

        $slotId = (int) ($input['time_slot_id'] ?? 0);
        if ($slotId < 1) {
            return ['error' => 'Select a time of day (tenant slot).'];
        }

        $slotCheck = $this->pdo->prepare(
            'SELECT id FROM tenant_time_slots WHERE id = :id AND tenant_id = :tid LIMIT 1'
        );
        $slotCheck->execute(['id' => $slotId, 'tid' => $tenantId]);
        if (!$slotCheck->fetch()) {
            return ['error' => 'Invalid time slot for this tenant.'];
        }

        $priId = (int) ($input['priority_id'] ?? 0);
        if ($priId < 1) {
            return ['error' => 'Select a priority.'];
        }
        $priSql = 'SELECT name FROM tenant_priorities WHERE id = :id AND tenant_id = :tid';
        if ($isCreate) {
            $priSql .= ' AND is_active = 1';
        }
        $priSql .= ' LIMIT 1';
        $priCheck = $this->pdo->prepare($priSql);
        $priCheck->execute(['id' => $priId, 'tid' => $tenantId]);
        $pRow = $priCheck->fetch();
        if (!$pRow) {
            return ['error' => 'Invalid priority for this tenant.'];
        }
        $priKey = $this->priorityKeySlug((string) $pRow['name']);

        $assigned = $input['assigned_user_id'] ?? null;
        $assigned = $assigned === null || $assigned === '' ? null : (int) $assigned;

        $detailsRaw = trim((string) ($input['details'] ?? ''));
        $isActive = array_key_exists('is_active', $input)
            ? (!empty($input['is_active']) ? 1 : 0)
            : ($isCreate ? 1 : 1);

        return [
            'title' => $title,
            'details' => $detailsRaw === '' ? null : $detailsRaw,
            'priority_id' => $priId,
            'priority_key' => $priKey,
            'recurrence_type' => $rec,
            'recurrence_weekday' => $recurrenceWeekdayOut,
            'recurrence_weekdays' => $weekdaysCsv,
            'recurrence_anchor_date' => $anchorOut,
            'recurrence_day_of_month' => $dom,
            'time_slot_id' => $slotId,
            'is_infinity' => !empty($input['is_infinity']) ? 1 : 0,
            'is_active' => $isActive,
            'assigned_user_id' => $assigned,
        ];
    }

    /** @return list<int> */
    private function parseTaskWeekdaysList(array $task): array
    {
        $list = [];
        if (!empty($task['recurrence_weekdays']) && is_string($task['recurrence_weekdays'])) {
            foreach (explode(',', $task['recurrence_weekdays']) as $p) {
                $d = (int) trim($p);
                if ($d >= 0 && $d <= 6) {
                    $list[] = $d;
                }
            }
            $list = array_values(array_unique($list));
            sort($list);
        }
        if ($list === [] && isset($task['recurrence_weekday']) && $task['recurrence_weekday'] !== null && $task['recurrence_weekday'] !== '') {
            $d = (int) $task['recurrence_weekday'];
            if ($d >= 0 && $d <= 6) {
                $list = [$d];
            }
        }

        return $list;
    }

    private function isBiweeklyWeekActive(string $todayYmd, string $anchorYmd): bool
    {
        $t = strtotime($todayYmd . ' 00:00:00');
        $a = strtotime($anchorYmd . ' 00:00:00');
        if ($t === false || $a === false) {
            return false;
        }
        if ($t < $a) {
            return false;
        }
        $diffDays = (int) floor(($t - $a) / 86400);
        $weekIndex = (int) floor($diffDays / 7);

        return ($weekIndex % 2) === 0;
    }

    private function isQuarterlyMonthActive(string $todayYmd, string $anchorYmd): bool
    {
        $t = strtotime($todayYmd . ' 00:00:00');
        $a = strtotime($anchorYmd . ' 00:00:00');
        if ($t === false || $a === false) {
            return false;
        }
        if ($t < $a) {
            return false;
        }

        $tYear = (int) date('Y', $t);
        $tMonth = (int) date('n', $t);
        $aYear = (int) date('Y', $a);
        $aMonth = (int) date('n', $a);
        $monthDiff = (($tYear - $aYear) * 12) + ($tMonth - $aMonth);

        return ($monthDiff % 3) === 0;
    }

    private function firstDayOfWeekInMonth(int $year, int $month, int $weekday): int
    {
        $firstDow = (int) date('w', strtotime(sprintf('%04d-%02d-01 00:00:00', $year, $month)));

        return 1 + (($weekday - $firstDow + 7) % 7);
    }

    /**
     * Materialize or repair today's rows so the board query (which joins task_occurrences) does not
     * drop tasks that should appear today. Saving a task in admin already calls syncTodayOccurrence;
     * this covers gaps after slot deletion, migrations, or missed syncs.
     */
    private function ensureTodayBoardOccurrences(int $tenantId, string $today): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT t.id
             FROM tasks t
             WHERE t.tenant_id = :tenant_id
               AND t.deleted_at IS NULL
               AND t.is_active = 1
               AND (
                 (t.is_infinity = 0 AND NOT EXISTS (
                    SELECT 1 FROM task_occurrences o
                    WHERE o.task_id = t.id AND o.due_date = :today_missing
                  ))
                 OR EXISTS (
                    SELECT 1 FROM task_occurrences o
                    WHERE o.task_id = t.id
                      AND o.due_date = :today_slot
                      AND o.completed_at IS NULL
                      AND (
                        o.time_slot_id IS NULL
                        OR (t.time_slot_id IS NOT NULL AND o.time_slot_id <> t.time_slot_id)
                      )
                  )
               )'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'today_missing' => $today,
            'today_slot' => $today,
        ]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $taskId) {
            $this->syncTodayOccurrence($tenantId, (int) $taskId);
        }
    }

    private function syncTodayOccurrence(int $tenantId, int $taskId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT recurrence_type, recurrence_weekday, recurrence_weekdays, recurrence_anchor_date,
                    recurrence_day_of_month, time_slot_id, is_active
             FROM tasks WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        $task = $stmt->fetch();
        if (!$task || !(int) $task['is_active']) {
            return;
        }

        $slotId = $task['time_slot_id'] !== null ? (int) $task['time_slot_id'] : null;
        if ($slotId === null || $slotId < 1) {
            $fallback = $this->pdo->prepare(
                'SELECT id FROM tenant_time_slots
                 WHERE tenant_id = :tid AND is_active = 1
                 ORDER BY sort_order ASC, id ASC LIMIT 1'
            );
            $fallback->execute(['tid' => $tenantId]);
            $row = $fallback->fetch();
            if (!$row) {
                return;
            }
            $slotId = (int) $row['id'];
            $this->pdo->prepare('UPDATE tasks SET time_slot_id = :sid WHERE id = :id')->execute([
                'sid' => $slotId,
                'id' => $taskId,
            ]);
        }

        $cal = TenantTime::calendarPartsNow($this->pdo, $tenantId);
        $today = $cal['today'];
        $dow = $cal['dow'];
        $dom = $cal['dom'];
        $last = $cal['last'];
        $should = false;
        $type = (string) $task['recurrence_type'];
        $weekdaysList = $this->parseTaskWeekdaysList($task);

        if ($type === 'none' || $type === 'daily') {
            $should = true;
        } elseif ($type === 'weekly') {
            $should = $weekdaysList !== [] && in_array($dow, $weekdaysList, true);
        } elseif ($type === 'biweekly') {
            $anchor = $task['recurrence_anchor_date'] ?? null;
            if ($anchor !== null && $anchor !== '' && $weekdaysList !== []) {
                $should = in_array($dow, $weekdaysList, true)
                    && $this->isBiweeklyWeekActive($today, (string) $anchor);
            }
        } elseif ($type === 'monthly') {
            if ($task['recurrence_weekday'] !== null && $task['recurrence_weekday'] !== '') {
                $targetDow = (int) $task['recurrence_weekday'];
                if ($targetDow >= 0 && $targetDow <= 6) {
                    $year = (int) substr($today, 0, 4);
                    $month = (int) substr($today, 5, 2);
                    $day = $this->firstDayOfWeekInMonth($year, $month, $targetDow);
                    $should = ($dom === $day);
                }
            } else {
                $target = (int) $task['recurrence_day_of_month'];
                $day = min($target, $last);
                $should = ($dom === $day);
            }
        } elseif ($type === 'quarterly') {
            $anchor = $task['recurrence_anchor_date'] ?? null;
            if ($anchor !== null && $anchor !== '' && $this->isQuarterlyMonthActive($today, (string) $anchor)) {
                if ($task['recurrence_weekday'] !== null && $task['recurrence_weekday'] !== '') {
                    $targetDow = (int) $task['recurrence_weekday'];
                    if ($targetDow >= 0 && $targetDow <= 6) {
                        $year = (int) substr($today, 0, 4);
                        $month = (int) substr($today, 5, 2);
                        $day = $this->firstDayOfWeekInMonth($year, $month, $targetDow);
                        $should = ($dom === $day);
                    }
                } else {
                    $target = (int) $task['recurrence_day_of_month'];
                    $day = min($target, $last);
                    $should = ($dom === $day);
                }
            }
        }

        if (!$should) {
            return;
        }

        $dueTime = '00:00:00';

        $exists = $this->pdo->prepare(
            'SELECT id FROM task_occurrences WHERE task_id = :task_id AND due_date = :due_date LIMIT 1'
        );
        $exists->execute(['task_id' => $taskId, 'due_date' => $today]);
        if ($exists->fetch()) {
            $upd = $this->pdo->prepare(
                'UPDATE task_occurrences
                 SET due_time = :tm, time_slot_id = :slot_id, tenant_id = :tenant_id
                 WHERE task_id = :task_id AND due_date = :due_date AND completed_at IS NULL'
            );
            $upd->execute([
                'tm' => $dueTime,
                'slot_id' => $slotId,
                'tenant_id' => $tenantId,
                'task_id' => $taskId,
                'due_date' => $today,
            ]);
            return;
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO task_occurrences (tenant_id, task_id, due_date, due_time, time_slot_id)
             VALUES (:tenant_id, :task_id, :due_date, :due_time, :slot_id)'
        );
        $ins->execute([
            'tenant_id' => $tenantId,
            'task_id' => $taskId,
            'due_date' => $today,
            'due_time' => $dueTime,
            'slot_id' => $slotId,
        ]);
    }

    public function listTimeSlotsAdmin(): void
    {
        if (!Auth::requireUser() || !$this->isTaskEditor()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $stmt = $this->pdo->prepare(
            'SELECT id, name, sort_order, is_active
             FROM tenant_time_slots
             WHERE tenant_id = :tenant_id
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        json_response(['ok' => true, 'slots' => $stmt->fetchAll()]);
    }

    public function createTimeSlot(): void
    {
        if (!Auth::requireUser() || !$this->isTenantSlotManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            json_response(['ok' => false, 'message' => 'Name is required.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $max = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM tenant_time_slots WHERE tenant_id = :tid');
        $max->execute(['tid' => $tenantId]);
        $next = (int) $max->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_time_slots (tenant_id, name, sort_order, is_active)
             VALUES (:tenant_id, :name, :sort_order, 1)'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'sort_order' => $next]);

        $id = (int) $this->pdo->lastInsertId();
        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.time_slot.create', ['slot_id' => $id]);
        json_response(['ok' => true, 'id' => $id]);
    }

    public function updateTimeSlot(): void
    {
        if (!Auth::requireUser() || !$this->isTenantSlotManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            json_response(['ok' => false, 'message' => 'Invalid slot.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $own = $this->pdo->prepare('SELECT id FROM tenant_time_slots WHERE id = :id AND tenant_id = :tid LIMIT 1');
        $own->execute(['id' => $id, 'tid' => $tenantId]);
        if (!$own->fetch()) {
            json_response(['ok' => false, 'message' => 'Slot not found.'], 404);
            return;
        }

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                json_response(['ok' => false, 'message' => 'Name cannot be empty.'], 422);
                return;
            }
            $this->pdo->prepare('UPDATE tenant_time_slots SET name = :n WHERE id = :id')->execute(['n' => $name, 'id' => $id]);
        }
        if (array_key_exists('sort_order', $input)) {
            $this->pdo->prepare('UPDATE tenant_time_slots SET sort_order = :s WHERE id = :id')->execute([
                's' => (int) $input['sort_order'],
                'id' => $id,
            ]);
        }
        if (array_key_exists('is_active', $input)) {
            $this->pdo->prepare('UPDATE tenant_time_slots SET is_active = :a WHERE id = :id')->execute([
                'a' => !empty($input['is_active']) ? 1 : 0,
                'id' => $id,
            ]);
        }

        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.time_slot.update', ['slot_id' => $id]);
        json_response(['ok' => true]);
    }

    public function listTenantPrioritiesAdmin(): void
    {
        if (!Auth::requireUser() || !$this->isTaskEditor()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $stmt = $this->pdo->prepare(
            'SELECT id, name, color_hex, sort_order, is_active
             FROM tenant_priorities
             WHERE tenant_id = :tenant_id
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        json_response(['ok' => true, 'priorities' => $stmt->fetchAll()]);
    }

    public function createTenantPriority(): void
    {
        if (!Auth::requireUser() || !$this->isTenantSlotManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            json_response(['ok' => false, 'message' => 'Name is required.'], 422);
            return;
        }
        $hex = trim((string) ($input['color_hex'] ?? '#64748b'));
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            json_response(['ok' => false, 'message' => 'Color must be a hex value like #RRGGBB.'], 422);
            return;
        }
        $hex = strtoupper($hex);

        $tenantId = Auth::effectiveTenantId();
        $max = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM tenant_priorities WHERE tenant_id = :tid');
        $max->execute(['tid' => $tenantId]);
        $next = (int) $max->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_priorities (tenant_id, name, color_hex, sort_order, is_active)
             VALUES (:tenant_id, :name, :color_hex, :sort_order, 1)'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'color_hex' => $hex, 'sort_order' => $next]);

        $id = (int) $this->pdo->lastInsertId();
        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.priority.create', ['priority_id' => $id]);
        json_response(['ok' => true, 'id' => $id]);
    }

    public function updateTenantPriority(): void
    {
        if (!Auth::requireUser() || !$this->isTenantSlotManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            json_response(['ok' => false, 'message' => 'Invalid priority.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $own = $this->pdo->prepare('SELECT id FROM tenant_priorities WHERE id = :id AND tenant_id = :tid LIMIT 1');
        $own->execute(['id' => $id, 'tid' => $tenantId]);
        if (!$own->fetch()) {
            json_response(['ok' => false, 'message' => 'Priority not found.'], 404);
            return;
        }

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                json_response(['ok' => false, 'message' => 'Name cannot be empty.'], 422);
                return;
            }
            $this->pdo->prepare('UPDATE tenant_priorities SET name = :n WHERE id = :id')->execute(['n' => $name, 'id' => $id]);
        }
        if (array_key_exists('color_hex', $input)) {
            $hex = trim((string) $input['color_hex']);
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                json_response(['ok' => false, 'message' => 'Color must be #RRGGBB.'], 422);
                return;
            }
            $hex = strtoupper($hex);
            $this->pdo->prepare('UPDATE tenant_priorities SET color_hex = :c WHERE id = :id')->execute(['c' => $hex, 'id' => $id]);
        }
        if (array_key_exists('sort_order', $input)) {
            $this->pdo->prepare('UPDATE tenant_priorities SET sort_order = :s WHERE id = :id')->execute([
                's' => (int) $input['sort_order'],
                'id' => $id,
            ]);
        }
        if (array_key_exists('is_active', $input)) {
            $this->pdo->prepare('UPDATE tenant_priorities SET is_active = :a WHERE id = :id')->execute([
                'a' => !empty($input['is_active']) ? 1 : 0,
                'id' => $id,
            ]);
        }

        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.priority.update', ['priority_id' => $id]);
        json_response(['ok' => true]);
    }

    public function createSubscriptionLevel(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, ['owner', 'super_admin'], true)) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $levelKey = strtolower(trim((string) ($input['level_key'] ?? '')));
        if (!preg_match('/^[a-z0-9][a-z0-9_]{1,78}$/', $levelKey)) {
            json_response(['ok' => false, 'message' => 'Plan key: 2–79 chars, lowercase letters, numbers, underscore.'], 422);
            return;
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 120) {
            json_response(['ok' => false, 'message' => 'Plan name is required (max 120 characters).'], 422);
            return;
        }
        $isPaid = !empty($input['is_paid']) ? 1 : 0;
        $monthly = $input['monthly_price'] ?? null;
        $monthly = $monthly === null || $monthly === '' ? null : round((float) $monthly, 2);
        $showAds = array_key_exists('show_ads', $input) ? (!empty($input['show_ads']) ? 1 : 0) : 1;

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO subscription_levels (level_key, name, is_paid, monthly_price, show_ads, is_active)
                 VALUES (:k, :n, :p, :m, :a, 1)'
            );
            $ins->execute([
                'k' => $levelKey,
                'n' => $name,
                'p' => $isPaid,
                'm' => $monthly,
                'a' => $showAds,
            ]);
            $levelId = (int) $this->pdo->lastInsertId();

            $features = $this->pdo->query('SELECT id FROM feature_flags')->fetchAll(PDO::FETCH_COLUMN);
            $fins = $this->pdo->prepare(
                'INSERT INTO subscription_level_features (level_id, feature_id, is_enabled) VALUES (:lid, :fid, 0)'
            );
            foreach ($features as $fid) {
                $fins->execute(['lid' => $levelId, 'fid' => (int) $fid]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            json_response(['ok' => false, 'message' => 'Could not create plan (duplicate key?).'], 422);
            return;
        }

        AuditLogger::log($this->pdo, Auth::homeTenantId(), (int) $_SESSION['user_id'], 'admin.plan.create', ['level_id' => $levelId]);
        json_response(['ok' => true, 'id' => $levelId]);
    }

    public function updateSubscriptionLevel(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, ['owner', 'super_admin'], true)) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            json_response(['ok' => false, 'message' => 'Invalid plan.'], 422);
            return;
        }

        $own = $this->pdo->prepare('SELECT id FROM subscription_levels WHERE id = :id LIMIT 1');
        $own->execute(['id' => $id]);
        if (!$own->fetch()) {
            json_response(['ok' => false, 'message' => 'Plan not found.'], 404);
            return;
        }

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                json_response(['ok' => false, 'message' => 'Name cannot be empty.'], 422);
                return;
            }
            $this->pdo->prepare('UPDATE subscription_levels SET name = :n WHERE id = :id')->execute(['n' => $name, 'id' => $id]);
        }
        if (array_key_exists('is_paid', $input)) {
            $this->pdo->prepare('UPDATE subscription_levels SET is_paid = :p WHERE id = :id')->execute([
                'p' => !empty($input['is_paid']) ? 1 : 0,
                'id' => $id,
            ]);
        }
        if (array_key_exists('monthly_price', $input)) {
            $monthly = $input['monthly_price'];
            $monthly = $monthly === null || $monthly === '' ? null : round((float) $monthly, 2);
            $this->pdo->prepare('UPDATE subscription_levels SET monthly_price = :m WHERE id = :id')->execute(['m' => $monthly, 'id' => $id]);
        }
        if (array_key_exists('show_ads', $input)) {
            $this->pdo->prepare('UPDATE subscription_levels SET show_ads = :a WHERE id = :id')->execute([
                'a' => !empty($input['show_ads']) ? 1 : 0,
                'id' => $id,
            ]);
        }
        if (array_key_exists('is_active', $input)) {
            $this->pdo->prepare('UPDATE subscription_levels SET is_active = :a WHERE id = :id')->execute([
                'a' => !empty($input['is_active']) ? 1 : 0,
                'id' => $id,
            ]);
        }

        AuditLogger::log($this->pdo, Auth::homeTenantId(), (int) $_SESSION['user_id'], 'admin.plan.update', ['level_id' => $id]);
        json_response(['ok' => true]);
    }

    public function listUsersAdmin(): void
    {
        if (!Auth::requireUser() || !$this->isUserManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $stmt = $this->pdo->prepare(
            'SELECT id, display_name, email, role, is_active, last_login_at, created_at
             FROM users
             WHERE tenant_id = :tenant_id
             ORDER BY is_active DESC, display_name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        json_response(['ok' => true, 'users' => $stmt->fetchAll()]);
    }

    public function createUserAdmin(): void
    {
        if (!Auth::requireUser() || !$this->isUserManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $displayName = trim((string) ($input['display_name'] ?? ''));
        $role = (string) ($input['role'] ?? '');
        $pin = (string) ($input['pin'] ?? '');
        $email = trim((string) ($input['email'] ?? ''));

        if ($displayName === '' || !preg_match('/^\d{6}$/', $pin)) {
            json_response(['ok' => false, 'message' => 'Display name and 6-digit PIN are required.'], 422);
            return;
        }

        $actor = (string) ($_SESSION['role'] ?? '');
        if (!$this->roleAllowedForCreate($actor, $role)) {
            json_response(['ok' => false, 'message' => 'You cannot assign that role.'], 403);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $pinHash = password_hash($pin, PASSWORD_DEFAULT);
        $lookup = Auth::pinLookupHash($pin);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (tenant_id, display_name, email, role, pin_hash, pin_lookup_hash, is_active)
                 VALUES (:tenant_id, :display_name, :email, :role, :pin_hash, :lookup, 1)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'display_name' => $displayName,
                'email' => $email === '' ? null : $email,
                'role' => $role,
                'pin_hash' => $pinHash,
                'lookup' => $lookup,
            ]);
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'message' => 'Could not create user (duplicate PIN in this company?).'], 422);
            return;
        }

        $newId = (int) $this->pdo->lastInsertId();
        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.user.create', ['user_id' => $newId]);
        json_response(['ok' => true, 'user_id' => $newId]);
    }

    public function updateUserAdmin(): void
    {
        if (!Auth::requireUser() || !$this->isUserManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $userId = (int) ($input['id'] ?? 0);
        if ($userId < 1) {
            json_response(['ok' => false, 'message' => 'Invalid user.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $stmt = $this->pdo->prepare(
            'SELECT id, role FROM users WHERE id = :id AND tenant_id = :tid LIMIT 1'
        );
        $stmt->execute(['id' => $userId, 'tid' => $tenantId]);
        $target = $stmt->fetch();
        if (!$target) {
            json_response(['ok' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $actor = (string) ($_SESSION['role'] ?? '');
        $actorId = (int) $_SESSION['user_id'];

        if (array_key_exists('role', $input)) {
            $newRole = (string) $input['role'];
            if (!$this->roleAllowedForAssign($actor, (string) $target['role'], $newRole)) {
                json_response(['ok' => false, 'message' => 'You cannot set that role.'], 403);
                return;
            }
            if ($userId === $actorId && $newRole !== $target['role']) {
                json_response(['ok' => false, 'message' => 'You cannot change your own role here.'], 403);
                return;
            }
            $this->pdo->prepare('UPDATE users SET role = :r WHERE id = :id')->execute(['r' => $newRole, 'id' => $userId]);
        }

        if (array_key_exists('display_name', $input)) {
            $dn = trim((string) $input['display_name']);
            if ($dn === '') {
                json_response(['ok' => false, 'message' => 'Display name cannot be empty.'], 422);
                return;
            }
            $this->pdo->prepare('UPDATE users SET display_name = :d WHERE id = :id')->execute(['d' => $dn, 'id' => $userId]);
        }

        if (array_key_exists('email', $input)) {
            $em = trim((string) $input['email']);
            $this->pdo->prepare('UPDATE users SET email = :e WHERE id = :id')->execute([
                'e' => $em === '' ? null : $em,
                'id' => $userId,
            ]);
        }

        if (array_key_exists('is_active', $input)) {
            $active = !empty($input['is_active']) ? 1 : 0;
            if ($active === 0 && $userId === $actorId) {
                json_response(['ok' => false, 'message' => 'You cannot disable your own account.'], 403);
                return;
            }
            if ($active === 0 && (string) $target['role'] === 'tenant_admin') {
                $cnt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM users WHERE tenant_id = :tid AND role = :r AND is_active = 1'
                );
                $cnt->execute(['tid' => $tenantId, 'r' => 'tenant_admin']);
                if ((int) $cnt->fetchColumn() <= 1) {
                    json_response(['ok' => false, 'message' => 'Cannot disable the last tenant admin.'], 403);
                    return;
                }
            }
            $this->pdo->prepare('UPDATE users SET is_active = :a WHERE id = :id')->execute(['a' => $active, 'id' => $userId]);
        }

        AuditLogger::log($this->pdo, $tenantId, $actorId, 'admin.user.update', ['user_id' => $userId]);
        json_response(['ok' => true]);
    }

    public function setUserPinAdmin(): void
    {
        if (!Auth::requireUser() || !$this->isUserManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $input = $this->input();
        $userId = (int) ($input['id'] ?? 0);
        $pin = (string) ($input['pin'] ?? '');
        if ($userId < 1 || !preg_match('/^\d{6}$/', $pin)) {
            json_response(['ok' => false, 'message' => 'Valid user and 6-digit PIN required.'], 422);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = :id AND tenant_id = :tid LIMIT 1');
        $stmt->execute(['id' => $userId, 'tid' => $tenantId]);
        if (!$stmt->fetch()) {
            json_response(['ok' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $pinHash = password_hash($pin, PASSWORD_DEFAULT);
        $lookup = Auth::pinLookupHash($pin);

        try {
            $upd = $this->pdo->prepare(
                'UPDATE users SET pin_hash = :ph, pin_lookup_hash = :lk, failed_pin_attempts = 0, pin_locked_until = NULL
                 WHERE id = :id AND tenant_id = :tid'
            );
            $upd->execute(['ph' => $pinHash, 'lk' => $lookup, 'id' => $userId, 'tid' => $tenantId]);
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'message' => 'PIN already used by another user in this company.'], 422);
            return;
        }

        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'admin.user.pin_reset', ['user_id' => $userId]);
        json_response(['ok' => true]);
    }

    private function isTenantSlotManager(): bool
    {
        return in_array((string) ($_SESSION['role'] ?? ''), ['owner', 'super_admin', 'tenant_admin'], true);
    }

    private function isUserManager(): bool
    {
        return in_array((string) ($_SESSION['role'] ?? ''), [
            'owner', 'super_admin', 'tenant_admin', 'tenant_manager',
        ], true);
    }

    private function roleAllowedForCreate(string $actor, string $newRole): bool
    {
        $allowed = ['tenant_user', 'tenant_task', 'tenant_manager', 'tenant_admin', 'owner', 'super_admin'];
        if (!in_array($newRole, $allowed, true)) {
            return false;
        }

        if (in_array($actor, ['owner', 'super_admin'], true)) {
            return true;
        }
        if ($actor === 'tenant_admin') {
            return in_array($newRole, ['tenant_user', 'tenant_task', 'tenant_manager'], true);
        }
        if ($actor === 'tenant_manager') {
            return in_array($newRole, ['tenant_user', 'tenant_task'], true);
        }

        return false;
    }

    private function roleAllowedForAssign(string $actor, string $oldRole, string $newRole): bool
    {
        return $this->roleAllowedForCreate($actor, $newRole);
    }

    /**
     * @return array{ok: true, tenant_id: int}|array{ok: false, message: string}
     */
    private function provisionTenantAccount(
        string $companyName,
        string $companyUsername,
        string $companyPassword,
        string $ownerEmail,
        string $adminDisplayName,
        string $adminPin,
        string $timezone
    ): array {
        $nameLen = function_exists('mb_strlen') ? mb_strlen($companyName) : strlen($companyName);
        if ($companyName === '' || $nameLen > 120) {
            return ['ok' => false, 'message' => 'Company name is required (max 120 characters).'];
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,78}$/', $companyUsername)) {
            return ['ok' => false, 'message' => 'Company login: 2–79 characters, letters, numbers, underscore or hyphen, starting with a letter or number.'];
        }
        if (strlen($companyPassword) < 8) {
            return ['ok' => false, 'message' => 'Company password must be at least 8 characters.'];
        }
        if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'A valid owner email is required.'];
        }
        if ($adminDisplayName === '') {
            return ['ok' => false, 'message' => 'Admin display name is required.'];
        }
        if (!preg_match('/^\d{6}$/', $adminPin)) {
            return ['ok' => false, 'message' => 'Admin PIN must be exactly 6 digits.'];
        }
        if (!TenantTime::isValidTimezone($timezone)) {
            return ['ok' => false, 'message' => 'Invalid timezone.'];
        }

        $dup = $this->pdo->prepare('SELECT id FROM tenants WHERE company_username = :u LIMIT 1');
        $dup->execute(['u' => $companyUsername]);
        if ($dup->fetch()) {
            return ['ok' => false, 'message' => 'That company login is already taken.'];
        }

        $hash = password_hash($companyPassword, PASSWORD_DEFAULT);
        $pinHash = password_hash($adminPin, PASSWORD_DEFAULT);
        $pinLookup = Auth::pinLookupHash($adminPin);

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO tenants (
                    name, company_username, password_hash, owner_email, timezone,
                    theme_default, trial_started_at, trial_ends_at, plan_key, is_demo, is_active
                ) VALUES (
                    :name, :cu, :ph, :oe, :tz,
                    \'light\', NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), \'basic_ads\', 0, 1
                )'
            );
            $ins->execute([
                'name' => $companyName,
                'cu' => $companyUsername,
                'ph' => $hash,
                'oe' => $ownerEmail,
                'tz' => $timezone,
            ]);
            $tenantId = (int) $this->pdo->lastInsertId();
            if ($tenantId < 1) {
                throw new \RuntimeException('Tenant insert failed.');
            }

            $this->seedDefaultTimeSlots($tenantId);
            $this->seedDefaultPriorities($tenantId);

            $uins = $this->pdo->prepare(
                'INSERT INTO users (tenant_id, display_name, email, role, pin_hash, pin_lookup_hash, is_active)
                 VALUES (:tid, :dn, :em, \'tenant_admin\', :ph, :lk, 1)'
            );
            $uins->execute([
                'tid' => $tenantId,
                'dn' => $adminDisplayName,
                'em' => $ownerEmail,
                'ph' => $pinHash,
                'lk' => $pinLookup,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['ok' => false, 'message' => 'Could not create company. Try a different login name.'];
        }

        return ['ok' => true, 'tenant_id' => $tenantId];
    }

    private function seedDefaultTimeSlots(int $tenantId): void
    {
        $rows = [
            ['Morning', 0],
            ['Afternoon', 1],
            ['Evening', 2],
        ];
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_time_slots (tenant_id, name, sort_order, is_active) VALUES (:tid, :n, :o, 1)'
        );
        foreach ($rows as [$name, $ord]) {
            $stmt->execute(['tid' => $tenantId, 'n' => $name, 'o' => $ord]);
        }
    }

    private function seedDefaultPriorities(int $tenantId): void
    {
        $rows = [
            ['Normal', '#64748b', 0],
            ['High', '#dc2626', 1],
            ['Low', '#94a3b8', 2],
        ];
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_priorities (tenant_id, name, color_hex, sort_order, is_active)
             VALUES (:tid, :n, :c, :o, 1)'
        );
        foreach ($rows as [$name, $hex, $ord]) {
            $stmt->execute(['tid' => $tenantId, 'n' => $name, 'c' => $hex, 'o' => $ord]);
        }
    }

    private function priorityKeySlug(string $name): string
    {
        $s = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($name));
        $s = trim((string) $s, '-');
        if ($s === '') {
            return 'priority';
        }

        return substr($s, 0, 40);
    }

    /**
     * Subscription plan + feature matrix → ads, ArrowChat, reports, etc.
     *
     * @return array{
     *   plan_key: string,
     *   level_id: int|null,
     *   features: array{tasks_reports: bool, chat_arrowchat: bool, theme_custom: bool, ads_toggle: bool, training_employee: bool},
     *   show_google_ads: bool,
     *   adsense_client: string,
     *   adsense_slot_dashboard: string,
     *   arrowchat_autoload_url: string|null,
     *   arrowchat_autoload_configured: bool,
     *   arrowchat_plan_feature_enabled: bool
     * }
     */
    private function resolveIntegrationsForTenant(int $tenantId): array
    {
        $autoloadProbe = ArrowChatIntegration::resolvedAutoloadUrl();

        $defaults = [
            'plan_key' => '',
            'level_id' => null,
            'features' => [
                'tasks_reports' => false,
                'chat_arrowchat' => false,
                'theme_custom' => false,
                'ads_toggle' => false,
                'training_employee' => false,
            ],
            'show_google_ads' => false,
            'adsense_client' => '',
            'adsense_slot_dashboard' => '',
            'arrowchat_autoload_url' => null,
            'arrowchat_autoload_configured' => $autoloadProbe !== null,
            'arrowchat_plan_feature_enabled' => false,
        ];

        $tstmt = $this->pdo->prepare('SELECT plan_key FROM tenants WHERE id = :id LIMIT 1');
        $tstmt->execute(['id' => $tenantId]);
        $trow = $tstmt->fetch();
        if (!$trow) {
            return $defaults;
        }

        $planKey = (string) $trow['plan_key'];
        $defaults['plan_key'] = $planKey;

        $lvlStmt = $this->pdo->prepare(
            'SELECT id, show_ads FROM subscription_levels WHERE level_key = :k AND is_active = 1 LIMIT 1'
        );
        $lvlStmt->execute(['k' => $planKey]);
        $level = $lvlStmt->fetch();
        if (!$level) {
            return $defaults;
        }

        $levelId = (int) $level['id'];
        $defaults['level_id'] = $levelId;
        $showAdsOnPlan = ((int) $level['show_ads']) === 1;

        $flagMap = [];
        $m = $this->pdo->prepare(
            'SELECT ff.feature_key, slf.is_enabled
             FROM subscription_level_features slf
             INNER JOIN feature_flags ff ON ff.id = slf.feature_id
             WHERE slf.level_id = :lid'
        );
        $m->execute(['lid' => $levelId]);
        while ($row = $m->fetch()) {
            $flagMap[(string) $row['feature_key']] = ((int) $row['is_enabled']) === 1;
        }

        $adFree = !empty($flagMap['ads.toggle']);
        $defaults['features'] = [
            'tasks_reports' => !empty($flagMap['tasks.reports']),
            'chat_arrowchat' => !empty($flagMap['chat.arrowchat']),
            'theme_custom' => !empty($flagMap['theme.custom']),
            'ads_toggle' => $adFree,
            'training_employee' => !empty($flagMap['training.employee']),
        ];

        // Read .env at request time (avoid stale config() cache for env vars).
        $client = trim((string) env('GOOGLE_ADSENSE_CLIENT', ''));
        $slot = trim((string) env('GOOGLE_ADSENSE_SLOT_DASHBOARD', ''));
        $defaults['show_google_ads'] = $showAdsOnPlan && !$adFree && $client !== '' && $slot !== '';
        if ($defaults['show_google_ads']) {
            $defaults['adsense_client'] = $client;
            $defaults['adsense_slot_dashboard'] = $slot;
        }

        $chatFeature = !empty($flagMap['chat.arrowchat']);
        $defaults['arrowchat_plan_feature_enabled'] = $chatFeature;

        if ($chatFeature && $autoloadProbe !== null) {
            $defaults['arrowchat_autoload_url'] = $autoloadProbe;
        }

        return $defaults;
    }

    public function listAuditLogs(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, ['owner', 'super_admin', 'tenant_admin'], true)) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $limit = min(500, max(1, (int) ($_GET['limit'] ?? 150)));
        $stmt = $this->pdo->prepare(
            "SELECT al.id, al.tenant_id, al.user_id, al.action, al.ip_address, al.user_agent, al.meta_json, al.created_at,
                    u.display_name AS user_display_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.tenant_id = :tid
             ORDER BY al.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['tid' => $tenantId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            if (isset($r['meta_json']) && is_string($r['meta_json'])) {
                $decoded = json_decode($r['meta_json'], true);
                $r['meta'] = is_array($decoded) ? $decoded : null;
                unset($r['meta_json']);
            } else {
                $r['meta'] = null;
            }
        }
        unset($r);

        json_response(['ok' => true, 'logs' => $rows]);
    }

    private function isTrainingManager(): bool
    {
        return in_array((string) ($_SESSION['role'] ?? ''), [
            'owner', 'super_admin', 'tenant_admin', 'tenant_manager',
        ], true);
    }

    private function assertTrainingEnabled(int $tenantId): bool
    {
        $info = $this->resolveIntegrationsForTenant($tenantId);
        if (empty($info['features']['training_employee'])) {
            json_response(['ok' => false, 'message' => 'Training is not enabled for your subscription plan.'], 403);
            return false;
        }
        return true;
    }

    public function trainingDefinitionsList(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, title, details, sort_order, is_active
             FROM training_definitions
             WHERE tenant_id = :tid
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['tid' => $tenantId]);
        json_response(['ok' => true, 'definitions' => $stmt->fetchAll()]);
    }

    public function trainingDefinitionSave(): void
    {
        if (!Auth::requireUser() || !$this->isTrainingManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'Limited mode.'], 503);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            json_response(['ok' => false, 'message' => 'Title is required.'], 422);
            return;
        }
        $details = trim((string) ($input['details'] ?? ''));
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $isActive = !empty($input['is_active']) ? 1 : 0;
        $uid = (int) $_SESSION['user_id'];

        if ($id < 1) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO training_definitions (tenant_id, title, details, sort_order, is_active)
                 VALUES (:tid, :t, :d, :o, :a)'
            );
            $stmt->execute([
                'tid' => $tenantId,
                't' => $title,
                'd' => $details === '' ? null : $details,
                'o' => $sortOrder,
                'a' => $isActive,
            ]);
            $newId = (int) $this->pdo->lastInsertId();
            AuditLogger::log($this->pdo, $tenantId, $uid, 'training.definition.create', ['id' => $newId]);
            json_response(['ok' => true, 'id' => $newId]);
            return;
        }

        $own = $this->pdo->prepare('SELECT id FROM training_definitions WHERE id = :id AND tenant_id = :tid LIMIT 1');
        $own->execute(['id' => $id, 'tid' => $tenantId]);
        if (!$own->fetch()) {
            json_response(['ok' => false, 'message' => 'Not found.'], 404);
            return;
        }
        $this->pdo->prepare(
            'UPDATE training_definitions SET title = :t, details = :d, sort_order = :o, is_active = :a WHERE id = :id AND tenant_id = :tid'
        )->execute([
            't' => $title,
            'd' => $details === '' ? null : $details,
            'o' => $sortOrder,
            'a' => $isActive,
            'id' => $id,
            'tid' => $tenantId,
        ]);
        AuditLogger::log($this->pdo, $tenantId, $uid, 'training.definition.update', ['id' => $id]);
        json_response(['ok' => true]);
    }

    public function trainingTemplatesList(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $tpl = $this->pdo->prepare(
            'SELECT id, name, description, is_active FROM training_templates WHERE tenant_id = :tid ORDER BY name ASC'
        );
        $tpl->execute(['tid' => $tenantId]);
        $templates = $tpl->fetchAll();
        $itemStmt = $this->pdo->prepare(
            'SELECT tti.definition_id, td.title, tti.sort_order
             FROM training_template_items tti
             INNER JOIN training_definitions td ON td.id = tti.definition_id AND td.tenant_id = :tid
             WHERE tti.template_id = :tpid
             ORDER BY tti.sort_order ASC, tti.id ASC'
        );
        foreach ($templates as &$tp) {
            $itemStmt->execute(['tid' => $tenantId, 'tpid' => (int) $tp['id']]);
            $tp['items'] = $itemStmt->fetchAll();
        }
        unset($tp);

        json_response(['ok' => true, 'templates' => $templates]);
    }

    public function trainingTemplateSave(): void
    {
        if (!Auth::requireUser() || !$this->isTrainingManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'Limited mode.'], 503);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            json_response(['ok' => false, 'message' => 'Name is required.'], 422);
            return;
        }
        $desc = trim((string) ($input['description'] ?? ''));
        $isActive = !empty($input['is_active']) ? 1 : 0;
        $uid = (int) $_SESSION['user_id'];

        if ($id < 1) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO training_templates (tenant_id, name, description, is_active)
                 VALUES (:tid, :n, :d, :a)'
            );
            $stmt->execute([
                'tid' => $tenantId,
                'n' => $name,
                'd' => $desc === '' ? null : $desc,
                'a' => $isActive,
            ]);
            $newId = (int) $this->pdo->lastInsertId();
            AuditLogger::log($this->pdo, $tenantId, $uid, 'training.template.create', ['id' => $newId]);
            json_response(['ok' => true, 'id' => $newId]);
            return;
        }

        $own = $this->pdo->prepare('SELECT id FROM training_templates WHERE id = :id AND tenant_id = :tid LIMIT 1');
        $own->execute(['id' => $id, 'tid' => $tenantId]);
        if (!$own->fetch()) {
            json_response(['ok' => false, 'message' => 'Not found.'], 404);
            return;
        }
        $this->pdo->prepare(
            'UPDATE training_templates SET name = :n, description = :d, is_active = :a WHERE id = :id AND tenant_id = :tid'
        )->execute([
            'n' => $name,
            'd' => $desc === '' ? null : $desc,
            'a' => $isActive,
            'id' => $id,
            'tid' => $tenantId,
        ]);
        AuditLogger::log($this->pdo, $tenantId, $uid, 'training.template.update', ['id' => $id]);
        json_response(['ok' => true]);
    }

    public function trainingTemplateSetItems(): void
    {
        if (!Auth::requireUser() || !$this->isTrainingManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'Limited mode.'], 503);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $input = $this->input();
        $templateId = (int) ($input['template_id'] ?? 0);
        $ids = $input['definition_ids'] ?? null;
        if ($templateId < 1 || !is_array($ids)) {
            json_response(['ok' => false, 'message' => 'template_id and definition_ids[] required.'], 422);
            return;
        }

        $own = $this->pdo->prepare('SELECT id FROM training_templates WHERE id = :id AND tenant_id = :tid LIMIT 1');
        $own->execute(['id' => $templateId, 'tid' => $tenantId]);
        if (!$own->fetch()) {
            json_response(['ok' => false, 'message' => 'Template not found.'], 404);
            return;
        }

        $this->pdo->prepare('DELETE FROM training_template_items WHERE template_id = :id')->execute(['id' => $templateId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO training_template_items (template_id, definition_id, sort_order) VALUES (:tp, :df, :ord)'
        );
        $ord = 0;
        foreach ($ids as $did) {
            $defId = (int) $did;
            if ($defId < 1) {
                continue;
            }
            $chk = $this->pdo->prepare('SELECT id FROM training_definitions WHERE id = :id AND tenant_id = :tid LIMIT 1');
            $chk->execute(['id' => $defId, 'tid' => $tenantId]);
            if (!$chk->fetch()) {
                continue;
            }
            $ins->execute(['tp' => $templateId, 'df' => $defId, 'ord' => $ord++]);
        }

        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'training.template.items', [
            'template_id' => $templateId,
            'count' => $ord,
        ]);
        json_response(['ok' => true]);
    }

    public function trainingAssignmentsMine(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.definition_id, d.title, d.details, a.assigned_at, a.completed_at,
                    a.signoff_see_at, a.signoff_do_at, a.signoff_teach_at,
                    ss.display_name AS signoff_see_by_name,
                    sd.display_name AS signoff_do_by_name,
                    st.display_name AS signoff_teach_by_name,
                    cb.display_name AS completed_by_name
             FROM training_assignments a
             INNER JOIN training_definitions d ON d.id = a.definition_id
             LEFT JOIN users ss ON ss.id = a.signoff_see_by_user_id
             LEFT JOIN users sd ON sd.id = a.signoff_do_by_user_id
             LEFT JOIN users st ON st.id = a.signoff_teach_by_user_id
             LEFT JOIN users cb ON cb.id = a.completed_by_user_id
             WHERE a.tenant_id = :tid AND a.user_id = :uid
             ORDER BY (a.signoff_see_at IS NULL OR a.signoff_do_at IS NULL OR a.signoff_teach_at IS NULL) DESC,
                      a.assigned_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);
        json_response(['ok' => true, 'assignments' => $stmt->fetchAll()]);
    }

    public function trainingAssignmentsTeam(): void
    {
        if (!Auth::requireUser() || !$this->isTrainingManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.user_id, usr.display_name AS user_name, a.definition_id, d.title,
                    a.assigned_at, a.completed_at,
                    a.signoff_see_at, a.signoff_do_at, a.signoff_teach_at,
                    ss.display_name AS signoff_see_by_name,
                    sd.display_name AS signoff_do_by_name,
                    st.display_name AS signoff_teach_by_name
             FROM training_assignments a
             INNER JOIN training_definitions d ON d.id = a.definition_id
             INNER JOIN users usr ON usr.id = a.user_id
             LEFT JOIN users ss ON ss.id = a.signoff_see_by_user_id
             LEFT JOIN users sd ON sd.id = a.signoff_do_by_user_id
             LEFT JOIN users st ON st.id = a.signoff_teach_by_user_id
             WHERE a.tenant_id = :tid
             ORDER BY (a.signoff_see_at IS NULL OR a.signoff_do_at IS NULL OR a.signoff_teach_at IS NULL) DESC,
                      usr.display_name ASC, a.assigned_at DESC
             LIMIT 500'
        );
        $stmt->execute(['tid' => $tenantId]);
        json_response(['ok' => true, 'assignments' => $stmt->fetchAll()]);
    }

    public function trainingAssignDefinition(): void
    {
        if (!Auth::requireUser() || !$this->isTrainingManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'Limited mode.'], 503);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $input = $this->input();
        $userId = (int) ($input['user_id'] ?? 0);
        $defId = (int) ($input['definition_id'] ?? 0);
        if ($userId < 1 || $defId < 1) {
            json_response(['ok' => false, 'message' => 'user_id and definition_id required.'], 422);
            return;
        }

        $u = $this->pdo->prepare('SELECT id FROM users WHERE id = :id AND tenant_id = :tid AND is_active = 1 LIMIT 1');
        $u->execute(['id' => $userId, 'tid' => $tenantId]);
        if (!$u->fetch()) {
            json_response(['ok' => false, 'message' => 'User not found.'], 404);
            return;
        }
        $d = $this->pdo->prepare('SELECT id FROM training_definitions WHERE id = :id AND tenant_id = :tid AND is_active = 1 LIMIT 1');
        $d->execute(['id' => $defId, 'tid' => $tenantId]);
        if (!$d->fetch()) {
            json_response(['ok' => false, 'message' => 'Training item not found.'], 404);
            return;
        }

        $dup = $this->pdo->prepare(
            'SELECT id FROM training_assignments
             WHERE tenant_id = :tid AND user_id = :uid AND definition_id = :did
               AND (signoff_see_at IS NULL OR signoff_do_at IS NULL OR signoff_teach_at IS NULL)
             LIMIT 1'
        );
        $dup->execute(['tid' => $tenantId, 'uid' => $userId, 'did' => $defId]);
        if ($dup->fetch()) {
            json_response(['ok' => false, 'message' => 'This training is already assigned and not fully signed off.'], 422);
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO training_assignments (tenant_id, user_id, definition_id, assigned_by_user_id)
             VALUES (:tid, :uid, :did, :by)'
        )->execute([
            'tid' => $tenantId,
            'uid' => $userId,
            'did' => $defId,
            'by' => (int) $_SESSION['user_id'],
        ]);
        $aid = (int) $this->pdo->lastInsertId();
        AuditLogger::log($this->pdo, $tenantId, (int) $_SESSION['user_id'], 'training.assign', [
            'assignment_id' => $aid,
            'user_id' => $userId,
            'definition_id' => $defId,
        ]);
        json_response(['ok' => true, 'assignment_id' => $aid]);
    }

    public function trainingAssignTemplate(): void
    {
        if (!Auth::requireUser() || !$this->isTrainingManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'Limited mode.'], 503);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $input = $this->input();
        $userId = (int) ($input['user_id'] ?? 0);
        $templateId = (int) ($input['template_id'] ?? 0);
        if ($userId < 1 || $templateId < 1) {
            json_response(['ok' => false, 'message' => 'user_id and template_id required.'], 422);
            return;
        }

        $u = $this->pdo->prepare('SELECT id FROM users WHERE id = :id AND tenant_id = :tid AND is_active = 1 LIMIT 1');
        $u->execute(['id' => $userId, 'tid' => $tenantId]);
        if (!$u->fetch()) {
            json_response(['ok' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $tp = $this->pdo->prepare(
            'SELECT id FROM training_templates WHERE id = :id AND tenant_id = :tid AND is_active = 1 LIMIT 1'
        );
        $tp->execute(['id' => $templateId, 'tid' => $tenantId]);
        if (!$tp->fetch()) {
            json_response(['ok' => false, 'message' => 'Template not found.'], 404);
            return;
        }

        $items = $this->pdo->prepare(
            'SELECT definition_id FROM training_template_items WHERE template_id = :tp ORDER BY sort_order ASC, id ASC'
        );
        $items->execute(['tp' => $templateId]);
        $defRows = $items->fetchAll(PDO::FETCH_COLUMN);
        if (!$defRows) {
            json_response(['ok' => false, 'message' => 'Template has no training items.'], 422);
            return;
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO training_assignments (tenant_id, user_id, definition_id, assigned_by_user_id)
             VALUES (:tid, :uid, :did, :by)'
        );
        $dup = $this->pdo->prepare(
            'SELECT id FROM training_assignments
             WHERE tenant_id = :tid AND user_id = :uid AND definition_id = :did
               AND (signoff_see_at IS NULL OR signoff_do_at IS NULL OR signoff_teach_at IS NULL)
             LIMIT 1'
        );
        $actor = (int) $_SESSION['user_id'];
        $created = 0;
        foreach ($defRows as $did) {
            $defId = (int) $did;
            $dup->execute(['tid' => $tenantId, 'uid' => $userId, 'did' => $defId]);
            if ($dup->fetch()) {
                continue;
            }
            $ins->execute(['tid' => $tenantId, 'uid' => $userId, 'did' => $defId, 'by' => $actor]);
            $created++;
        }

        AuditLogger::log($this->pdo, $tenantId, $actor, 'training.assign_template', [
            'template_id' => $templateId,
            'user_id' => $userId,
            'assignments_created' => $created,
        ]);
        json_response(['ok' => true, 'assignments_created' => $created]);
    }

    /** Manager+ sign-off for See 1, Do 1, or Teach 1. Completes assignment when all three are signed. */
    public function trainingSignoff(): void
    {
        if (!Auth::requireUser() || !$this->isTrainingManager()) {
            json_response(['ok' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'Limited mode.'], 503);
            return;
        }
        $tenantId = Auth::effectiveTenantId();
        if (!$this->assertTrainingEnabled($tenantId)) {
            return;
        }

        $input = $this->input();
        $assignmentId = (int) ($input['assignment_id'] ?? 0);
        $kind = trim((string) ($input['kind'] ?? ''));
        if ($assignmentId < 1 || !in_array($kind, ['see', 'do', 'teach'], true)) {
            json_response(['ok' => false, 'message' => 'assignment_id and kind (see, do, or teach) are required.'], 422);
            return;
        }

        $row = $this->pdo->prepare(
            'SELECT id, signoff_see_at, signoff_do_at, signoff_teach_at
             FROM training_assignments WHERE id = :id AND tenant_id = :tid LIMIT 1'
        );
        $row->execute(['id' => $assignmentId, 'tid' => $tenantId]);
        $a = $row->fetch();
        if (!$a) {
            json_response(['ok' => false, 'message' => 'Assignment not found.'], 404);
            return;
        }

        $colAt = match ($kind) {
            'see' => 'signoff_see_at',
            'do' => 'signoff_do_at',
            'teach' => 'signoff_teach_at',
        };
        $colBy = match ($kind) {
            'see' => 'signoff_see_by_user_id',
            'do' => 'signoff_do_by_user_id',
            'teach' => 'signoff_teach_by_user_id',
        };

        if ($a[$colAt] !== null) {
            json_response(['ok' => false, 'message' => 'This sign-off was already recorded.'], 422);
            return;
        }

        $mgr = (int) $_SESSION['user_id'];
        $sql = "UPDATE training_assignments SET {$colAt} = NOW(), {$colBy} = :mgr WHERE id = :id AND tenant_id = :tid";
        $this->pdo->prepare($sql)->execute(['mgr' => $mgr, 'id' => $assignmentId, 'tid' => $tenantId]);

        $chk = $this->pdo->prepare(
            'SELECT signoff_see_at, signoff_do_at, signoff_teach_at FROM training_assignments WHERE id = :id LIMIT 1'
        );
        $chk->execute(['id' => $assignmentId]);
        $u = $chk->fetch();
        if (
            $u
            && $u['signoff_see_at'] !== null
            && $u['signoff_do_at'] !== null
            && $u['signoff_teach_at'] !== null
        ) {
            $this->pdo->prepare(
                'UPDATE training_assignments
                 SET completed_at = NOW(), completed_by_user_id = :mgr
                 WHERE id = :id AND tenant_id = :tid AND completed_at IS NULL'
            )->execute(['mgr' => $mgr, 'id' => $assignmentId, 'tid' => $tenantId]);
        }

        AuditLogger::log($this->pdo, $tenantId, $mgr, 'training.signoff', [
            'assignment_id' => $assignmentId,
            'kind' => $kind,
        ]);
        json_response(['ok' => true]);
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return $_POST;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
