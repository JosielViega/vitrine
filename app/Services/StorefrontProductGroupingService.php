<?php

declare(strict_types=1);

namespace App\Services;

final class StorefrontProductGroupingService
{
    public function __construct(private readonly array $aliases = [])
    {
    }

    /** @return list<array{key: string, name: string, primary_row: array, secondary_row: ?array, variants: list<array{label: string, row: array}>}> */
    public function group(array $rows): array
    {
        $bySubcategory = [];
        foreach ($rows as $row) {
            $bySubcategory[(int) $row['subcategory_id']][] = $row;
        }

        $groups = [];
        foreach ($bySubcategory as $subcategoryRows) {
            $halves = [];
            foreach ($subcategoryRows as $row) {
                $baseName = $this->halfBaseName((string) $row['name']);
                if ($baseName !== null) {
                    $halves[$this->halfMatchSlug($baseName)][] = $row;
                }
            }

            $consumed = [];
            foreach ($subcategoryRows as $row) {
                $productId = (int) $row['id'];
                if (isset($consumed[$productId]) || $this->halfBaseName((string) $row['name']) !== null) {
                    continue;
                }
                $name = trim((string) $row['name']);
                $half = $halves[$this->slug($name)][0] ?? null;
                $variants = [['label' => $half === null ? '' : 'Inteira', 'row' => $row]];
                if ($half !== null) {
                    $variants[] = ['label' => 'Meia', 'row' => $half];
                    $consumed[(int) $half['id']] = true;
                }
                $groups[] = $this->result($name, $row, $half, $variants);
                $consumed[$productId] = true;
            }

            foreach ($subcategoryRows as $row) {
                $productId = (int) $row['id'];
                $name = $this->halfBaseName((string) $row['name']);
                if ($name === null || isset($consumed[$productId])) {
                    continue;
                }
                $groups[] = $this->result($name, $row, null, [['label' => 'Meia', 'row' => $row]]);
            }
        }

        return $groups;
    }

    public function slug(string $value): string
    {
        $normalized = strtr(trim($value), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C',
        ]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $slug = strtolower($ascii === false ? $value : $ascii);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    }

    private function result(string $name, array $primary, ?array $secondary, array $variants): array
    {
        return [
            'key' => 'product-' . (int) $primary['id'],
            'name' => $name,
            'primary_row' => $primary,
            'secondary_row' => $secondary,
            'variants' => $variants,
        ];
    }

    private function halfBaseName(string $name): ?string
    {
        foreach (['/^\s*meia\s*:\s*(?<base>.+?)\s*$/iu', '/^(?<base>.+?)\s+-\s*meia\s*$/iu'] as $pattern) {
            if (preg_match($pattern, $name, $matches) === 1 && trim($matches['base']) !== '') {
                return trim($matches['base']);
            }
        }

        return null;
    }

    private function halfMatchSlug(string $baseName): string
    {
        $slug = $this->slug($baseName);

        return $this->slug((string) ($this->aliases[$slug] ?? $slug));
    }
}
