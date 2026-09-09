<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class BusinessHoursService
{
    private DateTimeZone $timezone;

    /** @var array<int, list<array{open: string, close: string}>> */
    private array $schedule = [];

    /** @param array{timezone?: string, schedule?: array<int, list<array{open: string, close: string}>>} $config */
    public function __construct(array $config, private readonly ?Closure $clock = null)
    {
        $this->timezone = new DateTimeZone((string) ($config['timezone'] ?? 'America/Sao_Paulo'));

        foreach ((array) ($config['schedule'] ?? []) as $weekday => $periods) {
            $weekday = (int) $weekday;
            if ($weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException('Business schedule weekdays must use ISO-8601 values from 1 to 7.');
            }
            foreach ($periods as $period) {
                $open = $this->validatedTime((string) ($period['open'] ?? ''));
                $close = $this->validatedTime((string) ($period['close'] ?? ''));
                if ($open >= $close) {
                    throw new InvalidArgumentException('Business schedule periods must close after they open.');
                }
                $this->schedule[$weekday][] = ['open' => $open, 'close' => $close];
            }
            usort($this->schedule[$weekday], static fn (array $left, array $right): int => $left['open'] <=> $right['open']);
        }
        ksort($this->schedule);
    }

    /** @return array<string, mixed> */
    public function currentStatus(): array
    {
        $clock = $this->clock;
        $current = $clock === null ? new DateTimeImmutable('now', $this->timezone) : $clock();
        if (!$current instanceof DateTimeImmutable) {
            throw new RuntimeException('The business-hours clock must return DateTimeImmutable.');
        }

        return $this->statusAt($current);
    }

    /** @return array<string, mixed> */
    public function statusAt(DateTimeImmutable $at): array
    {
        $current = $at->setTimezone($this->timezone);
        $weekday = (int) $current->format('N');

        foreach ($this->schedule[$weekday] ?? [] as $period) {
            $opening = $this->atTime($current, $period['open']);
            $closing = $this->atTime($current, $period['close']);
            if ($current >= $opening && $current < $closing) {
                return $this->status($current, true, $opening, $closing, null);
            }
        }

        return $this->status($current, false, null, null, $this->nextOpeningAfter($current));
    }

    /** @return array<string, mixed> */
    private function status(DateTimeImmutable $current, bool $isOpen, ?DateTimeImmutable $opening, ?DateTimeImmutable $closing, ?DateTimeImmutable $nextOpening): array
    {
        $message = $isOpen
            ? 'Hoje até ' . $this->formatTime($closing)
            : $this->nextOpeningMessage($current, $nextOpening);

        return [
            'is_open' => $isOpen,
            'timezone' => $this->timezone->getName(),
            'current_at' => $current,
            'current_open_at' => $opening,
            'current_close_at' => $closing,
            'next_open_at' => $nextOpening,
            'status_label' => $isOpen ? 'Aberto agora' : 'Fechado agora',
            'message' => $message,
            'checkout_message' => $isOpen ? 'Pedidos até ' . $this->formatTime($closing) : $message,
            'schedule_label' => $this->scheduleLabel(),
            'schedule_hours' => $this->scheduleHours(),
        ];
    }

    private function nextOpeningAfter(DateTimeImmutable $current): ?DateTimeImmutable
    {
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $current->setTime(0, 0)->modify('+' . $offset . ' days');
            $weekday = (int) $date->format('N');
            foreach ($this->schedule[$weekday] ?? [] as $period) {
                $opening = $this->atTime($date, $period['open']);
                if ($opening > $current) {
                    return $opening;
                }
            }
        }

        return null;
    }

    private function nextOpeningMessage(DateTimeImmutable $current, ?DateTimeImmutable $nextOpening): string
    {
        if ($nextOpening === null) {
            return 'Sem próximo horário de abertura configurado';
        }

        $day = $current->format('Y-m-d') === $nextOpening->format('Y-m-d')
            ? 'hoje'
            : $this->weekdayName((int) $nextOpening->format('N'), true);

        return 'Abrimos ' . $day . ' às ' . $this->formatTime($nextOpening);
    }

    private function scheduleLabel(): string
    {
        $days = array_keys(array_filter($this->schedule));
        if ($days === []) {
            return 'Horário não configurado';
        }

        $consecutive = $days === range($days[0], $days[count($days) - 1]);
        if ($consecutive && count($days) > 1) {
            return ucfirst($this->weekdayName($days[0])) . ' a ' . $this->weekdayName($days[count($days) - 1]);
        }

        return ucfirst(implode(', ', array_map(fn (int $day): string => $this->weekdayName($day), $days)));
    }

    private function scheduleHours(): string
    {
        $periods = reset($this->schedule);
        if (!is_array($periods) || $periods === []) {
            return '';
        }

        return implode(' e ', array_map(fn (array $period): string => $this->formatTimeValue($period['open']) . ' às ' . $this->formatTimeValue($period['close']), $periods));
    }

    private function atTime(DateTimeImmutable $date, string $time): DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $date->setTime($hour, $minute);
    }

    private function validatedTime(string $time): string
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw new InvalidArgumentException('Business schedule times must use the HH:MM format.');
        }

        return $time;
    }

    private function formatTime(?DateTimeImmutable $date): string
    {
        return $date === null ? '' : $this->formatTimeValue($date->format('H:i'));
    }

    private function formatTimeValue(string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $minute === 0 ? $hour . 'h' : sprintf('%dh%02d', $hour, $minute);
    }

    private function weekdayName(int $weekday, bool $withFeira = false): string
    {
        $names = $withFeira
            ? [1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado', 7 => 'domingo']
            : [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado', 7 => 'domingo'];

        return $names[$weekday];
    }
}
