<?php

declare(strict_types=1);

/** @var string $csrfToken */
/** @var array $flashMessages */
?>
<main class="login-shell">
    <section class="login-card" aria-labelledby="login-title">
        <img class="login-logo" src="/assets/images/brand/logo-sao-jorge.png" alt="Bar e Lanchonete São Jorge">
        <div class="login-heading">
            <span class="eyebrow">Área restrita</span>
            <h1 id="login-title">Admin da Vitrine</h1>
            <p>Entre com suas credenciais administrativas.</p>
        </div>

        <?php foreach (($flashMessages['error'] ?? []) as $message): ?>
            <div class="flash flash-error" role="alert"><?= e($message) ?></div>
        <?php endforeach; ?>

        <form class="login-form" method="post" action="/admin/login">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <label>
                <span>Usuário</span>
                <input type="text" name="username" autocomplete="username" maxlength="100" required autofocus>
            </label>
            <label>
                <span>Senha</span>
                <input type="password" name="password" autocomplete="current-password" maxlength="4096" required>
            </label>
            <button class="button button-primary button-wide" type="submit">Entrar</button>
        </form>
    </section>
</main>
