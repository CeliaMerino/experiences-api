<?php

declare(strict_types=1);

namespace App\Tests\Session\Application;

use App\Experience\Domain\Description;
use App\Experience\Domain\Experience;
use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ExperienceNotFound;
use App\Experience\Domain\ProviderId;
use App\Experience\Domain\Title;
use App\Session\Application\Create\CreateSessionCommand;
use App\Session\Application\Create\CreateSessionCommandHandler;
use App\Session\Domain\Service\SessionScheduler;
use App\Session\Domain\SessionDayTaken;
use App\Session\Domain\SessionInThePast;
use App\Shared\Domain\Exception\InvalidValue;
use App\Tests\Experience\InMemoryExperienceRepository;
use App\Tests\Session\InMemorySessionRepository;
use App\Tests\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class CreateSessionTest extends TestCase
{
    public function testAltaConDatosValidosPersisteYDevuelveIdentificador(): void
    {
        $experiences = new InMemoryExperienceRepository();
        $sessions = new InMemorySessionRepository();
        $experience = $this->persistExperience($experiences);
        $handler = $this->handler($experiences, $sessions);

        $id = $handler(new CreateSessionCommand(
            $experience->id()->value(),
            '2026-07-01T10:00:00+00:00',
            12,
            3500,
            'EUR',
        ));

        $session = $sessions->find($id);

        self::assertNotNull($session);
        self::assertTrue($id->equals($session->id()));
        self::assertTrue($experience->id()->equals($session->experienceId()));
        self::assertSame(12, $session->capacity()->value());
        self::assertSame(0, $session->seatsTaken()->value());
        self::assertSame(3500, $session->price()->amount());
        self::assertSame('EUR', $session->price()->currency());
        self::assertSame('Europe/Madrid', $session->startsAt()->getTimezone()->getName());
    }

    public function testExperienciaInexistenteLanzaExperienceNotFound(): void
    {
        $handler = $this->handler(new InMemoryExperienceRepository(), new InMemorySessionRepository());

        $this->expectException(ExperienceNotFound::class);

        $handler(new CreateSessionCommand(
            ExperienceId::generate()->value(),
            '2026-07-01T10:00:00+00:00',
            10,
            2500,
            'EUR',
        ));
    }

    public function testFechaEnElPasadoLanzaSessionInThePast(): void
    {
        $experiences = new InMemoryExperienceRepository();
        $experience = $this->persistExperience($experiences);
        $handler = $this->handler($experiences, new InMemorySessionRepository());

        $this->expectException(SessionInThePast::class);

        $handler(new CreateSessionCommand(
            $experience->id()->value(),
            '2026-06-15T11:00:00+00:00',
            10,
            2500,
            'EUR',
        ));
    }

    public function testSegundoAltaElMismoDiaCivilLanzaSessionDayTaken(): void
    {
        $experiences = new InMemoryExperienceRepository();
        $sessions = new InMemorySessionRepository();
        $experience = $this->persistExperience($experiences);
        $handler = $this->handler($experiences, $sessions);

        $handler(new CreateSessionCommand(
            $experience->id()->value(),
            '2026-07-01T10:00:00+00:00',
            10,
            2500,
            'EUR',
        ));

        $this->expectException(SessionDayTaken::class);

        $handler(new CreateSessionCommand(
            $experience->id()->value(),
            '2026-07-01T18:00:00+00:00',
            8,
            3000,
            'EUR',
        ));
    }

    public function testAforoCeroLanzaInvalidValue(): void
    {
        $experiences = new InMemoryExperienceRepository();
        $experience = $this->persistExperience($experiences);
        $handler = $this->handler($experiences, new InMemorySessionRepository());

        $this->expectException(InvalidValue::class);

        $handler(new CreateSessionCommand(
            $experience->id()->value(),
            '2026-07-01T10:00:00+00:00',
            0,
            2500,
            'EUR',
        ));
    }

    public function testImporteNegativoLanzaInvalidValue(): void
    {
        $experiences = new InMemoryExperienceRepository();
        $experience = $this->persistExperience($experiences);
        $handler = $this->handler($experiences, new InMemorySessionRepository());

        $this->expectException(InvalidValue::class);

        $handler(new CreateSessionCommand(
            $experience->id()->value(),
            '2026-07-01T10:00:00+00:00',
            10,
            -1,
            'EUR',
        ));
    }

    public function testDivisaInvalidaLanzaInvalidValue(): void
    {
        $experiences = new InMemoryExperienceRepository();
        $experience = $this->persistExperience($experiences);
        $handler = $this->handler($experiences, new InMemorySessionRepository());

        $this->expectException(InvalidValue::class);

        $handler(new CreateSessionCommand(
            $experience->id()->value(),
            '2026-07-01T10:00:00+00:00',
            10,
            2500,
            'euro',
        ));
    }

    public function testElManejadorNoContieneCondicionalesNiAritmetica(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3).'/src/Session/Application/Create/CreateSessionCommandHandler.php',
        );
        self::assertNotFalse($source);

        foreach (token_get_all($source) as $token) {
            if (!\is_array($token)) {
                self::assertNotContains(
                    $token,
                    ['>', '<', '+', '-', '*', '/'],
                    sprintf('Operador prohibido "%s" en el manejador.', $token),
                );
                continue;
            }

            self::assertNotSame(
                \T_IF,
                $token[0],
                'El manejador no puede contener if.',
            );
            self::assertNotContains(
                $token[0],
                [\T_IS_GREATER_OR_EQUAL, \T_IS_SMALLER_OR_EQUAL, \T_SPACESHIP],
                sprintf('Operador de comparación prohibido en el manejador: %s', $token[1]),
            );
        }
    }

    private function handler(
        InMemoryExperienceRepository $experiences,
        InMemorySessionRepository $sessions,
    ): CreateSessionCommandHandler {
        return new CreateSessionCommandHandler(
            $experiences,
            $sessions,
            new SessionScheduler($sessions, FrozenClock::at('2026-06-15T12:00:00+00:00')),
        );
    }

    private function persistExperience(InMemoryExperienceRepository $experiences): Experience
    {
        $experience = Experience::create(
            ExperienceId::generate(),
            ProviderId::generate(),
            Title::of('Kayak en el Sella'),
            Description::of('Descenso del río'),
            'Europe/Madrid',
        );
        $experiences->save($experience);

        return $experience;
    }
}
