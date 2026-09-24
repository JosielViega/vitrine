<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Services\BusinessHoursService;
use App\Services\StorefrontCatalogService;
use App\Services\StorefrontOperationsService;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly StorefrontCatalogService $catalog,
        private readonly BusinessHoursService $businessHours,
        private readonly Csrf $csrf,
        private readonly ?StorefrontOperationsService $operations = null,
    ) {
    }

    public function index(): Response
    {
        if (($blocked = $this->blockedResponse()) !== null) {
            return $blocked;
        }
        $menu = $this->catalog->catalog();
        $featured = $this->catalog->featured();

        return Response::html($this->view->render('pages/home', [
            'title' => 'Bar e Lanchonete São Jorge',
            'menu' => $menu,
            'featured' => $featured,
            'popular' => $this->catalog->popular($featured['id'] ?? null),
            'businessStatus' => $this->businessHours->currentStatus(),
        ]));
    }

    public function cardapio(): Response
    {
        if (($blocked = $this->blockedResponse()) !== null) {
            return $blocked;
        }
        return Response::html($this->view->render('pages/menu', [
            'title' => 'Cardápio | Bar e Lanchonete São Jorge',
            'menu' => $this->catalog->catalog(),
        ]));
    }

    public function product(string $slug): Response
    {
        if (($blocked = $this->blockedResponse()) !== null) {
            return $blocked;
        }
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
        ]));
    }

    public function order(): Response
    {
        if (($blocked = $this->blockedResponse()) !== null) {
            return $blocked;
        }
        return Response::html($this->view->render('pages/order', [
            'title' => 'Meu Pedido | Bar e Lanchonete São Jorge',
            'businessStatus' => $this->businessHours->currentStatus(),
            'csrfToken' => $this->csrf->token(),
            'recommendations' => $this->catalog->popular(null, 4),
        ]));
    }

    private function blockedResponse(): ?Response
    {
        if ($this->operations === null || !$this->operations->isBlocked()) {
            return null;
        }

        return Response::html($this->view->render('pages/notice', [
            'title' => 'Aviso | Bar e Lanchonete São Jorge',
            'notice' => $this->operations->notice(),
        ], 'layouts/notice'))->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
