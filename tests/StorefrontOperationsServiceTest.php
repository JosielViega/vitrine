<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontOperationsRepositoryInterface;
use App\Services\StorefrontOperationsService;
use App\Services\StorefrontOperationsValidationException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontOperationsServiceTest extends TestCase
{
    public function testReturnsNoticeSevenDaysAndPersistedBusinessStatus(): void
    {
        $service = $this->service(clock: '2026-09-10 21:45:00');
        $dashboard = $service->dashboard();

        self::assertFalse($dashboard['notice']['enabled']);
        self::assertSame('available', $dashboard['storefront_status']);
        self::assertCount(7, $dashboard['schedule']);
        self::assertSame('17:00', $dashboard['schedule'][3]['open_time']);
        self::assertSame('America/Sao_Paulo', $dashboard['business_status']['timezone']);
        self::assertSame('2026-09-11 17:00:00', $dashboard['business_status']['next_open_at']->format('Y-m-d H:i:s'));
    }

    public function testNoticeCanBeActivatedAndDisabledWithoutLosingDraft(): void
    {
        $repository = new StorefrontOperationsFakeRepository();
        $service = $this->service($repository);
        $service->updateNotice('1', '  Atenção  ', "  Linha 1\nLinha 2  ");

        self::assertTrue($service->isBlocked());
        self::assertSame('Atenção', $service->notice()['title']);
        self::assertSame("Linha 1\nLinha 2", $service->notice()['message']);

        $service->updateNotice(null, 'Atenção', "Linha 1\nLinha 2");
        self::assertFalse($service->isBlocked());
        self::assertSame('Atenção', $service->notice()['title']);
    }

    #[DataProvider('invalidNoticeProvider')]
    public function testActiveNoticeValidatesRequiredTextAndLimits(string $title, string $message): void
    {
        $this->expectException(StorefrontOperationsValidationException::class);
        $this->service()->updateNotice('1', $title, $message);
    }

    public static function invalidNoticeProvider(): array
    {
        return [
            'title required' => ['', 'Mensagem'],
            'message required' => ['Título', ''],
            'title limit' => [str_repeat('a', 121), 'Mensagem'],
            'message limit' => ['Título', str_repeat('a', 1501)],
            'invalid UTF-8' => ["\xC3\x28", 'Mensagem'],
        ];
    }

    #[DataProvider('invalidHoursProvider')]
    public function testInvalidDayRejectsWholeScheduleWithoutRepositoryWrite(string $open, string $close): void
    {
        $repository = new StorefrontOperationsFakeRepository();
        $input = $this->validHoursInput();
        $input['day_5_open'] = $open;
        $input['day_5_close'] = $close;

        try {
            $this->service($repository)->updateBusinessHours($input);
            self::fail('Expected invalid schedule.');
        } catch (StorefrontOperationsValidationException) {
            self::assertSame(0, $repository->scheduleWrites);
            self::assertSame('17:00:00', $repository->hours[3]['open_time']);
        }
    }

    public static function invalidHoursProvider(): array
    {
        return [
            'open empty' => ['', '21:30'],
            'close empty' => ['17:00', ''],
            'bad format' => ['7:00', '21:30'],
            'equal' => ['17:00', '17:00'],
            'after close' => ['22:00', '02:00'],
        ];
    }

    public function testUpdatedScheduleFeedsBusinessHoursAndClosedDaysPersistNullTimes(): void
    {
        $repository = new StorefrontOperationsFakeRepository();
        $input = $this->validHoursInput();
        $input['day_4_close'] = '22:00';
        $input['day_6_enabled'] = null;
        $input['day_7_enabled'] = '1';
        $input['day_7_open'] = '17:00';
        $input['day_7_close'] = '20:00';
        $service = $this->service($repository, '2026-09-13 18:00:00');
        $service->updateBusinessHours($input);

        self::assertSame(1, $repository->scheduleWrites);
        self::assertNull($repository->hours[5]['open_time']);
        self::assertTrue($service->businessHoursService()->currentStatus()['is_open']);
        self::assertSame('20:00:00', $service->businessHoursService()->currentStatus()['current_close_at']->format('H:i:s'));
    }

    private function service(?StorefrontOperationsFakeRepository $repository = null, string $clock = '2026-09-10 18:00:00'): StorefrontOperationsService
    {
        $now = new DateTimeImmutable($clock, new DateTimeZone('America/Sao_Paulo'));

        return new StorefrontOperationsService($repository ?? new StorefrontOperationsFakeRepository(), 'America/Sao_Paulo', static fn (): DateTimeImmutable => $now);
    }

    private function validHoursInput(): array
    {
        $input = [];
        for ($day = 1; $day <= 7; $day++) {
            $input['day_' . $day . '_enabled'] = in_array($day, [4, 5, 6], true) ? '1' : null;
            $input['day_' . $day . '_open'] = '17:00';
            $input['day_' . $day . '_close'] = '21:30';
        }

        return $input;
    }
}

final class StorefrontOperationsFakeRepository implements StorefrontOperationsRepositoryInterface
{
    public array $settingsRow = ['notice_enabled' => 0, 'notice_title' => '', 'notice_message' => ''];
    public array $hours = [];
    public int $scheduleWrites = 0;

    public function __construct()
    {
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $enabled = in_array($weekday, [4, 5, 6], true);
            $this->hours[$weekday - 1] = [
                'weekday' => $weekday,
                'enabled' => $enabled ? 1 : 0,
                'open_time' => $enabled ? '17:00:00' : null,
                'close_time' => $enabled ? '21:30:00' : null,
            ];
        }
    }

    public function settings(): array { return $this->settingsRow; }
    public function businessHours(): array { return array_values($this->hours); }
    public function updateNotice(bool $enabled, string $title, string $message): void
    {
        $this->settingsRow = ['notice_enabled' => $enabled ? 1 : 0, 'notice_title' => $title, 'notice_message' => $message];
    }
    public function updateBusinessHours(array $schedule): void
    {
        $this->scheduleWrites++;
        foreach ($schedule as $weekday => $day) {
            $this->hours[$weekday - 1] = ['weekday' => $weekday, ...$day];
        }
    }
}
