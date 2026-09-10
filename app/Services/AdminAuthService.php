<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Repositories\AdminAuthRepositoryInterface;

final class AdminAuthService
{
    public const LOGIN_SUCCESS = 'success';
    public const LOGIN_INVALID = 'invalid';
    public const LOGIN_RATE_LIMITED = 'rate_limited';

    private const AUTHENTICATED_KEY = 'admin_authenticated';
    private const USER_ID_KEY = 'admin_user_id';
    private const USERNAME_KEY = 'admin_username';
    private const ATTEMPTS_KEY = 'admin_login_attempts';
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 600;
    private const DUMMY_HASH = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

    public function __construct(
        private readonly AdminAuthRepositoryInterface $repository,
        private readonly Session $session,
        private readonly ?\Closure $clock = null,
        private readonly ?\Closure $passwordVerifier = null,
    ) {
    }

    public function attemptLogin(string $username, string $password): string
    {
        $attempts = $this->recentAttempts();
        if (count($attempts) >= self::MAX_ATTEMPTS) {
            $this->session->put(self::ATTEMPTS_KEY, $attempts);

            return self::LOGIN_RATE_LIMITED;
        }

        $user = $this->repository->findByUsername($username);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $passwordValid = $this->verifyPassword($password, $hash);
        if ($user === null || !$passwordValid) {
            $attempts[] = $this->now();
            $this->session->put(self::ATTEMPTS_KEY, $attempts);

            return self::LOGIN_INVALID;
        }

        if (!$this->session->regenerate()) {
            $this->clearAuthentication();

            return self::LOGIN_INVALID;
        }

        $this->session->forget(self::ATTEMPTS_KEY);
        $this->session->put(self::AUTHENTICATED_KEY, true);
        $this->session->put(self::USER_ID_KEY, (int) $user['id']);
        $this->session->put(self::USERNAME_KEY, (string) $user['username']);

        return self::LOGIN_SUCCESS;
    }

    public function check(): bool
    {
        return $this->session->get(self::AUTHENTICATED_KEY) === true
            && $this->userId() !== null
            && $this->username() !== null;
    }

    public function userId(): ?int
    {
        $id = $this->session->get(self::USER_ID_KEY);

        return is_int($id) && $id > 0 ? $id : null;
    }

    public function username(): ?string
    {
        $username = $this->session->get(self::USERNAME_KEY);

        return is_string($username) && $username !== '' ? $username : null;
    }

    public function logout(): void
    {
        $this->clearAuthentication();
        $this->session->forget(self::ATTEMPTS_KEY);
        $this->session->regenerate();
    }

    private function clearAuthentication(): void
    {
        $this->session->forget(self::AUTHENTICATED_KEY);
        $this->session->forget(self::USER_ID_KEY);
        $this->session->forget(self::USERNAME_KEY);
    }

    private function verifyPassword(string $password, string $hash): bool
    {
        if ($this->passwordVerifier instanceof \Closure) {
            return (bool) ($this->passwordVerifier)($password, $hash);
        }

        return password_verify($password, $hash);
    }

    private function recentAttempts(): array
    {
        $attempts = $this->session->get(self::ATTEMPTS_KEY, []);
        if (!is_array($attempts)) {
            return [];
        }

        $threshold = $this->now() - self::WINDOW_SECONDS;

        return array_values(array_filter($attempts, static fn (mixed $attempt): bool => is_int($attempt) && $attempt > $threshold));
    }

    private function now(): int
    {
        return $this->clock instanceof \Closure ? (int) ($this->clock)() : time();
    }
}
