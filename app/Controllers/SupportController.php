<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\TenantTime;
use PDO;

final class SupportController
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 2097152;

    public function __construct(private readonly PDO $pdo, private readonly bool $limitedMode) {}

    private function isGlobalSupportAdmin(): bool
    {
        $r = (string) ($_SESSION['role'] ?? '');

        return in_array($r, ['owner', 'super_admin'], true);
    }

    private function supportUploadsRoot(): string
    {
        $dir = base_path('storage/support_uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /** @return array{ok:false,message:string}|array{ok:true,storage_key:string,mime:string} */
    private function storeUploadedImage(?array $file, int $tenantId): array
    {
        if ($file === null || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'storage_key' => '', 'mime' => ''];
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Image upload failed.'];
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'message' => 'Image must be 2 MB or smaller.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'message' => 'Invalid upload.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $ext = self::ALLOWED_MIME[$mime] ?? null;
        if ($ext === null) {
            return ['ok' => false, 'message' => 'Use JPEG, PNG, GIF, or WebP images only.'];
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $rel = $tenantId . '/' . $name;
        $destDir = $this->supportUploadsRoot() . '/' . $tenantId;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $dest = $this->supportUploadsRoot() . '/' . $rel;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'message' => 'Could not save image.'];
        }

        return ['ok' => true, 'storage_key' => $rel, 'mime' => $mime];
    }

    /** @return array<string, mixed>|null */
    private function loadTicketRow(int $ticketId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, ten.name AS tenant_name
             FROM support_tickets t
             INNER JOIN tenants ten ON ten.id = t.tenant_id
             WHERE t.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $ticketId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function assertTicketAccess(int $ticketId): ?array
    {
        $row = $this->loadTicketRow($ticketId);
        if ($row === null) {
            return null;
        }
        if ($this->isGlobalSupportAdmin()) {
            return $row;
        }
        if ((int) $row['tenant_id'] === Auth::effectiveTenantId()) {
            return $row;
        }

        return null;
    }

    private function attachmentUrl(?string $storageKey): ?string
    {
        if ($storageKey === null || $storageKey === '') {
            return null;
        }

        return '/api/support/attachment?k=' . rawurlencode($storageKey);
    }

    public function listTickets(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $scope = isset($_GET['scope']) ? trim((string) $_GET['scope']) : 'tenant';
        if ($scope === 'all') {
            if (!$this->isGlobalSupportAdmin()) {
                json_response(['ok' => false, 'message' => 'Forbidden'], 403);
                return;
            }
            $stmt = $this->pdo->query(
                'SELECT t.id, t.tenant_id, ten.name AS tenant_name, t.title, t.priority, t.status,
                        t.created_at, t.updated_at, t.closed_at,
                        u.display_name AS created_by_name
                 FROM support_tickets t
                 INNER JOIN tenants ten ON ten.id = t.tenant_id
                 INNER JOIN users u ON u.id = t.created_by_user_id
                 ORDER BY t.updated_at DESC
                 LIMIT 500'
            );
        } else {
            $tid = Auth::effectiveTenantId();
            $stmt = $this->pdo->prepare(
                'SELECT t.id, t.tenant_id, ten.name AS tenant_name, t.title, t.priority, t.status,
                        t.created_at, t.updated_at, t.closed_at,
                        u.display_name AS created_by_name
                 FROM support_tickets t
                 INNER JOIN tenants ten ON ten.id = t.tenant_id
                 INNER JOIN users u ON u.id = t.created_by_user_id
                 WHERE t.tenant_id = :tid
                 ORDER BY t.updated_at DESC
                 LIMIT 500'
            );
            $stmt->execute(['tid' => $tid]);
        }
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $out = [];
        $now = time();
        foreach ($rows as $r) {
            $created = strtotime((string) $r['created_at']) ?: $now;
            $out[] = [
                'id' => (int) $r['id'],
                'tenant_id' => (int) $r['tenant_id'],
                'tenant_name' => (string) $r['tenant_name'],
                'title' => (string) $r['title'],
                'priority' => (string) $r['priority'],
                'status' => (string) $r['status'],
                'created_at' => TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, (int) $r['tenant_id'], (string) $r['created_at']) ?? (string) $r['created_at'],
                'updated_at' => TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, (int) $r['tenant_id'], (string) $r['updated_at']) ?? (string) $r['updated_at'],
                'closed_at' => $r['closed_at'] !== null && $r['closed_at'] !== ''
                    ? (TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, (int) $r['tenant_id'], (string) $r['closed_at']) ?? (string) $r['closed_at'])
                    : null,
                'created_by_name' => (string) $r['created_by_name'],
                'open_seconds' => max(0, $now - $created),
            ];
        }

        json_response(['ok' => true, 'tickets' => $out]);
    }

    public function createTicket(): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $tenantId = Auth::effectiveTenantId();
        $userId = (int) $_SESSION['user_id'];

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $priority = trim((string) ($_POST['priority'] ?? 'normal'));
        if ($title === '' || strlen($title) > 255) {
            json_response(['ok' => false, 'message' => 'Title is required (max 255 characters).'], 422);
            return;
        }
        if ($description === '') {
            json_response(['ok' => false, 'message' => 'Message is required.'], 422);
            return;
        }
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            json_response(['ok' => false, 'message' => 'Invalid priority.'], 422);
            return;
        }

        $up = $this->storeUploadedImage($_FILES['image'] ?? null, $tenantId);
        if (!$up['ok']) {
            json_response(['ok' => false, 'message' => $up['message']], 422);
            return;
        }
        $attachKey = $up['storage_key'] !== '' ? $up['storage_key'] : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO support_tickets (tenant_id, created_by_user_id, title, description, priority, status, attachment_storage_key)
             VALUES (:tid, :uid, :title, :desc, :pri, \'open\', :att)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'uid' => $userId,
            'title' => $title,
            'desc' => $description,
            'pri' => $priority,
            'att' => $attachKey,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        json_response(['ok' => true, 'ticket_id' => $id]);
    }

    /** @param array<string, mixed> $ticket */
    private function formatTicketDetail(array $ticket): array
    {
        $now = time();
        $created = strtotime((string) $ticket['created_at']) ?: $now;
        $creatorStmt = $this->pdo->prepare(
            'SELECT display_name FROM users WHERE id = :id LIMIT 1'
        );
        $creatorStmt->execute(['id' => (int) $ticket['created_by_user_id']]);
        $cr = $creatorStmt->fetch(PDO::FETCH_ASSOC);
        $closedByName = null;
        if (!empty($ticket['closed_by_user_id'])) {
            $cb = $this->pdo->prepare(
                'SELECT display_name FROM users WHERE id = :id LIMIT 1'
            );
            $cb->execute(['id' => (int) $ticket['closed_by_user_id']]);
            $crow = $cb->fetch(PDO::FETCH_ASSOC);
            $closedByName = $crow ? (string) $crow['display_name'] : null;
        }

        $tid = (int) $ticket['tenant_id'];

        return [
            'id' => (int) $ticket['id'],
            'tenant_id' => $tid,
            'tenant_name' => (string) $ticket['tenant_name'],
            'title' => (string) $ticket['title'],
            'description' => (string) $ticket['description'],
            'priority' => (string) $ticket['priority'],
            'status' => (string) $ticket['status'],
            'created_by' => [
                'id' => (int) $ticket['created_by_user_id'],
                'display_name' => $cr ? (string) $cr['display_name'] : 'Unknown',
            ],
            'created_at' => TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, $tid, (string) $ticket['created_at']) ?? (string) $ticket['created_at'],
            'updated_at' => TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, $tid, (string) $ticket['updated_at']) ?? (string) $ticket['updated_at'],
            'closed_at' => $ticket['closed_at'] !== null && $ticket['closed_at'] !== ''
                ? (TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, $tid, (string) $ticket['closed_at']) ?? (string) $ticket['closed_at'])
                : null,
            'closed_by_name' => $closedByName,
            'open_seconds' => max(0, $now - $created),
            'initial_attachment_url' => $this->attachmentUrl(
                isset($ticket['attachment_storage_key']) && $ticket['attachment_storage_key'] !== ''
                    ? (string) $ticket['attachment_storage_key']
                    : null
            ),
            'can_close' => $this->canCloseTicket($ticket),
            'can_reply' => (string) $ticket['status'] === 'open',
        ];
    }

    /** @param array<string, mixed> $ticket */
    private function canCloseTicket(array $ticket): bool
    {
        if ((string) $ticket['status'] !== 'open') {
            return false;
        }
        if ($this->isGlobalSupportAdmin()) {
            return true;
        }

        return (int) $ticket['tenant_id'] === Auth::effectiveTenantId();
    }

    public function getTicket(int $ticketId): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $ticket = $this->assertTicketAccess($ticketId);
        if ($ticket === null) {
            json_response(['ok' => false, 'message' => 'Not found'], 404);
            return;
        }

        $msgStmt = $this->pdo->prepare(
            'SELECT m.id, m.body, m.attachment_storage_key, m.created_at, m.user_id, u.display_name
             FROM support_ticket_messages m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.ticket_id = :tid
             ORDER BY m.id ASC'
        );
        $msgStmt->execute(['tid' => $ticketId]);
        $messages = [];
        $tid = (int) $ticket['tenant_id'];
        foreach ($msgStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $messages[] = [
                'id' => (int) $m['id'],
                'body' => (string) $m['body'],
                'created_at' => TenantTime::mysqlNaiveDateTimeToAtom($this->pdo, $tid, (string) $m['created_at']) ?? (string) $m['created_at'],
                'user' => [
                    'id' => (int) $m['user_id'],
                    'display_name' => (string) $m['display_name'],
                ],
                'attachment_url' => $this->attachmentUrl(
                    $m['attachment_storage_key'] !== null && $m['attachment_storage_key'] !== ''
                        ? (string) $m['attachment_storage_key']
                        : null
                ),
            ];
        }

        json_response([
            'ok' => true,
            'ticket' => $this->formatTicketDetail($ticket),
            'messages' => $messages,
        ]);
    }

    public function postMessage(int $ticketId): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $ticket = $this->assertTicketAccess($ticketId);
        if ($ticket === null) {
            json_response(['ok' => false, 'message' => 'Not found'], 404);
            return;
        }
        if ((string) $ticket['status'] !== 'open') {
            json_response(['ok' => false, 'message' => 'This ticket is closed.'], 422);
            return;
        }

        $tenantId = (int) $ticket['tenant_id'];
        $userId = (int) $_SESSION['user_id'];
        $body = '';
        $file = null;

        $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($ct, 'multipart/form-data')) {
            $body = trim((string) ($_POST['body'] ?? ''));
            $file = $_FILES['image'] ?? null;
        } else {
            $raw = (string) file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                json_response(['ok' => false, 'message' => 'Invalid JSON.'], 422);
                return;
            }
            $body = trim((string) ($data['body'] ?? ''));
        }

        if ($body === '') {
            json_response(['ok' => false, 'message' => 'Message cannot be empty.'], 422);
            return;
        }

        $up = $this->storeUploadedImage($file, $tenantId);
        if (!$up['ok']) {
            json_response(['ok' => false, 'message' => $up['message']], 422);
            return;
        }
        $attachKey = $up['storage_key'] !== '' ? $up['storage_key'] : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO support_ticket_messages (ticket_id, user_id, body, attachment_storage_key)
             VALUES (:tid, :uid, :body, :att)'
        );
        $stmt->execute([
            'tid' => $ticketId,
            'uid' => $userId,
            'body' => $body,
            'att' => $attachKey,
        ]);

        $this->pdo->prepare(
            'UPDATE support_tickets SET updated_at = NOW() WHERE id = :id'
        )->execute(['id' => $ticketId]);

        json_response(['ok' => true, 'message_id' => (int) $this->pdo->lastInsertId()]);
    }

    public function closeTicket(int $ticketId): void
    {
        if (!Auth::requireUser()) {
            json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }
        if ($this->limitedMode) {
            json_response(['ok' => false, 'message' => 'System is in limited mode due to maintenance.'], 503);
            return;
        }

        $ticket = $this->assertTicketAccess($ticketId);
        if ($ticket === null) {
            json_response(['ok' => false, 'message' => 'Not found'], 404);
            return;
        }
        if (!$this->canCloseTicket($ticket)) {
            json_response(['ok' => false, 'message' => 'You cannot close this ticket.'], 403);
            return;
        }
        if ((string) $ticket['status'] !== 'open') {
            json_response(['ok' => false, 'message' => 'Already closed.'], 422);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $tid = (int) $ticket['tenant_id'];
        $closedAt = TenantTime::nowMysqlInTenantTimezone($this->pdo, $tid);

        $stmt = $this->pdo->prepare(
            'UPDATE support_tickets
             SET status = \'closed\', closed_at = :ca, closed_by_user_id = :uid, updated_at = NOW()
             WHERE id = :id AND status = \'open\''
        );
        $stmt->execute(['ca' => $closedAt, 'uid' => $userId, 'id' => $ticketId]);

        json_response(['ok' => true]);
    }

    public function serveAttachment(): void
    {
        if (!Auth::requireUser()) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        $key = isset($_GET['k']) ? trim((string) $_GET['k']) : '';
        if ($key === '' || str_contains($key, '..') || str_contains($key, '\\')) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        if (!preg_match('#^(\d+)/([a-f0-9]{32}\.(jpg|jpeg|png|gif|webp))$#i', $key, $m)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        $tenantFromKey = (int) $m[1];
        $path = $this->supportUploadsRoot() . '/' . $key;
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $allowed = false;
        if ($this->isGlobalSupportAdmin()) {
            $allowed = true;
        } elseif ($tenantFromKey === Auth::effectiveTenantId()) {
            $allowed = true;
        }

        if (!$allowed) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=3600');
        readfile($path);
    }
}
