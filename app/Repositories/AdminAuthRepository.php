<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AdminAuthRepository implements AdminAuthRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, username, password_hash FROM admin_users WHERE username = :username LIMIT 1',
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user)) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'password_hash' => (string) $user['password_hash'],
        ];
    }
}
