<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly array $menu,
    ) {
    }

    public function index(): Response
    {
        return Response::html($this->view->render('pages/home', [
            'title' => 'Bar e Lanchonete São Jorge',
            'menu' => $this->menu,
            'featured' => $this->findProduct('camarao-batata-aipim'),
            'popular' => $this->productsByIds(['carne-sol-aipim', 'batata-frita']),
        ]));
    }

    public function cardapio(): Response
    {
        return Response::html($this->view->render('pages/menu', [
            'title' => 'Cardápio | Bar e Lanchonete São Jorge',
            'menu' => $this->menu,
        ]));
    }

    public function product(string $slug): Response
    {
        $product = $this->findProduct(rawurldecode($slug));
        if ($product === null) {
            return Response::html($this->view->render('pages/404', [
                'title' => 'Produto não encontrado | Bar e Lanchonete São Jorge',
                'path' => '/produto/' . $slug,
            ]), 404);
        }

        $related = array_values(array_filter(
            $this->menu['products'],
            static fn (array $item): bool => $item['id'] !== $product['id'] && $item['category'] === $product['category'],
        ));

        return Response::html($this->view->render('pages/product', [
            'title' => $product['name'] . ' | Bar e Lanchonete São Jorge',
            'product' => $product,
            'related' => array_slice($related, 0, 2),
        ]));
    }

    public function order(): Response
    {
        return Response::html($this->view->render('pages/order', [
            'title' => 'Meu Pedido | Bar e Lanchonete São Jorge',
        ]));
    }

    private function findProduct(string $id): ?array
    {
        foreach ($this->menu['products'] as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function productsByIds(array $ids): array
    {
        $products = [];
        foreach ($ids as $id) {
            $product = $this->findProduct($id);
            if ($product !== null) {
                $products[] = $product;
            }
        }

        return $products;
    }
}
