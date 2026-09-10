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
    <meta name="theme-color" content="#15100c">
    <title><?= e($title ?? 'Admin da Vitrine') ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script src="/assets/js/admin.js" defer></script>
</head>
<body class="admin-body">
    <?= $content ?>
</body>
</html>
