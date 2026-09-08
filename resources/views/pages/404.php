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
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                <strong>Caminho solicitado</strong>
                <small><code><?= e($path) ?></code></small>
            </p>
        </div>
        <a class="primary-link" href="/cardapio">Voltar ao cardápio
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
    </div>
</section>
