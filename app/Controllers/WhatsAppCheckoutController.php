<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\WhatsAppCheckoutService;
use Throwable;

final class WhatsAppCheckoutController
{
    public function __construct(
        private readonly Request $request,
        private readonly Csrf $csrf,
        private readonly WhatsAppCheckoutService $checkout,
        private readonly Logger $logger,
    ) {
    }

    public function create(): Response
    {
        if (!$this->csrf->verify($this->request->input('_token'))) {
            return Response::json(['ok' => false, 'code' => 'invalid_csrf', 'message' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403);
        }

        try {
            $result = $this->checkout->checkout($this->request->input('cart'), $this->request->input('service_type'));
            $status = (int) $result['http_status'];
            unset($result['http_status']);

            return Response::json($result, $status);
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected WhatsApp checkout failure.', ['exception' => $exception::class]);

            return Response::json(['ok' => false, 'code' => 'unexpected_error', 'message' => 'Não foi possível finalizar agora. Tente novamente em instantes.'], 500);
        }
    }
}
