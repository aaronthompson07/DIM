<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/helpers.php';
require dirname(__DIR__) . '/app/Core/Env.php';
require dirname(__DIR__) . '/app/Core/Database.php';
require dirname(__DIR__) . '/app/Core/MigrationRunner.php';
require dirname(__DIR__) . '/app/Core/Auth.php';
require dirname(__DIR__) . '/app/Core/TenantTime.php';
require dirname(__DIR__) . '/app/Core/SubscriptionFeatures.php';
require dirname(__DIR__) . '/app/Core/ArrowChatIntegration.php';
require dirname(__DIR__) . '/app/Core/ArrowChatBridge.php';
require dirname(__DIR__) . '/app/Services/AuditLogger.php';
require dirname(__DIR__) . '/app/Services/WeatherAlertsService.php';
require dirname(__DIR__) . '/app/Controllers/PageController.php';
require dirname(__DIR__) . '/app/Controllers/ApiController.php';
require dirname(__DIR__) . '/app/Controllers/SupportController.php';

use App\Controllers\ApiController;
use App\Controllers\PageController;
use App\Controllers\SupportController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\MigrationRunner;

Env::load(base_path('.env'));
Auth::initSession();

$pdo = Database::connection();
$migrationResult = MigrationRunner::runPending($pdo, base_path('database/migrations'));
$limitedMode = (bool) $migrationResult['limited_mode'];
$migrationError = $migrationResult['error'] ? (string) $migrationResult['error'] : null;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$pageController = new PageController();
$api = new ApiController($pdo, $limitedMode);
$supportApi = new SupportController($pdo, $limitedMode);

$tenantMaintenanceWriteLockActive = static function () use ($pdo): bool {
    try {
        $stmt = $pdo->prepare(
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
};

if ($path === '/') {
    $pageController->index($limitedMode, $migrationError);
    exit;
}

if ($path === '/login') {
    $pageController->login($limitedMode, $migrationError);
    exit;
}

if ($path === '/register') {
    $pageController->register($limitedMode, $migrationError);
    exit;
}

if ($path === '/dashboard') {
    $pageController->dashboard($limitedMode, $migrationError);
    exit;
}

if ($path === '/admin') {
    $pageController->admin($limitedMode, $migrationError, 'hub');
    exit;
}
if (preg_match('#^/admin/(impersonate|create-company|tasks|slots|priorities|messages|users|logs|training|company|plans|matrix)$#', $path, $adminPathMatch)) {
    $pageController->admin($limitedMode, $migrationError, $adminPathMatch[1]);
    exit;
}

if ($path === '/reports') {
    $pageController->reports($limitedMode, $migrationError);
    exit;
}

if ($path === '/training') {
    $pageController->training($limitedMode, $migrationError);
    exit;
}

if ($path === '/help') {
    $pageController->help($limitedMode, $migrationError);
    exit;
}

if ($path === '/changelog') {
    $pageController->changelog($limitedMode, $migrationError);
    exit;
}

if ($path === '/support') {
    $pageController->support($limitedMode, $migrationError);
    exit;
}

if ($path === '/support/all') {
    $pageController->supportAll($limitedMode, $migrationError);
    exit;
}

if (preg_match('#^/support/(\d+)$#', $path, $supportPathMatch)) {
    $pageController->supportTicket($limitedMode, $migrationError, (int) $supportPathMatch[1]);
    exit;
}

if (str_starts_with($path, '/api/')) {
    if ($method === 'POST') {
        $role = (string) ($_SESSION['role'] ?? '');
        $bypass = in_array($role, ['owner', 'super_admin'], true);
        $sessionOnlyPaths = [
            '/api/auth/company-login',
            '/api/auth/pin-login',
            '/api/auth/demo-login',
            '/api/auth/logout-user',
            '/api/auth/logout-all',
            '/api/auth/theme-preference',
        ];
        $pathAllowedDuringTenantMaintenance = in_array($path, $sessionOnlyPaths, true);
        if (
            !$limitedMode
            && !$bypass
            && !$pathAllowedDuringTenantMaintenance
            && $tenantMaintenanceWriteLockActive()
        ) {
            json_response(['ok' => false, 'message' => 'Maintenance mode is active. This action is read-only right now.'], 503);
            exit;
        }
    }

    if ($method === 'POST' && $path === '/api/auth/company-login') {
        $api->companyLogin();
        exit;
    }
    if ($method === 'GET' && $path === '/api/auth/company-users') {
        $api->companyUsers();
        exit;
    }
    if ($method === 'POST' && $path === '/api/auth/pin-login') {
        $api->pinLogin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/auth/demo-login') {
        $api->demoLogin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/auth/logout-user') {
        $api->logout(false);
        exit;
    }
    if ($method === 'POST' && $path === '/api/auth/logout-all') {
        $api->logout(true);
        exit;
    }
    if ($method === 'GET' && $path === '/api/auth/me') {
        $api->me();
        exit;
    }
    if ($method === 'POST' && $path === '/api/auth/theme-preference') {
        $api->themePreference();
        exit;
    }
    if ($method === 'POST' && $path === '/api/auth/register-tenant') {
        $api->registerTenant();
        exit;
    }
    if ($method === 'GET' && $path === '/api/tasks/today-summary') {
        $api->todaySummary();
        exit;
    }
    if ($method === 'GET' && $path === '/api/tasks/today-board') {
        $api->todayBoard();
        exit;
    }
    if ($method === 'GET' && $path === '/api/tasks/today-open') {
        $api->todayTasks();
        exit;
    }
    if ($method === 'GET' && $path === '/api/tasks/today-completed') {
        $api->completedToday();
        exit;
    }
    if ($method === 'GET' && $path === '/api/tasks/assigned-to-me') {
        $api->assignedToMe();
        exit;
    }
    if ($method === 'GET' && $path === '/api/weather/alerts') {
        $api->weatherAlerts();
        exit;
    }
    if ($method === 'GET' && $path === '/api/weather/current') {
        $api->weatherCurrent();
        exit;
    }
    if ($method === 'GET' && $path === '/api/reports/summary') {
        $api->reportsSummary();
        exit;
    }
    if ($method === 'POST' && $path === '/api/tasks/complete') {
        $api->completeTask();
        exit;
    }
    if ($method === 'POST' && $path === '/api/tasks/uncomplete') {
        $api->uncompleteTask();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/config') {
        $api->adminConfig();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/system-messages') {
        $api->listSystemMessagesAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/system-messages/create') {
        $api->createSystemMessageAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/system-messages/delete') {
        $api->deleteSystemMessageAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/tenant-theme') {
        $api->updateTenantTheme();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/tenant-profile') {
        $api->updateTenantProfile();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/feature-matrix') {
        $api->updateFeatureMatrix();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/tenants') {
        $api->listTenants();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/tenants/create') {
        $api->createTenantAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/impersonate') {
        $api->impersonateTenant();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/impersonate-clear') {
        $api->clearImpersonate();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/tenant-users') {
        $api->tenantUsersForAdmin();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/tasks') {
        $api->adminTasksList();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/tasks/create') {
        $api->adminTaskCreate();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/tasks/update') {
        $api->adminTaskUpdate();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/tasks/delete') {
        $api->adminTaskDelete();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/time-slots') {
        $api->listTimeSlotsAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/time-slots/create') {
        $api->createTimeSlot();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/time-slots/update') {
        $api->updateTimeSlot();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/priorities') {
        $api->listTenantPrioritiesAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/priorities/create') {
        $api->createTenantPriority();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/priorities/update') {
        $api->updateTenantPriority();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/plans/create') {
        $api->createSubscriptionLevel();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/plans/update') {
        $api->updateSubscriptionLevel();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/users') {
        $api->listUsersAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/users/create') {
        $api->createUserAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/users/update') {
        $api->updateUserAdmin();
        exit;
    }
    if ($method === 'POST' && $path === '/api/admin/users/set-pin') {
        $api->setUserPinAdmin();
        exit;
    }
    if ($method === 'GET' && $path === '/api/admin/audit-logs') {
        $api->listAuditLogs();
        exit;
    }
    if ($method === 'GET' && $path === '/api/training/definitions') {
        $api->trainingDefinitionsList();
        exit;
    }
    if ($method === 'POST' && $path === '/api/training/definitions/save') {
        $api->trainingDefinitionSave();
        exit;
    }
    if ($method === 'GET' && $path === '/api/training/templates') {
        $api->trainingTemplatesList();
        exit;
    }
    if ($method === 'POST' && $path === '/api/training/templates/save') {
        $api->trainingTemplateSave();
        exit;
    }
    if ($method === 'POST' && $path === '/api/training/templates/items') {
        $api->trainingTemplateSetItems();
        exit;
    }
    if ($method === 'GET' && $path === '/api/training/my-assignments') {
        $api->trainingAssignmentsMine();
        exit;
    }
    if ($method === 'GET' && $path === '/api/training/team-assignments') {
        $api->trainingAssignmentsTeam();
        exit;
    }
    if ($method === 'POST' && $path === '/api/training/assign-definition') {
        $api->trainingAssignDefinition();
        exit;
    }
    if ($method === 'POST' && $path === '/api/training/assign-template') {
        $api->trainingAssignTemplate();
        exit;
    }
    if ($method === 'POST' && $path === '/api/training/signoff') {
        $api->trainingSignoff();
        exit;
    }

    if ($method === 'GET' && $path === '/api/support/tickets') {
        $supportApi->listTickets();
        exit;
    }
    if ($method === 'POST' && $path === '/api/support/tickets') {
        $supportApi->createTicket();
        exit;
    }
    if (preg_match('#^/api/support/tickets/(\d+)$#', $path, $sm) && $method === 'GET') {
        $supportApi->getTicket((int) $sm[1]);
        exit;
    }
    if (preg_match('#^/api/support/tickets/(\d+)/messages$#', $path, $sm) && $method === 'POST') {
        $supportApi->postMessage((int) $sm[1]);
        exit;
    }
    if (preg_match('#^/api/support/tickets/(\d+)/close$#', $path, $sm) && $method === 'POST') {
        $supportApi->closeTicket((int) $sm[1]);
        exit;
    }
    if ($method === 'GET' && $path === '/api/support/attachment') {
        $supportApi->serveAttachment();
        exit;
    }

    if ($method === 'GET' && $path === '/api/holidays/season') {
        $api->holidaysSeason();
        exit;
    }

    json_response(['ok' => false, 'message' => 'Endpoint not found.'], 404);
    exit;
}

http_response_code(404);
echo 'Not Found';
