<?php

declare(strict_types=1);

$gaId = trim((string) config('analytics.ga4_measurement_id', ''));
if ($gaId === '') {
    return;
}
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId, ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= htmlspecialchars($gaId, ENT_QUOTES, 'UTF-8') ?>');
</script>
