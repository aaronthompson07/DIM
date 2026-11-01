<?php
declare(strict_types=1);
/** @var bool $limitedMode */
/** @var string|null $migrationError */
if (empty($limitedMode)) {
    return;
}
?>
<div class="banner warning">
    <strong>Database upgrade required.</strong> Write actions are disabled until pending migrations finish.
    <?php if (!empty($migrationError)) { ?>
        <p class="migration-error-detail muted"><?= htmlspecialchars((string) $migrationError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php } ?>
</div>
