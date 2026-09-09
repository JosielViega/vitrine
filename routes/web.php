<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Core\Request;
use App\Core\Response;

$home = new HomeController($app['view'], $app['catalog'], $app['businessHours']);
$health = new HealthController();
$router = $app['router'];
$router->get('/', [$home, 'index']);
$router->get('/cardapio', [$home, 'cardapio']);
$router->get('/produto/{slug}', [$home, 'product']);
$router->get('/pedido', [$home, 'order']);
$router->get('/health', [$health, 'index']);
$router->fallback(static function (Request $request) use ($app): Response {
    return Response::html($app['view']->render('pages/404', ['title' => 'Página não encontrada | Bar e Lanchonete São Jorge', 'path' => $request->path()]), 404);
});

return $router;
