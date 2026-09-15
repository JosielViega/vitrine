<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use Throwable;

final class StorefrontOperationsRepository implements StorefrontOperationsRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function settings(): array
    {
        $statement = $this->database->connection()->query(
            'SELECT notice_enabled, notice_title, notice_message FROM storefront_settings WHERE id = 1',
        );
        $settings = $statement->fetch();
        if (!is_array($settings)) {
            throw new \RuntimeException('Storefront settings are not initialized.');
        }

        return $settings;
    }

    public function businessHours(): array
    {
        return $this->database->connection()->query(
            'SELECT weekday, enabled, open_time, close_time FROM storefront_business_hours ORDER BY weekday',
        )->fetchAll();
    }

    public function updateNotice(bool $enabled, string $title, string $message): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE storefront_settings SET notice_enabled = :enabled, notice_title = :title, notice_message = :message WHERE id = 1',
        );
        $statement->execute(['enabled' => $enabled ? 1 : 0, 'title' => $title, 'message' => $message]);
        if ($statement->rowCount() === 0) {
            $exists = $this->database->connection()->query('SELECT 1 FROM storefront_settings WHERE id = 1')->fetchColumn();
            if ($exists === false) {
                throw new \RuntimeException('Storefront settings are not initialized.');
            }
        }
    }

    public function updateBusinessHours(array $schedule): void
    {
        $pdo = $this->database->connection();
        $statement = $pdo->prepare(
            'UPDATE storefront_business_hours SET enabled = :enabled, open_time = :open_time, close_time = :close_time WHERE weekday = :weekday',
        );
        $pdo->beginTransaction();
        try {
            foreach ($schedule as $weekday => $day) {
                $statement->execute([
                    'weekday' => $weekday,
                    'enabled' => $day['enabled'] ? 1 : 0,
                    'open_time' => $day['open_time'],
                    'close_time' => $day['close_time'],
                ]);
                if ($statement->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM storefront_business_hours WHERE weekday = :weekday');
                    $exists->execute(['weekday' => $weekday]);
                    if ($exists->fetchColumn() === false) {
                        throw new \RuntimeException('Storefront business hours are not initialized.');
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
