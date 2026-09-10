<?php

declare(strict_types=1);

use App\Controllers\AdminAuthController;
use App\Controllers\AdminController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\WhatsAppCheckoutController;
use App\Core\Request;
use App\Core\Response;

$home = new HomeController($app['view'], $app['catalog'], $app['businessHours'], $app['csrf']);
$whatsappCheckout = new WhatsAppCheckoutController($app['request'], $app['csrf'], $app['whatsappCheckout'], $app['logger']);
$health = new HealthController();
$router = $app['router'];
$router->get('/', [$home, 'index']);
$router->get('/cardapio', [$home, 'cardapio']);
$router->get('/produto/{slug}', [$home, 'product']);
$router->get('/pedido', [$home, 'order']);
$router->get('/health', [$health, 'index']);
if (isset($app['session'], $app['adminAuth'], $app['storefrontVisibility'])) {
    $adminAuth = new AdminAuthController($app['request'], $app['view'], $app['csrf'], $app['session'], $app['adminAuth'], $app['logger']);
    $admin = new AdminController($app['request'], $app['view'], $app['csrf'], $app['session'], $app['adminAuth'], $app['storefrontVisibility'], $app['logger']);
    $router->get('/admin/login', [$adminAuth, 'loginForm']);
    $router->post('/admin/login', [$adminAuth, 'login']);
    $router->get('/admin', [$admin, 'index']);
    $router->post('/admin/visibility', [$admin, 'updateVisibility']);
    $router->post('/admin/logout', [$adminAuth, 'logout']);
}
$router->post('/checkout/whatsapp', [$whatsappCheckout, 'create']);
$router->fallback(static function (Request $request) use ($app): Response {
    return Response::html($app['view']->render('pages/404', ['title' => 'Página não encontrada | Bar e Lanchonete São Jorge', 'path' => $request->path()]), 404);
});

return $router;
