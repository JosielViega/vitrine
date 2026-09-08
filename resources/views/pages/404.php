<?php

declare(strict_types=1);

/** @var string $path */
?>
<section class="intro">
    <div class="container intro-content">
        <div>
            <p class="status">Erro 404</p>
            <h1>Página não encontrada</h1>
            <p class="intro-copy">O endereço que você tentou acessar não existe ou não está mais disponível.</p>
        </div>
        <div class="service-card">
            <p>
                <span aria-hidden="true">⌖</span>
                <strong>Caminho solicitado</strong>
                <small><code><?= e($path) ?></code></small>
            </p>
        </div>
        <a class="primary-link" href="/">Voltar ao cardápio <span aria-hidden="true">→</span></a>
    </div>
</section>
