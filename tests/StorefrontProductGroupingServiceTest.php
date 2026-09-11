<?php

declare(strict_types=1);

namespace Tests;

use App\Services\StorefrontProductGroupingService;
use PHPUnit\Framework\TestCase;

final class StorefrontProductGroupingServiceTest extends TestCase
{
    public function testGroupsWholeAndHalfIntoOneAdministrativeProduct(): void
    {
        $groups = $this->grouping()->group([
            $this->row(79, 'Camarão c/ Batata e Aipim'),
            $this->row(82, 'Meia: Camarão c/ Batata e Aipim'),
        ]);

        self::assertCount(1, $groups);
        self::assertSame('product-79', $groups[0]['key']);
        self::assertSame([79, 82], array_map(static fn (array $variant): int => $variant['row']['id'], $groups[0]['variants']));
        self::assertSame(['Inteira', 'Meia'], array_column($groups[0]['variants'], 'label'));
    }

    public function testKnownAliasStillGroups(): void
    {
        $groups = $this->grouping()->group([
            $this->row(127, 'Porção de carne'),
            $this->row(130, 'Meia: Porção Carne'),
        ]);

        self::assertCount(1, $groups);
        self::assertSame([127, 130], array_map(static fn (array $variant): int => $variant['row']['id'], $groups[0]['variants']));
    }

    public function testSingleProductCreatesOneGroupWithOneId(): void
    {
        $groups = $this->grouping()->group([$this->row(112, 'Pescadinha')]);

        self::assertCount(1, $groups);
        self::assertCount(1, $groups[0]['variants']);
        self::assertSame(112, $groups[0]['variants'][0]['row']['id']);
    }

    private function grouping(): StorefrontProductGroupingService
    {
        return new StorefrontProductGroupingService(['porcao-carne' => 'porcao-de-carne']);
    }

    private function row(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name, 'subcategory_id' => 1];
    }
}
