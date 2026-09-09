<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;
use App\Services\StorefrontCatalogService;

final class HomeController
{
    public function __construct(private readonly View $view, private readonly StorefrontCatalogService $catalog)
    {
    }

    public function index(): Response
    {
        $menu = $this->catalog->catalog();
        $featured = $this->catalog->featured();

        return Response::html($this->view->render('pages/home', [
            'title' => 'Bar e Lanchonete São Jorge',
            'menu' => $menu,
            'featured' => $featured,
            'popular' => $this->catalog->popular($featured['id'] ?? null),
        ]));
    }

    public function cardapio(): Response
    {
        return Response::html($this->view->render('pages/menu', [
            'title' => 'Cardápio | Bar e Lanchonete São Jorge',
            'menu' => $this->catalog->catalog(),
        ]));
    }

    public function product(string $slug): Response
    {
        $product = $this->catalog->findBySlug(rawurldecode($slug));
        if ($product === null) {
            return Response::html($this->view->render('pages/404', [
                'title' => 'Produto não encontrado | Bar e Lanchonete São Jorge',
                'path' => '/produto/' . $slug,
            ]), 404);
        }

        return Response::html($this->view->render('pages/product', [
            'title' => $product['name'] . ' | Bar e Lanchonete São Jorge',
            'product' => $product,
            'related' => $this->catalog->related($product),
        ]));
    }

    public function order(): Response
    {
        return Response::html($this->view->render('pages/order', ['title' => 'Meu Pedido | Bar e Lanchonete São Jorge']));
    }
}
