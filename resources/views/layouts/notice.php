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
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b0806">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="notice-body">
    <?= $content ?>
</body>
</html>
