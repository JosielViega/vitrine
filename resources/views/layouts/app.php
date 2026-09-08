<?php

declare(strict_types=1);

/** @var string $content */
/** @var string $title */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cardápio da Bar e Lanchonete São Jorge. Porções, bebidas e acréscimos para consumo no local ou retirada.">
    <meta name="theme-color" content="#0d0907">
    <title><?= e($title ?? 'Bar e Lanchonete São Jorge') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/" aria-label="Início — Bar e Lanchonete São Jorge">
            <span class="brand-seal" aria-hidden="true">
                <svg class="brand-mug" viewBox="0 0 64 56" focusable="false">
                    <path d="M16 18h31v29H16zM47 24h5c9 0 9 17 0 17h-5"/>
                    <path d="M21 18v-4a6 6 0 0 1 11-3 7 7 0 0 1 13 4v3M23 24v17M32 24v17M41 24v17"/>
                </svg>
                <span class="brand-kicker">Bar e Lanchonete</span>
                <strong>São Jorge</strong>
                <span class="brand-offer">Porções • Bebidas • Sucos</span>
                <svg class="brand-cutlery" viewBox="0 0 64 24" focusable="false">
                    <path d="m20 3 24 18M44 3 20 21M17 2v8M21 2v8M25 2v8M21 10v11M44 3c5 5 5 9 0 13v5"/>
                </svg>
            </span>
        </a>
    </header>
    <main>
        <?= $content ?>
    </main>
</body>
</html>
