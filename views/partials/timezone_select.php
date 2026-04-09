<?php
declare(strict_types=1);

/** @var array<string, array<string, string>> $timezoneGroups */
/** @var string $timezoneSelectId */
/** @var string $timezoneSelected */
/** @var string|null $timezoneSelectName */

$nameAttr = '';
if (isset($timezoneSelectName) && $timezoneSelectName !== '') {
    $nameAttr = ' name="' . htmlspecialchars((string) $timezoneSelectName) . '"';
}
$sel = \App\Core\TenantTime::validateTimezoneIdentifier($timezoneSelected ?? 'UTC');
?>
<select id="<?= htmlspecialchars($timezoneSelectId, ENT_QUOTES, 'UTF-8') ?>"<?= $nameAttr ?> class="timezone-select" required autocomplete="off">
<?php foreach ($timezoneGroups as $group => $zones) { ?>
  <optgroup label="<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>">
  <?php foreach ($zones as $tzId => $label) { ?>
    <option value="<?= htmlspecialchars($tzId, ENT_QUOTES, 'UTF-8') ?>"<?= $tzId === $sel ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
  <?php } ?>
  </optgroup>
<?php } ?>
</select>
