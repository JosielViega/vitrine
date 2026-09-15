<?php

declare(strict_types=1);

namespace App\Repositories;

interface StorefrontOperationsRepositoryInterface
{
    /** @return array{notice_enabled: int|string, notice_title: string, notice_message: string} */
    public function settings(): array;

    /** @return list<array{weekday: int|string, enabled: int|string, open_time: ?string, close_time: ?string}> */
    public function businessHours(): array;

    public function updateNotice(bool $enabled, string $title, string $message): void;

    /** @param array<int, array{enabled: bool, open_time: ?string, close_time: ?string}> $schedule */
    public function updateBusinessHours(array $schedule): void;
}
