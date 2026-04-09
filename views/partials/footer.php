<?php
declare(strict_types=1);

use App\Core\ArrowChatIntegration;
use App\Core\Auth;
use App\Core\Database;
use App\Core\TenantTime;

$companyTzForFooter = null;
if (!empty($_SESSION['company_authenticated'])) {
    $tid = Auth::effectiveTenantId();
    if ($tid > 0) {
        try {
            $companyTzForFooter = TenantTime::timezoneStringForTenant(Database::connection(), $tid);
        } catch (\Throwable) {
            $companyTzForFooter = 'UTC';
        }
    }
}
?>
<footer class="site-footer" role="contentinfo">
    <p class="site-footer-inner">
        Powered by <strong>DoItMeow</strong>
        <span class="site-footer-sep" aria-hidden="true">·</span>
        <span class="muted" title="Application version">v<?= htmlspecialchars((string) \config('app.version', '1.0.0'), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="site-footer-sep" aria-hidden="true">·</span>
        <a href="/changelog">Changelog</a>
        <span class="site-footer-sep" aria-hidden="true">·</span>
        <a href="/help">Help &amp; how it works</a>
        <?php if ($companyTzForFooter !== null) { ?>
        <span class="site-footer-sep" aria-hidden="true">·</span>
        <span id="footer-company-time" class="footer-company-clock muted" title="Time in your company timezone"></span>
        <span id="footer-weather-sep" class="site-footer-sep footer-weather-sep hidden" aria-hidden="true">·</span>
        <span id="footer-company-weather" class="footer-company-weather muted hidden" title="Current weather near your company ZIP (NOAA)"></span>
        <script>
        window.__COMPANY_TZ__ = <?= json_encode($companyTzForFooter, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
        </script>
        <?php } ?>
    </p>
</footer>
<?php
$arrowchatFooterSrc = null;
if (!empty($_SESSION['company_authenticated'])) {
    $acTid = Auth::effectiveTenantId();
    if ($acTid > 0) {
        try {
            $arrowchatFooterSrc = ArrowChatIntegration::footerScriptSrcIfEnabled(Database::connection(), $acTid);
        } catch (\Throwable) {
            $arrowchatFooterSrc = null;
        }
    }
}
?>
<?php if ($arrowchatFooterSrc !== null) { ?>
<script type="text/javascript" src="<?= htmlspecialchars($arrowchatFooterSrc, ENT_QUOTES, 'UTF-8') ?>" charset="utf-8"></script>
<?php } ?>
