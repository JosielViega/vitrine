<?php

declare(strict_types=1);

/** @var array{title: string, message: string} $notice */
?>
<main class="public-notice" aria-labelledby="public-notice-title">
    <img class="public-notice-logo" src="/assets/images/brand/logo-sao-jorge.png" alt="Bar e Lanchonete São Jorge">
    <section class="public-notice-card">
        <span class="public-notice-eyebrow">Comunicado</span>
        <h1 id="public-notice-title"><?= e($notice['title']) ?></h1>
        <p><?= e($notice['message']) ?></p>
    </section>
</main>
