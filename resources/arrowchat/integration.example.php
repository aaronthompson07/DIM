<?php
/**
 * Reference copy for ArrowChat includes/integration.php (DoItMeow).
 *
 * Why owner/super_admin were missing: in DoItMeow those accounts usually live under the
 * platform/home tenant_id, while tenant_admin/tenant_manager/tenant_user rows live under
 * each customer company. Filtering only u.tenant_id = viewer’s tenant hides platform staff.
 *
 * Set DOITMEOW_ARROWCHAT_PLATFORM_TENANT_ID to that home tenant’s id (see tenants.id) so
 * owner + super_admin in that tenant are included for everyone else’s buddy lists.
 *
 * Impersonation: owner/super_admin viewing as another company use $_SESSION['impersonate_tenant_id'];
 * doitmeow_company_tenant_for_chat() mirrors DoItMeow Auth::effectiveTenantId() for chat scope.
 *
 * NOT loaded by DoItMeow — paste into your ArrowChat server.
 */

// Match DoItMeow: config/app.php session_name (default doitmeow_session)
if (session_status() === PHP_SESSION_NONE) {
    session_name('doitmeow_session');
    session_start();
}

/**
 * tenants.id for the row where your owner/super_admin users live (often “home” / Moonbyte HQ).
 * Set to 0 to disable (only strict same-tenant lists).
 */
if (!defined('DOITMEOW_ARROWCHAT_PLATFORM_TENANT_ID')) {
    define('DOITMEOW_ARROWCHAT_PLATFORM_TENANT_ID', 0);
}

function get_user_id()
{
    if (empty($_SESSION['tenant_id']) || empty($_SESSION['user_id'])) {
        return null;
    }
    return (string) $_SESSION['user_id'];
}

/**
 * Company tenant for chat lists — same idea as DoItMeow Auth::effectiveTenantId().
 */
function doitmeow_company_tenant_for_chat(): int
{
    $role = (string) ($_SESSION['role'] ?? '');
    $imp = isset($_SESSION['impersonate_tenant_id']) ? (int) $_SESSION['impersonate_tenant_id'] : 0;
    if ($imp > 0 && in_array($role, ['owner', 'super_admin'], true)) {
        return $imp;
    }

    return (int) ($_SESSION['tenant_id'] ?? 0);
}

/**
 * WHERE fragment: same company as viewer, plus optional platform owner/super_admin tenant.
 */
function doitmeow_chat_visible_users_sql($db, string $escapedUserId): string
{
    $tid = doitmeow_company_tenant_for_chat();
    $platformTid = (int) DOITMEOW_ARROWCHAT_PLATFORM_TENANT_ID;
    $escTid = $db->escape_string((string) max(0, $tid));

    $w = "u.is_active = 1 AND u.id != '" . $escapedUserId . "' AND ( u.tenant_id = '" . $escTid . "'";
    if ($platformTid > 0) {
        $w .= " OR (u.role IN ('owner', 'super_admin') AND u.tenant_id = '" . $platformTid . "')";
    }
    $w .= ' )';

    return $w;
}

/** arrowchat_status.userid may be varchar; users.id is integer — compare as unsigned. */
function doitmeow_ac_status_join_on(): string
{
    return 'CAST(arrowchat_status.userid AS UNSIGNED) = u.id';
}

function get_friend_list($userid, $time)
{
    global $db;
    global $online_timeout;

    $uid = $db->escape_string($userid);
    $joinOn = doitmeow_ac_status_join_on();
    $scope = doitmeow_chat_visible_users_sql($db, $uid);

    return ("
        SELECT DISTINCT
            u.id userid,
            u.display_name username,
            COALESCE(arrowchat_status.session_time, 0) lastactivity,
            u.display_name avatar,
            u.id link,
            COALESCE(arrowchat_status.is_admin, 0) is_admin,
            COALESCE(arrowchat_status.status, '') status
        FROM users u
        LEFT JOIN arrowchat_status
            ON {$joinOn}
        WHERE {$scope}
        ORDER BY u.display_name ASC
    ");
}

function get_online_list($userid, $time)
{
    global $db;
    global $online_timeout;

    $uid = $db->escape_string($userid);
    $joinOn = doitmeow_ac_status_join_on();
    $t = time();
    $scope = doitmeow_chat_visible_users_sql($db, $uid);

    return ("
        SELECT DISTINCT
            u.id userid,
            u.display_name username,
            arrowchat_status.session_time lastactivity,
            u.display_name avatar,
            u.id link,
            COALESCE(arrowchat_status.is_admin, 0) is_admin,
            COALESCE(arrowchat_status.status, '') status
        FROM users u
        INNER JOIN arrowchat_status
            ON {$joinOn}
        WHERE {$scope}
          AND arrowchat_status.session_time > (" . $t . ' - ' . (int) $online_timeout . " - 60)
        ORDER BY u.display_name ASC
    ");
}

function get_user_details($userid)
{
    global $db;

    $joinOn = doitmeow_ac_status_join_on();

    return ("
        SELECT
            u.id userid,
            u.display_name username,
            arrowchat_status.session_time lastactivity,
            u.id link,
            u.display_name avatar,
            COALESCE(arrowchat_status.is_admin, 0) is_admin,
            COALESCE(arrowchat_status.status, '') status
        FROM users u
        LEFT JOIN arrowchat_status
            ON {$joinOn}
        WHERE u.id = '" . $db->escape_string($userid) . "'
    ");
}

function get_link($link, $user_id)
{
    global $base_url;
    return $base_url . '../dashboard';
}

function get_avatar($image, $user_id)
{
    global $base_url;
    if (is_string($image) && $image !== '' && is_file(dirname(dirname(dirname(__FILE__))) . '/images/' . $image . '.gif')) {
        return $base_url . '../images/' . $image . '.gif';
    }
    return $base_url . AC_FOLDER_ADMIN . '/images/img-no-avatar.png';
}

function get_group_id($userid)
{
    global $db;

    $uid = $db->escape_string($userid);
    $result = $db->execute("
        SELECT tenant_id
        FROM users
        WHERE id = '" . $uid . "'
        LIMIT 1
    ");

    if ($result && $db->count_select() > 0) {
        $row = $db->fetch_array($result);
        return array((int) $row['tenant_id']);
    }
    return null;
}

function get_all_groups()
{
    global $db;

    $groups = array();
    $result = $db->execute("SELECT id, name FROM tenants ORDER BY name ASC");
    if ($result && $db->count_select() > 0) {
        while ($row = $db->fetch_array($result)) {
            $groups[] = array($row['id'], $row['name']);
        }
        return $groups;
    }
    return null;
}

function get_username($userid)
{
    global $db;
    global $language;
    global $show_full_username;

    $users_name = $language[83];
    $result = $db->execute("
        SELECT display_name AS name
        FROM users
        WHERE id = '" . $db->escape_string($userid) . "'
    ");
    if ($result && $db->count_select() > 0) {
        $row = $db->fetch_array($result);
        $users_name = $row['name'];
    }
    $pieces = explode(' ', $users_name);
    if ($show_full_username == 1) {
        return $users_name;
    }
    return $pieces[0];
}
