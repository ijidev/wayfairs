<?php

declare(strict_types=1);

$GLOBALS['wayfairs_translator_index'] = (int) ($GLOBALS['wayfairs_translator_index'] ?? 0) + 1;
$translatorId = 'wayfairs-language-' . $GLOBALS['wayfairs_translator_index'];
?>
<div class="browser-translator" data-browser-translator translate="no">
  <label class="sr-only" for="<?= e($translatorId) ?>">Translate interface</label>
  <select id="<?= e($translatorId) ?>" data-translate-language aria-label="Translate interface">
    <option value="en">English</option>
    <option value="fr">Français</option>
    <option value="es">Español</option>
    <option value="de">Deutsch</option>
    <option value="pt">Português</option>
    <option value="it">Italiano</option>
  </select>
  <button class="translate-button" type="button" data-translate-button><?= wayfairs_icon('globe') ?><span>Translate</span></button>
  <span class="translator-status" data-translate-status role="status" aria-live="polite"></span>
</div>
