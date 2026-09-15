<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StorefrontOperationsRepositoryInterface;
use Closure;

final class StorefrontOperationsService
{
    private const DAYS = [
        1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta',
        5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo',
    ];

    public function __construct(
        private readonly StorefrontOperationsRepositoryInterface $repository,
        private readonly string $timezone = 'America/Sao_Paulo',
        private readonly ?Closure $clock = null,
    ) {
    }

    /** @return array{enabled: bool, title: string, message: string} */
    public function notice(): array
    {
        $settings = $this->repository->settings();

        return [
            'enabled' => (int) $settings['notice_enabled'] === 1,
            'title' => (string) $settings['notice_title'],
            'message' => (string) $settings['notice_message'],
        ];
    }

    public function isBlocked(): bool
    {
        return $this->notice()['enabled'];
    }

    /** @return list<array{weekday: int, label: string, enabled: bool, open_time: string, close_time: string}> */
    public function schedule(): array
    {
        $byWeekday = [];
        foreach ($this->repository->businessHours() as $row) {
            $weekday = (int) $row['weekday'];
            if (isset(self::DAYS[$weekday])) {
                $byWeekday[$weekday] = $row;
            }
        }

        $schedule = [];
        foreach (self::DAYS as $weekday => $label) {
            $row = $byWeekday[$weekday] ?? null;
            if ($row === null) {
                throw new \RuntimeException('Storefront business hours are not initialized.');
            }
            $schedule[] = [
                'weekday' => $weekday,
                'label' => $label,
                'enabled' => (int) $row['enabled'] === 1,
                'open_time' => $this->normalizeDatabaseTime($row['open_time']),
                'close_time' => $this->normalizeDatabaseTime($row['close_time']),
            ];
        }

        return $schedule;
    }

    public function businessHoursService(): BusinessHoursService
    {
        $schedule = [];
        foreach ($this->schedule() as $day) {
            if ($day['enabled']) {
                $schedule[$day['weekday']] = [['open' => $day['open_time'], 'close' => $day['close_time']]];
            }
        }

        return new BusinessHoursService(['timezone' => $this->timezone, 'schedule' => $schedule], $this->clock);
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $notice = $this->notice();

        return [
            'notice' => $notice,
            'schedule' => $this->schedule(),
            'business_status' => $this->businessHoursService()->currentStatus(),
            'storefront_status' => $notice['enabled'] ? 'blocked' : 'available',
        ];
    }

    public function updateNotice(mixed $enabled, mixed $title, mixed $message): void
    {
        $enabled = in_array($enabled, ['1', 1, true, 'on'], true);
        if (!is_string($title) || !is_string($message)) {
            throw new StorefrontOperationsValidationException('Revise o título e a mensagem do aviso.');
        }
        $title = trim($title);
        $message = trim($message);
        $this->assertText($title, 120, 'O título do aviso');
        $this->assertText($message, 1500, 'A mensagem do aviso');
        if ($enabled && $title === '') {
            throw new StorefrontOperationsValidationException('Informe o título antes de ativar o aviso.');
        }
        if ($enabled && $message === '') {
            throw new StorefrontOperationsValidationException('Informe a mensagem antes de ativar o aviso.');
        }

        $this->repository->updateNotice($enabled, $title, $message);
    }

    /** @param array<string, mixed> $input */
    public function updateBusinessHours(array $input): void
    {
        $schedule = [];
        foreach (self::DAYS as $weekday => $label) {
            $enabled = in_array($input['day_' . $weekday . '_enabled'] ?? null, ['1', 1, true, 'on'], true);
            if (!$enabled) {
                $schedule[$weekday] = ['enabled' => false, 'open_time' => null, 'close_time' => null];
                continue;
            }
            $open = $input['day_' . $weekday . '_open'] ?? null;
            $close = $input['day_' . $weekday . '_close'] ?? null;
            if (!is_string($open) || !$this->validTime($open) || !is_string($close) || !$this->validTime($close)) {
                throw new StorefrontOperationsValidationException("Informe horários válidos para {$label}.");
            }
            if ($open >= $close) {
                throw new StorefrontOperationsValidationException("Em {$label}, o fechamento deve ser depois da abertura.");
            }
            $schedule[$weekday] = ['enabled' => true, 'open_time' => $open, 'close_time' => $close];
        }

        $this->repository->updateBusinessHours($schedule);
    }

    private function assertText(string $value, int $maximum, string $label): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new StorefrontOperationsValidationException("{$label} deve usar texto UTF-8 válido.");
        }
        if (preg_match_all('/./us', $value) > $maximum) {
            throw new StorefrontOperationsValidationException("{$label} deve ter no máximo {$maximum} caracteres.");
        }
    }

    private function validTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }

    private function normalizeDatabaseTime(mixed $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }
        $time = (string) $time;

        return preg_match('/^(\d{2}:\d{2})(?::\d{2})$/', $time, $matches) === 1 ? $matches[1] : $time;
    }
}
