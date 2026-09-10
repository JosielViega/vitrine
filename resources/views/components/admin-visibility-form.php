<?php

declare(strict_types=1);

/** @var array $item */
/** @var string $itemType */
/** @var string $section */
/** @var string $csrfToken */
$checked = (int) $item['storefront_visible'] === 1;
?>
<form class="visibility-form" method="post" action="/admin/visibility" data-visibility-form>
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="type" value="<?= e($itemType) ?>">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <input type="hidden" name="section" value="<?= e($section) ?>">
    <input type="hidden" name="visible" value="0">
    <label class="switch-row">
        <span>Visível na vitrine</span>
        <span class="switch">
            <input type="checkbox" name="visible" value="1" <?= $checked ? 'checked' : '' ?> data-visibility-toggle>
            <span class="switch-track" aria-hidden="true"></span>
        </span>
    </label>
    <button class="button button-secondary admin-submit" type="submit">Salvar</button>
</form>
