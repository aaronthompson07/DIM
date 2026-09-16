<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\TenantTime;
use PDO;

final class PageController
{
    public function index(bool $limitedMode, ?string $migrationError): void
    {
        $title = 'DoItMeow';
        require base_path('views/index.php');
    }

    public function login(bool $limitedMode, ?string $migrationError): void
    {
        $title = 'DoItMeow Login';
        require base_path('views/login.php');
    }

    public function register(bool $limitedMode, ?string $migrationError): void
    {
        $title = 'Create your company';
        $timezoneGroups = TenantTime::groupedIdentifiers();
        require base_path('views/register.php');
    }

    public function dashboard(bool $limitedMode, ?string $migrationError): void
    {
        $title = 'DoItMeow Dashboard';
        require base_path('views/dashboard.php');
    }

    public function admin(bool $limitedMode, ?string $migrationError, string $section = 'hub'): void
    {
        $this->assertAdminSectionAccess($section);
        $timezoneGroups = TenantTime::groupedIdentifiers();
        $adminSection = $section;
        $title = $this->adminSectionTitle($section);
        if ($section === 'impersonate') {
            require base_path('views/admin-impersonate.php');

            return;
        }
        if ($section === 'create-company') {
            require base_path('views/admin-create-company.php');

            return;
        }
        require base_path('views/admin.php');
    }

    private function assertAdminSectionAccess(string $section): void
    {
        if (!Auth::requireUser()) {
            header('Location: /login', true, 302);
            exit;
        }
        $role = (string) ($_SESSION['role'] ?? '');
        if (in_array($section, ['impersonate', 'create-company'], true)) {
            if (!Auth::canImpersonate()) {
                header('Location: /admin', true, 302);
                exit;
            }

            return;
        }
        if (!in_array($role, ['owner', 'super_admin', 'tenant_admin', 'tenant_manager'], true)) {
            header('Location: /dashboard', true, 302);
            exit;
        }
        $elevated = in_array($role, ['owner', 'super_admin', 'tenant_admin'], true);
        $platform = in_array($role, ['owner', 'super_admin'], true);
        $allowed = match ($section) {
            'hub' => true,
            'tasks', 'users', 'training' => true,
            'slots', 'priorities', 'messages', 'company', 'logs' => $elevated,
            'plans', 'matrix' => $platform,
            default => false,
        };
        if (!$allowed) {
            header('Location: /admin', true, 302);
            exit;
        }
    }

    private function adminSectionTitle(string $section): string
    {
        return match ($section) {
            'hub' => 'DoItMeow Admin',
            'tasks' => 'Admin · Tasks',
            'slots' => 'Admin · Time of day',
            'priorities' => 'Admin · Priorities',
            'messages' => 'Admin · System messages',
            'users' => 'Admin · People',
            'logs' => 'Admin · Activity logs',
            'training' => 'Admin · Training',
            'company' => 'Admin · Company',
            'plans' => 'Admin · Billing plans',
            'matrix' => 'Admin · Feature matrix',
            'impersonate' => 'Admin · View as tenant',
            'create-company' => 'Admin · Create company',
            default => 'DoItMeow Admin',
        };
    }

    public function reports(bool $limitedMode, ?string $migrationError): void
    {
        if (!Auth::requireUser()) {
            header('Location: /dashboard', true, 302);
            exit;
        }
        if (!Auth::canViewReports()) {
            header('Location: /dashboard', true, 302);
            exit;
        }

        $title = 'DoItMeow Reports';
        require base_path('views/reports.php');
    }

    public function training(bool $limitedMode, ?string $migrationError): void
    {
        if (!Auth::requireUser()) {
            header('Location: /dashboard', true, 302);
            exit;
        }

        $title = 'Training';
        require base_path('views/training.php');
    }

    public function help(bool $limitedMode, ?string $migrationError): void
    {
        $title = 'Help · DoItMeow';
        require base_path('views/help.php');
    }

    public function changelog(bool $limitedMode, ?string $migrationError): void
    {
        $title = 'Changelog · DoItMeow';
        $appVersion = (string) \config('app.version', '1.0.0');
        $changelogGroups = [];
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query(
                'SELECT version, released_at, sort_order, change_text
                 FROM changelog_entries
                 ORDER BY id ASC'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $v = (string) $row['version'];
                if (!isset($changelogGroups[$v])) {
                    $changelogGroups[$v] = [
                        'version' => $v,
                        'released_at' => $row['released_at'],
                        'items' => [],
                        'sort_keys' => [],
                    ];
                } else {
                    $ra = (string) $row['released_at'];
                    if ($ra !== '' && strcmp($ra, (string) $changelogGroups[$v]['released_at']) > 0) {
                        $changelogGroups[$v]['released_at'] = $row['released_at'];
                    }
                }
                $changelogGroups[$v]['items'][] = (string) $row['change_text'];
                $changelogGroups[$v]['sort_keys'][] = [(int) $row['sort_order'], (int) $row['id']];
            }
            foreach ($changelogGroups as &$g) {
                $n = count($g['items']);
                $order = $g['sort_keys'];
                $idx = range(0, max(0, $n - 1));
                usort(
                    $idx,
                    static function (int $i, int $j) use ($order): int {
                        $cmp = ($order[$i][0] ?? 0) <=> ($order[$j][0] ?? 0);

                        return $cmp !== 0 ? $cmp : (($order[$i][1] ?? 0) <=> ($order[$j][1] ?? 0));
                    }
                );
                $g['items'] = array_map(static fn (int $i) => $g['items'][$i], $idx);
                unset($g['sort_keys']);
            }
            unset($g);
            $changelogGroups = array_values($changelogGroups);
            usort(
                $changelogGroups,
                static function (array $a, array $b): int {
                    return version_compare($b['version'], $a['version']);
                }
            );
        } catch (\Throwable) {
            $changelogGroups = [];
        }
        require base_path('views/changelog.php');
    }

    public function support(bool $limitedMode, ?string $migrationError): void
    {
        if (!Auth::requireUser()) {
            header('Location: /login', true, 302);
            exit;
        }
        $title = 'Support · DoItMeow';
        require base_path('views/support.php');
    }

    public function supportTicket(bool $limitedMode, ?string $migrationError, int $ticketId): void
    {
        if (!Auth::requireUser()) {
            header('Location: /login', true, 302);
            exit;
        }
        if ($ticketId < 1) {
            header('Location: /support', true, 302);
            exit;
        }
        $title = 'Ticket #' . $ticketId . ' · DoItMeow';
        $supportTicketId = $ticketId;
        require base_path('views/support-ticket.php');
    }

    public function supportAll(bool $limitedMode, ?string $migrationError): void
    {
        if (!Auth::requireUser()) {
            header('Location: /login', true, 302);
            exit;
        }
        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, ['owner', 'super_admin'], true)) {
            header('Location: /support', true, 302);
            exit;
        }
        $title = 'All support tickets · DoItMeow';
        require base_path('views/support-all.php');
    }

    public function boardDisplay(): void
    {
        require base_path('views/board-display.php');
    }

    public function appointments(bool $limitedMode, ?string $migrationError): void
    {
        if (empty($_SESSION['company_authenticated'])) {
            header('Location: /login', true, 302);
            exit;
        }
        if (!Auth::requireUser()) {
            header('Location: /dashboard', true, 302);
            exit;
        }
        $title = 'Appointment board · DoItMeow';
        require base_path('views/appointments.php');
    }

}
