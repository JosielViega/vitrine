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
    <meta name="theme-color" content="#173f35">
    <title><?= e($title ?? 'Bar e Lanchonete São Jorge') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/" aria-label="Início — Bar e Lanchonete São Jorge">
            <span class="brand-mark" aria-hidden="true">SJ</span>
            <span><strong>São Jorge</strong><small>Bar e Lanchonete</small></span>
        </a>
    </header>
    <main>
        <?= $content ?>
    </main>
</body>
</html>
