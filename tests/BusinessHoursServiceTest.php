<?php

declare(strict_types=1);

namespace Tests;

use App\Services\BusinessHoursService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BusinessHoursServiceTest extends TestCase
{
    #[DataProvider('openingStatusProvider')]
    public function testOpeningBoundariesAndWeekdays(string $dateTime, bool $expectedOpen): void
    {
        $status = $this->service()->statusAt($this->date($dateTime));

        self::assertSame($expectedOpen, $status['is_open']);
        self::assertSame($expectedOpen ? 'Aberto agora' : 'Fechado agora', $status['status_label']);
    }

    public static function openingStatusProvider(): array
    {
        return [
            'quinta um segundo antes' => ['2026-09-10 16:59:59', false],
            'quinta na abertura' => ['2026-09-10 17:00:00', true],
            'quinta durante atendimento' => ['2026-09-10 18:00:00', true],
            'quinta um segundo antes do fechamento' => ['2026-09-10 21:29:59', true],
            'quinta no fechamento exclusivo' => ['2026-09-10 21:30:00', false],
            'sexta durante atendimento' => ['2026-09-11 18:00:00', true],
            'sábado durante atendimento' => ['2026-09-12 18:00:00', true],
            'domingo fechado' => ['2026-09-13 18:00:00', false],
            'segunda fechado' => ['2026-09-14 18:00:00', false],
            'terça fechado' => ['2026-09-15 18:00:00', false],
            'quarta fechado' => ['2026-09-16 18:00:00', false],
        ];
    }

    public function testOpenStatusContainsCurrentPeriodAndFriendlyMessages(): void
    {
        $status = $this->service()->statusAt($this->date('2026-09-10 18:00:00'));

        self::assertSame('2026-09-10 17:00:00', $status['current_open_at']->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-10 21:30:00', $status['current_close_at']->format('Y-m-d H:i:s'));
        self::assertNull($status['next_open_at']);
        self::assertSame('Hoje até 21h30', $status['message']);
        self::assertSame('Pedidos até 21h30', $status['checkout_message']);
    }

    #[DataProvider('nextOpeningProvider')]
    public function testCalculatesNextOpening(string $dateTime, string $expected, string $message): void
    {
        $status = $this->service()->statusAt($this->date($dateTime));

        self::assertFalse($status['is_open']);
        self::assertSame($expected, $status['next_open_at']->format('Y-m-d H:i:s'));
        self::assertSame($message, $status['message']);
    }

    public static function nextOpeningProvider(): array
    {
        return [
            'antes da abertura na quinta' => ['2026-09-10 10:00:00', '2026-09-10 17:00:00', 'Abrimos hoje às 17h'],
            'após fechar na quinta' => ['2026-09-10 21:30:00', '2026-09-11 17:00:00', 'Abrimos sexta-feira às 17h'],
            'após fechar no sábado' => ['2026-09-12 22:00:00', '2026-09-17 17:00:00', 'Abrimos quinta-feira às 17h'],
            'domingo' => ['2026-09-13 12:00:00', '2026-09-17 17:00:00', 'Abrimos quinta-feira às 17h'],
        ];
    }

    public function testConvertsInputAndReportsOfficialTimezone(): void
    {
        $utc = new DateTimeImmutable('2026-09-10 20:00:00', new DateTimeZone('UTC'));
        $status = $this->service()->statusAt($utc);

        self::assertTrue($status['is_open']);
        self::assertSame('America/Sao_Paulo', $status['timezone']);
        self::assertSame('2026-09-10 17:00:00', $status['current_at']->format('Y-m-d H:i:s'));
        self::assertSame('Quinta a sábado', $status['schedule_label']);
        self::assertSame('17h às 21h30', $status['schedule_hours']);
    }

    public function testCurrentStatusCanUseAnInjectedDeterministicClock(): void
    {
        $service = new BusinessHoursService($this->config(), fn (): DateTimeImmutable => $this->date('2026-09-10 18:00:00'));

        self::assertTrue($service->currentStatus()['is_open']);
    }

    private function service(): BusinessHoursService
    {
        return new BusinessHoursService($this->config());
    }

    private function config(): array
    {
        return require dirname(__DIR__) . '/config/business.php';
    }

    private function date(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('America/Sao_Paulo'));
    }
}
