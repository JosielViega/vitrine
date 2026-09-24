<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AdminAuthService;
use App\Services\ProductImageException;
use App\Services\ProductImageService;
use App\Services\StorefrontVisibilityService;
use App\Services\StorefrontHomeHighlightsService;
use App\Services\StorefrontHomeHighlightsValidationException;
use App\Services\StorefrontOperationsService;
use App\Services\StorefrontOperationsValidationException;
use Throwable;

final class AdminController
{
    private const SECTIONS = ['categories', 'subcategories', 'products', 'images', 'home', 'operations'];
    private const TYPES = ['category', 'subcategory', 'product'];

    public function __construct(
        private readonly Request $request,
        private readonly View $view,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AdminAuthService $auth,
        private readonly StorefrontVisibilityService $visibility,
        private readonly ProductImageService $productImages,
        private readonly StorefrontHomeHighlightsService $homeHighlights,
        private readonly Logger $logger,
        private readonly ?StorefrontOperationsService $operations = null,
    ) {
    }

    public function index(): Response
    {
        if (!$this->auth->check()) {
            return $this->noStore(Response::redirect('/admin/login', 303));
        }

        $section = $this->section($this->request->query('section'));
        $dashboard = $this->visibility->dashboard();
        $dashboard['images'] = $this->productImages->groups();
        $dashboard['home'] = $this->homeHighlights->dashboard();
        if ($this->operations !== null) {
            $dashboard['operations'] = $this->operations->dashboard();
        }

        return $this->noStore(Response::html($this->view->render('admin/index', [
            'title' => 'Admin da Vitrine',
            'username' => $this->auth->username(),
            'csrfToken' => $this->csrf->token(),
            'section' => $section,
            'dashboard' => $dashboard,
            'imageWarnings' => $this->productImages->environmentWarnings(),
            'flashMessages' => $this->session->consumeFlash(),
        ], 'layouts/admin')));
    }

    public function updateVisibility(): Response
    {
        if (!$this->auth->check()) {
            return $this->respondUnauthorized();
        }
        if (!$this->csrf->verify($this->request->input('_token'))) {
            if ($this->request->acceptsJson()) {
                return $this->noStore(Response::json(['ok' => false, 'message' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403));
            }
            return $this->noStore(Response::html('Acesso negado.', 403));
        }

        $type = $this->request->input('type');
        $id = filter_var($this->request->input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $visible = $this->request->input('visible');
        $section = $this->section($this->request->input('section'));
        if (!is_string($type) || !in_array($type, self::TYPES, true) || $id === false || !in_array($visible, ['0', '1', 0, 1], true)) {
            return $this->respondError('Não foi possível atualizar a visibilidade.', 422, $section);
        }

        try {
            $found = $this->visibility->setVisibility($type, (int) $id, (bool) (int) $visible);
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected storefront visibility update failure.', ['exception' => $exception::class]);
            return $this->respondError('Não foi possível atualizar a visibilidade.', 500, $section);
        }
        if (!$found) {
            return $this->respondError('Item não encontrado.', 404, $section);
        }

        $this->session->flash('success', 'Visibilidade atualizada.');
        $redirect = '/admin?section=' . $section;
        if ($this->request->acceptsJson()) {
            return $this->noStore(Response::json(['ok' => true, 'redirect' => $redirect]));
        }
        return $this->noStore(Response::redirect($redirect, 303));
    }

    public function uploadImage(): Response
    {
        if (!$this->auth->check()) {
            return $this->respondUnauthorized();
        }
        if (!$this->csrf->verify($this->request->input('_token'))) {
            return $this->noStore(Response::html('Acesso negado.', 403));
        }

        try {
            $this->productImages->upload((string) $this->request->input('group_id'), $this->request->file('image') ?? []);
            $this->session->flash('success', 'Imagem atualizada.');
        } catch (ProductImageException $exception) {
            return $this->respondError($exception->getMessage(), 422, 'images');
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected product image upload failure.', ['exception' => $exception::class]);
            return $this->respondError('Não foi possível processar a imagem.', 500, 'images');
        }

        return $this->noStore(Response::redirect('/admin?section=images', 303));
    }

    public function removeImage(): Response
    {
        if (!$this->auth->check()) {
            return $this->respondUnauthorized();
        }
        if (!$this->csrf->verify($this->request->input('_token'))) {
            return $this->noStore(Response::html('Acesso negado.', 403));
        }

        try {
            $this->productImages->remove((string) $this->request->input('group_id'));
            $this->session->flash('success', 'Imagem removida.');
        } catch (ProductImageException $exception) {
            return $this->respondError($exception->getMessage(), 422, 'images');
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected product image removal failure.', ['exception' => $exception::class]);
            return $this->respondError('Não foi possível remover a imagem.', 500, 'images');
        }

        return $this->noStore(Response::redirect('/admin?section=images', 303));
    }

    public function updateHomeHighlights(): Response
    {
        if (!$this->auth->check()) {
            return $this->respondUnauthorized();
        }
        if (!$this->csrf->verify($this->request->input('_token'))) {
            return $this->noStore(Response::html('Acesso negado.', 403));
        }

        try {
            $this->homeHighlights->update(
                $this->request->input('featured_product_slug', ''),
                $this->request->input('popular_product_1_slug', ''),
                $this->request->input('popular_product_2_slug', ''),
            );
            $this->session->flash('success', 'Destaques da Home atualizados.');
        } catch (StorefrontHomeHighlightsValidationException $exception) {
            return $this->respondError($exception->getMessage(), 422, 'home');
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected storefront home highlights update failure.', ['exception' => $exception::class]);
            return $this->respondError('Não foi possível salvar os destaques da Home.', 500, 'home');
        }

        return $this->noStore(Response::redirect('/admin?section=home', 303));
    }

    public function updateNotice(): Response
    {
        if (!$this->auth->check()) {
            return $this->respondUnauthorized();
        }
        if (!$this->csrf->verify($this->request->input('_token'))) {
            return $this->noStore(Response::html('Acesso negado.', 403));
        }

        try {
            if ($this->operations === null) {
                throw new \RuntimeException('Storefront operations are unavailable.');
            }
            $this->operations->updateNotice(
                $this->request->input('notice_enabled'),
                $this->request->input('notice_title', ''),
                $this->request->input('notice_message', ''),
            );
            $this->session->flash('success', 'Quadro de aviso atualizado.');
        } catch (StorefrontOperationsValidationException $exception) {
            return $this->respondError($exception->getMessage(), 422, 'operations');
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected storefront notice update failure.', ['exception' => $exception::class]);
            return $this->respondError('Não foi possível salvar o aviso.', 500, 'operations');
        }

        return $this->noStore(Response::redirect('/admin?section=operations', 303));
    }

    public function updateBusinessHours(): Response
    {
        if (!$this->auth->check()) {
            return $this->respondUnauthorized();
        }
        if (!$this->csrf->verify($this->request->input('_token'))) {
            return $this->noStore(Response::html('Acesso negado.', 403));
        }

        $input = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            foreach (['enabled', 'open', 'close'] as $field) {
                $key = 'day_' . $weekday . '_' . $field;
                $input[$key] = $this->request->input($key);
            }
        }

        try {
            if ($this->operations === null) {
                throw new \RuntimeException('Storefront operations are unavailable.');
            }
            $this->operations->updateBusinessHours($input);
            $this->session->flash('success', 'Horário de funcionamento atualizado.');
        } catch (StorefrontOperationsValidationException $exception) {
            return $this->respondError($exception->getMessage(), 422, 'operations');
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected storefront business hours update failure.', ['exception' => $exception::class]);
            return $this->respondError('Não foi possível salvar o horário.', 500, 'operations');
        }

        return $this->noStore(Response::redirect('/admin?section=operations', 303));
    }

    private function respondUnauthorized(): Response
    {
        if ($this->request->acceptsJson()) {
            return $this->noStore(Response::json(['ok' => false, 'message' => 'Autenticação necessária.'], 401));
        }
        return $this->noStore(Response::redirect('/admin/login', 303));
    }

    private function respondError(string $message, int $status, string $section): Response
    {
        if ($this->request->acceptsJson()) {
            return $this->noStore(Response::json(['ok' => false, 'message' => $message], $status));
        }
        $this->session->flash('error', $message);
        return $this->noStore(Response::redirect('/admin?section=' . $section, 303));
    }

    private function section(mixed $section): string
    {
        return is_string($section) && in_array($section, self::SECTIONS, true) ? $section : 'categories';
    }

    private function noStore(Response $response): Response
    {
        return $response->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
