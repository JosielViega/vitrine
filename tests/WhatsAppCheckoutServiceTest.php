<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontCatalogRepository;
use App\Repositories\StorefrontOperationsRepositoryInterface;
use App\Services\BusinessHoursService;
use App\Services\StorefrontCatalogService;
use App\Services\StorefrontOperationsService;
use App\Services\WhatsAppCheckoutService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WhatsAppCheckoutServiceTest extends TestCase
{
    public function testAcceptsValidPickupOrder(): void
    {
        $result = $this->service()->checkout([$this->item(82, 7200)], 'pickup');

        self::assertTrue($result['ok']);
        self::assertSame(200, $result['http_status']);
        self::assertStringStartsWith('https://wa.me/5527998586163?text=', $result['whatsapp_url']);
    }

    public function testActiveNoticeHasPrecedenceAndNeverGeneratesUrl(): void
    {
        $result = $this->service('2026-09-10 18:00:00', blocked: true)->checkout([$this->item(82, 7200)], 'pickup');

        self::assertFalse($result['ok']);
        self::assertSame(409, $result['http_status']);
        self::assertSame('storefront_blocked', $result['code']);
        self::assertArrayNotHasKey('whatsapp_url', $result);
    }

    public function testActiveNoticePrecedesClosedBusiness(): void
    {
        $result = $this->service('2026-09-10 21:30:00', blocked: true)->checkout([$this->item(82, 7200)], 'pickup');

        self::assertSame('storefront_blocked', $result['code']);
    }

    public function testClosedBusinessNeverGeneratesUrl(): void
    {
        $result = $this->service('2026-09-10 21:30:00')->checkout([$this->item(82, 7200)], 'pickup');

        self::assertFalse($result['ok']);
        self::assertSame(409, $result['http_status']);
        self::assertSame('business_closed', $result['code']);
        self::assertArrayNotHasKey('whatsapp_url', $result);
    }

    public function testRejectsEmptyCart(): void
    {
        $result = $this->service()->checkout([], 'pickup');

        self::assertSame('cart_empty', $result['code']);
        self::assertSame(422, $result['http_status']);
    }

    public function testRejectsNonexistentProductId(): void
    {
        $result = $this->service()->checkout([$this->item(999, 100)], 'pickup');

        self::assertSame('cart_invalid', $result['code']);
        self::assertSame([999], $result['invalid_product_ids']);
    }

    public function testRejectsInactiveProduct(): void
    {
        $result = $this->service()->checkout([$this->item(90, 100)], 'pickup');

        self::assertSame('cart_invalid', $result['code']);
        self::assertSame([90], $result['invalid_product_ids']);
    }

    #[DataProvider('invalidQuantityProvider')]
    public function testRejectsInvalidQuantity(int $quantity): void
    {
        $result = $this->service()->checkout([$this->item(82, 7200, $quantity)], 'pickup');

        self::assertSame('cart_invalid', $result['code']);
        self::assertSame(422, $result['http_status']);
    }

    public static function invalidQuantityProvider(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'above limit' => [100]];
    }

    public function testRejectsNotesAbove180Characters(): void
    {
        $item = $this->item(82, 7200);
        $item['notes'] = str_repeat('a', 181);

        self::assertSame('cart_invalid', $this->service()->checkout([$item], 'pickup')['code']);
    }

    public function testPickupLabelIsIncluded(): void
    {
        self::assertStringContainsString("*Atendimento:*\nRetirada no local", $this->message($this->service()->checkout([$this->item(82, 7200)], 'pickup')));
    }

    public function testDineInLabelIsIncluded(): void
    {
        self::assertStringContainsString("*Atendimento:*\nConsumir no local", $this->message($this->service()->checkout([$this->item(82, 7200)], 'dine_in')));
    }

    public function testRejectsInvalidServiceType(): void
    {
        $result = $this->service()->checkout([$this->item(82, 7200)], 'delivery');

        self::assertSame('invalid_service_type', $result['code']);
        self::assertSame(422, $result['http_status']);
    }

    public function testRejectsManipulatedClientPriceAndReturnsAuthoritativeCart(): void
    {
        $result = $this->service()->checkout([$this->item(82, 1)], 'pickup');

        self::assertSame('cart_changed', $result['code']);
        self::assertSame(7200, $result['cart'][0]['priceCents']);
        self::assertSame(7200, $result['total_cents']);
        self::assertArrayNotHasKey('whatsapp_url', $result);
    }

    public function testDetectsPriceChangedSinceCartWasBuilt(): void
    {
        $result = $this->service()->checkout([$this->item(82, 7000)], 'pickup');

        self::assertSame(409, $result['http_status']);
        self::assertSame('O cardápio foi atualizado. Confira os valores antes de finalizar.', $result['message']);
    }

    public function testCalculatesTotalOnlyWithServerCents(): void
    {
        $result = $this->service()->checkout([$this->item(82, 7200), $this->item(3, 700, 2)], 'pickup');

        self::assertSame(8600, $result['total_cents']);
        self::assertStringContainsString('*Total: R$ 86,00*', $this->message($result));
    }

    public function testRendersMeiaVariant(): void
    {
        self::assertStringContainsString('Tamanho: Meia', $this->message($this->service()->checkout([$this->item(82, 7200)], 'pickup')));
    }

    public function testRendersInteiraVariant(): void
    {
        self::assertStringContainsString('Tamanho: Inteira', $this->message($this->service()->checkout([$this->item(79, 8500)], 'pickup')));
    }

    public function testSimpleProductDoesNotRenderArtificialUnitSize(): void
    {
        $message = $this->message($this->service()->checkout([$this->item(3, 700)], 'pickup'));

        self::assertStringNotContainsString('Tamanho:', $message);
        self::assertStringNotContainsString('Unidade', $message);
    }

    public function testBuildsFinalMessageWithQuantityAndSanitizedNotes(): void
    {
        $item = $this->item(82, 7200, 2);
        $item['notes'] = '<b>Sem sal</b> *urgente*';
        $message = $this->message($this->service()->checkout([$item], 'pickup'));

        self::assertStringContainsString('2x Camarão c/ Batata e Aipim', $message);
        self::assertStringContainsString('2 x R$ 72,00 = R$ 144,00', $message);
        self::assertStringContainsString("Observação do Camarão c/ Batata e Aipim:\nSem sal urgente", $message);
        self::assertStringContainsString('Pedido montado pelo cardápio digital.', $message);
    }

    public function testUrlUsesBackendEncoding(): void
    {
        $result = $this->service()->checkout([$this->item(82, 7200)], 'pickup');

        self::assertStringContainsString('%C3%A1', $result['whatsapp_url']);
        self::assertStringNotContainsString('Olá!', $result['whatsapp_url']);
        self::assertStringStartsWith('Olá!', $this->message($result));
    }

    public function testNormalizesConfiguredNumberToDigits(): void
    {
        self::assertSame('5527998586163', $this->service(number: '+55 (27) 99858-6163')->normalizedNumber());
    }

    public function testInvalidNumberFailsSafelyAtCheckout(): void
    {
        $result = $this->service(number: '')->checkout([$this->item(82, 7200)], 'pickup');

        self::assertSame('whatsapp_not_configured', $result['code']);
        self::assertSame(503, $result['http_status']);
    }

    public function testRejectsMoreThanThirtyCartLines(): void
    {
        $result = $this->service()->checkout(array_fill(0, 31, $this->item(82, 7200)), 'pickup');

        self::assertSame('cart_invalid', $result['code']);
    }

    #[DataProvider('validAddonCombinationProvider')]
    public function testAcceptsEveryAddonCombination(array $addons, int $expectedTotal): void
    {
        $result = $this->service()->checkout([$this->itemWithAddons(82, 7200, $addons)], 'pickup');

        self::assertTrue($result['ok']);
        self::assertSame($expectedTotal, $result['total_cents']);
    }

    public static function validAddonCombinationProvider(): array
    {
        return [
            'none' => [[], 7200],
            'Bacon' => [[['productId' => 117, 'name' => 'Alterado', 'priceCents' => 600]], 7800],
            'Mussarela' => [[['productId' => 74, 'name' => 'Alterado', 'priceCents' => 600]], 7800],
            'both' => [[
                ['productId' => 117, 'name' => 'Alterado', 'priceCents' => 600],
                ['productId' => 74, 'name' => 'Alterado', 'priceCents' => 600],
            ], 8400],
        ];
    }

    public function testRejectsAddonOnIneligibleProduct(): void
    {
        $result = $this->service()->checkout([
            $this->itemWithAddons(3, 700, [$this->addon(117)]),
        ], 'pickup');

        self::assertSame('cart_invalid', $result['code']);
    }

    #[DataProvider('directAddonProvider')]
    public function testRejectsAddonAsMainProduct(int $productId): void
    {
        $result = $this->service()->checkout([$this->item($productId, 600)], 'pickup');

        self::assertSame('cart_invalid', $result['code']);
        self::assertSame([$productId], $result['invalid_product_ids']);
    }

    public static function directAddonProvider(): array
    {
        return ['Bacon' => [117], 'Mussarela' => [74]];
    }

    public function testRejectsUnknownAndDuplicateAddons(): void
    {
        $unknown = $this->service()->checkout([
            $this->itemWithAddons(82, 7200, [$this->addon(999)]),
        ], 'pickup');
        $duplicate = $this->service()->checkout([
            $this->itemWithAddons(82, 7200, [$this->addon(117), $this->addon(117)]),
        ], 'pickup');

        self::assertSame('cart_invalid', $unknown['code']);
        self::assertSame('cart_invalid', $duplicate['code']);
    }

    #[DataProvider('malformedAddonProvider')]
    public function testRejectsMalformedAddonPayload(mixed $addons): void
    {
        $item = $this->item(82, 7200);
        $item['addons'] = $addons;

        self::assertSame('cart_invalid', $this->service()->checkout([$item], 'pickup')['code']);
    }

    public static function malformedAddonProvider(): array
    {
        return [
            'not a list' => [['addon' => ['productId' => 117, 'priceCents' => 600]]],
            'not an object' => [[117]],
            'invalid id' => [[['productId' => -1, 'priceCents' => 600]]],
            'nested id' => [[['productId' => [117], 'priceCents' => 600]]],
            'too many' => [[
                ['productId' => 117, 'priceCents' => 600],
                ['productId' => 74, 'priceCents' => 600],
                ['productId' => 999, 'priceCents' => 600],
            ]],
        ];
    }

    #[DataProvider('unavailableAddonProvider')]
    public function testRejectsUnavailableAddon(int $active, int $visible): void
    {
        $catalog = $this->catalog($active, $visible);
        $result = $this->service(catalog: $catalog)->checkout([
            $this->itemWithAddons(82, 7200, [$this->addon(117)]),
        ], 'pickup');

        self::assertSame('cart_invalid', $result['code']);
    }

    public static function unavailableAddonProvider(): array
    {
        return ['inactive' => [0, 1], 'hidden' => [1, 0]];
    }

    public function testChangedAddonPriceReturnsAuthoritativeCart(): void
    {
        $result = $this->service()->checkout([
            $this->itemWithAddons(82, 7200, [$this->addon(117, 500)]),
        ], 'pickup');

        self::assertSame('cart_changed', $result['code']);
        self::assertSame(600, $result['cart'][0]['addons'][0]['priceCents']);
        self::assertSame('Bacon', $result['cart'][0]['addons'][0]['name']);
        self::assertSame(7800, $result['total_cents']);
    }

    public function testQuantityMultipliesBaseAndAddonsAndMessageIsExplicit(): void
    {
        $result = $this->service()->checkout([
            $this->itemWithAddons(82, 7200, [$this->addon(117), $this->addon(74)], 2),
        ], 'pickup');
        $message = $this->message($result);

        self::assertSame(16800, $result['total_cents']);
        self::assertStringContainsString("Acréscimos:
- Mussarela (+R$ 6,00 por unidade)
- Bacon (+R$ 6,00 por unidade)", $message);
        self::assertStringContainsString('2 x R$ 84,00 = R$ 168,00', $message);
        self::assertStringContainsString('*Total: R$ 168,00*', $message);
    }

    public function testLegacyCartItemWithoutAddonsRemainsValid(): void
    {
        $item = $this->item(82, 7200);
        self::assertArrayNotHasKey('addons', $item);

        $result = $this->service()->checkout([$item], 'pickup');

        self::assertTrue($result['ok']);
        self::assertSame(7200, $result['total_cents']);
    }

    private function service(
        string $dateTime = '2026-09-10 18:00:00',
        string $number = '5527998586163',
        ?StorefrontCatalogService $catalog = null,
        bool $blocked = false,
    ): WhatsAppCheckoutService
    {
        $now = new DateTimeImmutable($dateTime, new DateTimeZone('America/Sao_Paulo'));
        $hours = new BusinessHoursService(require dirname(__DIR__) . '/config/business.php', static fn (): DateTimeImmutable => $now);

        $operationsRepository = new class($blocked) implements StorefrontOperationsRepositoryInterface {
            public function __construct(private bool $blocked) {}
            public function settings(): array { return ['notice_enabled' => $this->blocked ? 1 : 0, 'notice_title' => '', 'notice_message' => '']; }
            public function businessHours(): array { return []; }
            public function updateNotice(bool $enabled, string $title, string $message): void {}
            public function updateBusinessHours(array $schedule): void {}
        };
        $operations = new StorefrontOperationsService($operationsRepository);

        return new WhatsAppCheckoutService($hours, $catalog ?? $this->catalog(), ['number' => $number], null, $operations);
    }

    private function catalog(int $baconActive = 1, int $baconVisible = 1): StorefrontCatalogService
    {
        $categories = [['id' => 1, 'name' => 'Comidas', 'sort_order' => 0, 'active' => 1], ['id' => 2, 'name' => 'Bebidas', 'sort_order' => 0, 'active' => 1]];
        $subcategories = [
            ['id' => 1, 'category_id' => 1, 'name' => 'Porções', 'sort_order' => 0, 'active' => 1],
            ['id' => 2, 'category_id' => 2, 'name' => 'Cervejas', 'sort_order' => 0, 'active' => 1],
            ['id' => 3, 'category_id' => 1, 'name' => 'Acréscimo', 'sort_order' => 1, 'active' => 1],
        ];
        $products = [
            ['id' => 79, 'subcategory_id' => 1, 'category_id' => 1, 'name' => 'Camarão c/ Batata e Aipim', 'price_cents' => 8500, 'kind' => 'kitchen', 'active' => 1],
            ['id' => 82, 'subcategory_id' => 1, 'category_id' => 1, 'name' => 'Meia: Camarão c/ Batata e Aipim', 'price_cents' => 7200, 'kind' => 'kitchen', 'active' => 1],
            ['id' => 3, 'subcategory_id' => 2, 'category_id' => 2, 'name' => 'Brahma Latão', 'price_cents' => 700, 'kind' => 'regular', 'active' => 1],
            ['id' => 90, 'subcategory_id' => 1, 'category_id' => 1, 'name' => 'Produto inativo', 'price_cents' => 100, 'kind' => 'regular', 'active' => 0],
            ['id' => 117, 'subcategory_id' => 3, 'category_id' => 1, 'name' => 'Bacon', 'price_cents' => 600, 'kind' => 'regular', 'active' => $baconActive, 'storefront_visible' => $baconVisible],
            ['id' => 74, 'subcategory_id' => 3, 'category_id' => 1, 'name' => 'Mussarela', 'price_cents' => 600, 'kind' => 'regular', 'active' => 1, 'storefront_visible' => 1],
        ];
        $repository = new class($categories, $subcategories, $products) implements StorefrontCatalogRepository {
            public function __construct(private array $categories, private array $subcategories, private array $products) {}
            public function activeCategories(): array { return $this->categories; }
            public function activeSubcategories(): array { return $this->subcategories; }
            public function activeProducts(): array { return $this->products; }
        };

        return new StorefrontCatalogService($repository, [
            'addons' => [
                'product_ids' => [117, 74],
                'eligible_subcategories' => ['porcoes'],
            ],
            'fallback_image' => '/assets/images/products/mixed-portion-placeholder.jpg',
        ]);
    }

    private function item(int $productId, int $priceCents, int $quantity = 1): array
    {
        return ['productId' => $productId, 'publicProductId' => 'ignored', 'name' => 'Manipulado', 'variantLabel' => 'Manipulado', 'priceCents' => $priceCents, 'quantity' => $quantity, 'notes' => ''];
    }

    private function itemWithAddons(int $productId, int $priceCents, array $addons, int $quantity = 1): array
    {
        return [...$this->item($productId, $priceCents, $quantity), 'addons' => $addons];
    }

    private function addon(int $productId, int $priceCents = 600): array
    {
        return ['productId' => $productId, 'name' => 'Valor enviado pelo cliente', 'priceCents' => $priceCents];
    }

    private function message(array $result): string
    {
        parse_str((string) parse_url($result['whatsapp_url'], PHP_URL_QUERY), $query);

        return (string) ($query['text'] ?? '');
    }
}
