<?php

declare(strict_types=1);

namespace App\Repositories;

interface AdminAuthRepositoryInterface
{
    /** @return array{id: int, username: string, password_hash: string}|null */
    public function findByUsername(string $username): ?array;
}
