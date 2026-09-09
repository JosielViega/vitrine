<?php

declare(strict_types=1);

$activeNav = 'order';
/** @var array<string, mixed> $businessStatus */
$isBusinessOpen = (bool) $businessStatus['is_open'];
?>
<div class="screen order-screen" data-page="order" data-business-open="<?= $isBusinessOpen ? 'true' : 'false' ?>">
    <header class="topbar">
        <a class="topbar-action" href="/cardapio" aria-label="Voltar ao cardápio"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></a>
        <h1>Meu Pedido</h1>
        <button class="topbar-action" type="button" data-clear-cart aria-label="Limpar pedido"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6"/></svg></button>
    </header>
    <div class="order-content">
        <div class="order-items" data-order-items></div>
        <div class="empty-order" data-empty-order hidden><h2>Seu pedido está vazio</h2><p>Escolha uma porção ou bebida no cardápio.</p></div>
        <a class="add-more" href="/cardapio"><span class="round-add"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span>Adicionar mais itens<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a>
        <div class="order-total"><p><span>Subtotal</span><strong data-order-subtotal>R$ 0,00</strong></p><p><span>Total</span><strong data-order-total>R$ 0,00</strong></p></div>
        <button class="whatsapp-button" type="button" data-checkout <?= $isBusinessOpen ? '' : 'disabled' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4A8 8 0 1 1 20 11.5Z"/><path d="M9 8.5c.5 2.5 2 4 4.5 5"/></svg>Finalizar pedido no WhatsApp</button>
        <p class="checkout-notice" data-checkout-notice role="status" hidden>Finalização pelo WhatsApp será conectada na próxima etapa.</p>
        <div class="order-service"><?php $businessMessage = (string) $businessStatus['checkout_message']; require dirname(__DIR__) . '/components/service-info.php'; ?><p><?= $isBusinessOpen ? 'A finalização pelo WhatsApp será conectada na próxima etapa.' : 'Pedidos pelo WhatsApp estão disponíveis durante nosso horário de atendimento.' ?></p></div>
        <div class="order-banner"><img src="/assets/images/storefront/drinks-placeholder.jpg" alt="Bebida gelada" width="1280" height="720"><p>Boa comida<br><span>une boas pessoas!</span></p></div>
    </div>
    <?php require dirname(__DIR__) . '/components/bottom-nav.php'; ?>
    <div class="toast" data-toast role="status" aria-live="polite"></div>
</div>
