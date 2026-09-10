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
use App\Services\StorefrontVisibilityService;
use Throwable;

final class AdminController
{
    private const SECTIONS = ['categories', 'subcategories', 'products'];
    private const TYPES = ['category', 'subcategory', 'product'];

    public function __construct(
        private readonly Request $request,
        private readonly View $view,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AdminAuthService $auth,
        private readonly StorefrontVisibilityService $visibility,
        private readonly Logger $logger,
    ) {
    }

    public function index(): Response
    {
        if (!$this->auth->check()) {
            return $this->noStore(Response::redirect('/admin/login', 303));
        }

        $section = $this->section($this->request->query('section'));

        return $this->noStore(Response::html($this->view->render('admin/index', [
            'title' => 'Admin da Vitrine',
            'username' => $this->auth->username(),
            'csrfToken' => $this->csrf->token(),
            'section' => $section,
            'dashboard' => $this->visibility->dashboard(),
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
