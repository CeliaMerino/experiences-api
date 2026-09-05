<?php

declare(strict_types=1);

namespace App\Tests\Session\Unit;

use App\Experience\Domain\Description;
use App\Experience\Domain\Experience;
use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ProviderId;
use App\Experience\Domain\Title;
use App\Session\Domain\Capacity;
use App\Session\Domain\Service\SessionScheduler;
use App\Session\Domain\SessionDayTaken;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Session\InMemorySessionRepository;
use App\Tests\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class SessionSchedulerTest extends TestCase
{
    public function testDosSesionesElMismoDiaCivilLanzanSessionDayTaken(): void
    {
        $sessions = new InMemorySessionRepository();
        $scheduler = new SessionScheduler($sessions, $this->clock());
        $experience = $this->experience('Europe/Madrid');

        $first = $scheduler->schedule(
            $experience,
            new \DateTimeImmutable('2026-07-01T10:00:00+00:00'),
            Capacity::of(10),
            Money::of(2500, 'EUR'),
        );
        $sessions->save($first);

        $this->expectException(SessionDayTaken::class);

        $scheduler->schedule(
            $experience,
            new \DateTimeImmutable('2026-07-01T15:00:00+00:00'),
            Capacity::of(8),
            Money::of(3000, 'EUR'),
        );
    }

    public function testSesionesSeparadasPorTresHorasEnDiasCivilesDistintosSeCreanAmbas(): void
    {
        $sessions = new InMemorySessionRepository();
        $scheduler = new SessionScheduler($sessions, $this->clock());
        $experience = $this->experience('Europe/Madrid');

        $first = $scheduler->schedule(
            $experience,
            new \DateTimeImmutable('2026-07-01T21:00:00+00:00'),
            Capacity::of(10),
            Money::of(2500, 'EUR'),
        );
        $sessions->save($first);

        $second = $scheduler->schedule(
            $experience,
            new \DateTimeImmutable('2026-07-02T00:00:00+00:00'),
            Capacity::of(10),
            Money::of(2500, 'EUR'),
        );
        $sessions->save($second);

        self::assertSame('2026-07-01', $first->startsAt()->format('Y-m-d'));
        self::assertSame('2026-07-02', $second->startsAt()->format('Y-m-d'));
        self::assertSame('Europe/Madrid', $first->startsAt()->getTimezone()->getName());
        self::assertSame('Europe/Madrid', $second->startsAt()->getTimezone()->getName());
        self::assertNotNull($sessions->find($first->id()));
        self::assertNotNull($sessions->find($second->id()));
    }

    private function clock(): FrozenClock
    {
        return FrozenClock::at('2026-06-15T12:00:00+00:00');
    }

    private function experience(string $timezone): Experience
    {
        return Experience::create(
            ExperienceId::generate(),
            ProviderId::generate(),
            Title::of('Kayak en el Sella'),
            Description::of('Descenso del río'),
            $timezone,
        );
    }
}
