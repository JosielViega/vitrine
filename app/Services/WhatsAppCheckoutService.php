<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use JsonException;

final class WhatsAppCheckoutService
{
    private const MAX_CART_LINES = 30;
    private const MAX_QUANTITY = 99;
    private const MAX_NOTES_LENGTH = 180;
    private const MAX_CART_PAYLOAD_BYTES = 131072;

    private ?string $number;

    public function __construct(
        private readonly BusinessHoursService $businessHours,
        private readonly StorefrontCatalogService $catalog,
        array $config,
        private readonly ?Logger $logger = null,
    ) {
        $digits = preg_replace('/\D+/', '', (string) ($config['number'] ?? '')) ?? '';
        $this->number = preg_match('/^[1-9]\d{9,14}$/', $digits) === 1 ? $digits : null;
    }

    public function normalizedNumber(): ?string
    {
        return $this->number;
    }

    /** @return array<string, mixed> */
    public function checkout(mixed $cartPayload, mixed $serviceType): array
    {
        $businessStatus = $this->businessHours->currentStatus();
        if (!$businessStatus['is_open']) {
            return $this->error(409, 'business_closed', 'Fechado agora. ' . $businessStatus['message'] . '.');
        }

        if ($this->number === null) {
            $this->logger?->error('WhatsApp checkout unavailable: WHATSAPP_NUMBER is missing or invalid.');

            return $this->error(503, 'whatsapp_not_configured', 'A finalização pelo WhatsApp está temporariamente indisponível. Tente novamente mais tarde.');
        }

        $serviceLabels = ['pickup' => 'Retirada no local', 'dine_in' => 'Consumir no local'];
        if (!is_string($serviceType) || !isset($serviceLabels[$serviceType])) {
            return $this->error(422, 'invalid_service_type', 'Escolha retirada no local ou consumo no local.');
        }

        $cart = $this->decodedCart($cartPayload);
        if ($cart === null) {
            return $this->error(422, 'cart_invalid', 'Não foi possível validar seu pedido. Revise os itens e tente novamente.');
        }
        if ($cart === []) {
            return $this->error(422, 'cart_empty', 'Seu pedido está vazio. Adicione itens antes de finalizar.');
        }
        if (count($cart) > self::MAX_CART_LINES) {
            return $this->error(422, 'cart_invalid', 'Seu pedido possui itens demais. Revise o pedido e tente novamente.');
        }

        $authoritativeItems = [];
        $invalidProductIds = [];
        $priceChanged = false;
        $totalCents = 0;

        foreach ($cart as $item) {
            if (!is_array($item)) {
                return $this->error(422, 'cart_invalid', 'Não foi possível validar seu pedido. Revise os itens e tente novamente.');
            }

            $productId = $item['productId'] ?? null;
            $quantity = $item['quantity'] ?? null;
            $notes = $item['notes'] ?? '';
            if (!is_int($productId) || $productId <= 0 || !is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY || !is_string($notes) || !$this->validNotesLength($notes)) {
                return $this->error(422, 'cart_invalid', 'Não foi possível validar seu pedido. Confira quantidades e observações.');
            }

            $resolved = $this->catalog->findVariantByProductId($productId);
            if ($resolved === null) {
                $invalidProductIds[] = $productId;
                continue;
            }

            $product = $resolved['product'];
            $variant = $resolved['variant'];
            $priceCents = (int) $variant['price_cents'];
            $addonResult = $this->authoritativeAddons($item['addons'] ?? [], (array) ($product['addons'] ?? []));
            if ($addonResult === null) {
                return $this->error(422, 'cart_invalid', 'Não foi possível validar os acréscimos do pedido.');
            }

            $addons = $addonResult['addons'];
            $unitPriceCents = $priceCents + array_sum(array_column($addons, 'priceCents'));
            $sanitizedNotes = $this->sanitizeNotes($notes);
            $lineTotal = $unitPriceCents * $quantity;
            $totalCents += $lineTotal;
            $priceChanged = $priceChanged
                || !is_int($item['priceCents'] ?? null)
                || $item['priceCents'] !== $priceCents
                || $addonResult['price_changed'];
            $authoritativeItems[] = [
                'productId' => (int) $variant['product_id'],
                'publicProductId' => (string) $product['id'],
                'name' => (string) $product['name'],
                'variantLabel' => (string) $variant['label'],
                'priceCents' => $priceCents,
                'quantity' => $quantity,
                'addons' => $addons,
                'notes' => $sanitizedNotes,
                'image' => (string) $product['image'],
            ];
        }

        if ($invalidProductIds !== []) {
            return $this->error(409, 'cart_invalid', 'Um ou mais itens do seu pedido não estão mais disponíveis. Revise o pedido.', [
                'invalid_product_ids' => array_values(array_unique($invalidProductIds)),
            ]);
        }

        if ($priceChanged) {
            return $this->error(409, 'cart_changed', 'O cardápio foi atualizado. Confira os valores antes de finalizar.', [
                'cart' => $authoritativeItems,
                'total_cents' => $totalCents,
            ]);
        }

        $message = $this->message($authoritativeItems, $serviceLabels[$serviceType], $totalCents);

        return [
            'ok' => true,
            'http_status' => 200,
            'whatsapp_url' => 'https://wa.me/' . $this->number . '?text=' . rawurlencode($message),
            'total_cents' => $totalCents,
        ];
    }

    /**
     * @param list<array<string, mixed>> $availableAddons
     * @return null|array{addons: list<array{productId: int, name: string, priceCents: int}>, price_changed: bool}
     */
    private function authoritativeAddons(mixed $payload, array $availableAddons): ?array
    {
        if (!is_array($payload) || !array_is_list($payload) || count($payload) > count($availableAddons)) {
            return null;
        }

        $availableById = [];
        foreach ($availableAddons as $addon) {
            $availableById[(int) $addon['product_id']] = $addon;
        }

        $addons = [];
        $seen = [];
        $priceChanged = false;
        foreach ($payload as $submittedAddon) {
            $productId = is_array($submittedAddon) ? ($submittedAddon['productId'] ?? null) : null;
            if (!is_int($productId) || $productId <= 0 || isset($seen[$productId]) || !isset($availableById[$productId])) {
                return null;
            }

            $seen[$productId] = true;
            $addon = $availableById[$productId];
            $priceCents = (int) $addon['price_cents'];
            $priceChanged = $priceChanged
                || !is_int($submittedAddon['priceCents'] ?? null)
                || $submittedAddon['priceCents'] !== $priceCents;
            $addons[] = [
                'productId' => $productId,
                'name' => (string) $addon['name'],
                'priceCents' => $priceCents,
            ];
        }

        usort($addons, static fn (array $left, array $right): int => $left['productId'] <=> $right['productId']);

        return ['addons' => $addons, 'price_changed' => $priceChanged];
    }

    /** @return null|list<mixed> */
    private function decodedCart(mixed $payload): ?array
    {
        if (is_string($payload)) {
            $trimmed = ltrim($payload);
            if (strlen($payload) > self::MAX_CART_PAYLOAD_BYTES || ($trimmed[0] ?? '') !== '[') {
                return null;
            }
            try {
                $payload = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }

        return is_array($payload) && array_is_list($payload) ? $payload : null;
    }

    private function validNotesLength(string $notes): bool
    {
        if (preg_match('//u', $notes) !== 1) {
            return false;
        }

        return preg_match_all('/./us', $notes) <= self::MAX_NOTES_LENGTH;
    }

    private function sanitizeNotes(string $notes): string
    {
        $plain = strip_tags($notes);
        $plain = str_replace(["\r\n", "\r"], "\n", $plain);
        $plain = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $plain) ?? '';
        $plain = str_replace(['*', '_', '~', '`'], '', $plain);

        return trim($plain);
    }

    /** @param list<array<string, mixed>> $items */
    private function message(array $items, string $serviceLabel, int $totalCents): string
    {
        $lines = ['Olá! Gostaria de fazer um pedido na Bar e Lanchonete São Jorge.', '', '*PEDIDO*', ''];
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPriceCents = (int) $item['priceCents'] + array_sum(array_column($item['addons'], 'priceCents'));
            $lineTotal = $unitPriceCents * $quantity;
            $lines[] = $quantity . 'x ' . $item['name'];
            if ($item['variantLabel'] !== '') {
                $lines[] = 'Tamanho: ' . $item['variantLabel'];
            }
            if ($item['addons'] !== []) {
                $lines[] = 'Acréscimos:';
                foreach ($item['addons'] as $addon) {
                    $suffix = $quantity > 1 ? ' por unidade' : '';
                    $lines[] = '- ' . $addon['name'] . ' (+' . $this->currency((int) $addon['priceCents']) . $suffix . ')';
                }
            }
            $lines[] = $quantity === 1
                ? $this->currency($unitPriceCents)
                : $quantity . ' x ' . $this->currency($unitPriceCents) . ' = ' . $this->currency($lineTotal);
            if ($item['notes'] !== '') {
                $lines[] = 'Observação do ' . $item['name'] . ':';
                $lines[] = $item['notes'];
            }
            $lines[] = '';
        }
        $lines[] = '*Total: ' . $this->currency($totalCents) . '*';
        $lines[] = '';
        $lines[] = '*Atendimento:*';
        $lines[] = $serviceLabel;
        $lines[] = '';
        $lines[] = 'Pedido montado pelo cardápio digital.';

        return implode("\n", $lines);
    }

    private function currency(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    /** @param array<string, mixed> $extra @return array<string, mixed> */
    private function error(int $httpStatus, string $code, string $message, array $extra = []): array
    {
        return ['ok' => false, 'http_status' => $httpStatus, 'code' => $code, 'message' => $message] + $extra;
    }
}
