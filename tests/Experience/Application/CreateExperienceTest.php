<?php

declare(strict_types=1);

namespace App\Tests\Experience\Application;

use App\Experience\Application\Create\CreateExperienceCommand;
use App\Experience\Application\Create\CreateExperienceCommandHandler;
use App\Experience\Domain\ProviderId;
use App\Shared\Domain\Exception\InvalidValue;
use App\Tests\Experience\InMemoryExperienceRepository;
use PHPUnit\Framework\TestCase;

final class CreateExperienceTest extends TestCase
{
    public function testAltaConDatosValidosPersisteYDevuelveIdentificador(): void
    {
        $repository = new InMemoryExperienceRepository();
        $handler = new CreateExperienceCommandHandler($repository);
        $providerId = ProviderId::generate();

        $id = $handler(new CreateExperienceCommand(
            $providerId->value(),
            'Kayak en el Sella',
            'Descenso del río',
            'Europe/Madrid',
        ));

        $experience = $repository->get($id);

        self::assertTrue($id->equals($experience->id()));
        self::assertTrue($providerId->equals($experience->providerId()));
        self::assertSame('Kayak en el Sella', $experience->title()->value());
        self::assertSame('Descenso del río', $experience->description()->value());
        self::assertSame('Europe/Madrid', $experience->timezone()->getName());
    }

    public function testTituloVacioLanzaInvalidValue(): void
    {
        $handler = new CreateExperienceCommandHandler(new InMemoryExperienceRepository());

        $this->expectException(InvalidValue::class);

        $handler(new CreateExperienceCommand(
            ProviderId::generate()->value(),
            '',
            'Descenso del río',
            'Europe/Madrid',
        ));
    }

    public function testTituloDeMasDe150CaracteresLanzaInvalidValue(): void
    {
        $handler = new CreateExperienceCommandHandler(new InMemoryExperienceRepository());

        $this->expectException(InvalidValue::class);

        $handler(new CreateExperienceCommand(
            ProviderId::generate()->value(),
            str_repeat('a', 151),
            'Descenso del río',
            'Europe/Madrid',
        ));
    }

    public function testZonaHorariaInexistenteLanzaInvalidValue(): void
    {
        $handler = new CreateExperienceCommandHandler(new InMemoryExperienceRepository());

        $this->expectException(InvalidValue::class);

        $handler(new CreateExperienceCommand(
            ProviderId::generate()->value(),
            'Kayak en el Sella',
            'Descenso del río',
            'Mars/Olympus',
        ));
    }
}
