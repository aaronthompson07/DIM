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
        <span class="site-footer-sep" aria-hidden="true">·</span>
        <a href="/food-calculator.php" class="footer-popup-link" data-popup-title="Pet Feeding Calculator">Pet feeding calculator</a>
        <?php if (!empty($_SESSION['company_authenticated'])) {
            $footerPinUser = !empty($_SESSION['user_id']);
        ?>
        <span class="site-footer-sep<?= $footerPinUser ? '' : ' hidden' ?>" aria-hidden="true" id="footer-support-sep">·</span>
        <a href="/support" id="footer-support-tickets" class="<?= $footerPinUser ? '' : 'hidden' ?>">Support &amp; tickets</a>
        <?php } ?>
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
<script>
(function () {
    document.querySelectorAll('a.footer-popup-link').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var name = (link.dataset.popupTitle || link.textContent || 'DoItMeow').trim().replace(/\s+/g, '_');
            var w = 1020;
            var h = 820;
            var left = Math.max(0, Math.round((window.screen.availWidth - w) / 2));
            var top = Math.max(0, Math.round((window.screen.availHeight - h) / 2));
            var features = 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes';
            window.open(link.href, name, features);
        });
    });
})();
</script>
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
