<?php

declare(strict_types=1);

/** @var array<string, mixed> $businessStatus */
$businessMessage = (string) ($businessMessage ?? $businessStatus['message']);
?>
<div class="service-info <?= $businessStatus['is_open'] ? 'is-open' : 'is-closed' ?>" aria-label="Informações de atendimento">
    <p><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span><strong><?= e($businessStatus['status_label']) ?></strong><small><?= e($businessMessage) ?></small></span></p>
    <p><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3M17 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg><span><strong><?= e($businessStatus['schedule_label']) ?></strong><small><?= e($businessStatus['schedule_hours']) ?></small></span></p>
    <p><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span><strong>Consumo ou retirada no local</strong><small>Não fazemos entregas.</small></span></p>
</div>
