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

    public function admin(bool $limitedMode, ?string $migrationError): void
    {
        $title = 'DoItMeow Admin';
        $timezoneGroups = TenantTime::groupedIdentifiers();
        require base_path('views/admin.php');
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
                 ORDER BY released_at DESC, version DESC, sort_order ASC, id ASC'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $v = (string) $row['version'];
                if (!isset($changelogGroups[$v])) {
                    $changelogGroups[$v] = [
                        'version' => $v,
                        'released_at' => $row['released_at'],
                        'items' => [],
                    ];
                }
                $changelogGroups[$v]['items'][] = (string) $row['change_text'];
            }
            $changelogGroups = array_values($changelogGroups);
        } catch (\Throwable) {
            $changelogGroups = [];
        }
        require base_path('views/changelog.php');
    }
}
