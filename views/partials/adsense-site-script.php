<?php
declare(strict_types=1);

/**
 * Loads the AdSense publisher script on a page so Google can associate the domain with your
 * ca-pub client (e.g. during review). Does not insert an ad unit or call adsbygoogle.push(),
 * so no display inventory runs from this partial alone.
 *
 * If "Auto ads" is enabled in your AdSense account, Google may still inject ads on pages that
 * load this script — turn off Auto ads or restrict placements there if the landing page must
 * stay completely ad-free while keeping this script for verification.
 */
$adsenseClient = trim((string) env('GOOGLE_ADSENSE_CLIENT', ''));
if ($adsenseClient === '' || !preg_match('/^ca-pub-\d+$/', $adsenseClient)) {
    return;
}
?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($adsenseClient, ENT_QUOTES, 'UTF-8') ?>" crossorigin="anonymous"></script>
