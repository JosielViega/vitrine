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
use Throwable;

final class AdminAuthController
{
    public function __construct(
        private readonly Request $request,
        private readonly View $view,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AdminAuthService $auth,
        private readonly Logger $logger,
    ) {
    }

    public function loginForm(): Response
    {
        if ($this->auth->check()) {
            return $this->noStore(Response::redirect('/admin', 303));
        }

        return $this->noStore(Response::html($this->view->render('admin/login', [
            'title' => 'Entrar | Admin da Vitrine',
            'csrfToken' => $this->csrf->token(),
            'flashMessages' => $this->session->consumeFlash(),
        ], 'layouts/admin')));
    }

    public function login(): Response
    {
        if (!$this->csrf->verify($this->request->input('_token'))) {
            return $this->noStore(Response::html('Acesso negado.', 403));
        }

        $username = $this->request->input('username');
        $password = $this->request->input('password');
        if (!is_string($username) || !is_string($password)) {
            return $this->invalidLogin();
        }

        $username = trim($username);
        if ($username === '' || strlen($username) > 100 || $password === '' || strlen($password) > 4096) {
            return $this->invalidLogin();
        }

        try {
            $result = $this->auth->attemptLogin($username, $password);
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected admin login failure.', ['exception' => $exception::class]);
            $this->session->flash('error', 'Não foi possível entrar agora. Tente novamente em instantes.');

            return $this->noStore(Response::redirect('/admin/login', 303));
        }

        if ($result === AdminAuthService::LOGIN_SUCCESS) {
            return $this->noStore(Response::redirect('/admin', 303));
        }

        $message = $result === AdminAuthService::LOGIN_RATE_LIMITED
            ? 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'
            : 'Usuário ou senha inválidos.';
        $this->session->flash('error', $message);

        return $this->noStore(Response::redirect('/admin/login', 303));
    }

    public function logout(): Response
    {
        if (!$this->auth->check()) {
            return $this->noStore(Response::redirect('/admin/login', 303));
        }
        if (!$this->csrf->verify($this->request->input('_token'))) {
            return $this->noStore(Response::html('Acesso negado.', 403));
        }

        $this->auth->logout();

        return $this->noStore(Response::redirect('/admin/login', 303));
    }

    private function invalidLogin(): Response
    {
        $this->session->flash('error', 'Usuário ou senha inválidos.');

        return $this->noStore(Response::redirect('/admin/login', 303));
    }

    private function noStore(Response $response): Response
    {
        return $response->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
