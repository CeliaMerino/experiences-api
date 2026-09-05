<?php

declare(strict_types=1);

namespace App\Tests\Experience\Unit;

use App\Experience\Domain\Description;
use App\Experience\Domain\Experience;
use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ExperienceNotFound;
use App\Experience\Domain\ProviderId;
use App\Experience\Domain\Title;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\Exception\NotFound;
use PHPUnit\Framework\TestCase;

final class ExperienceTest extends TestCase
{
    public function testTituloVacioLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Title::of('');
    }

    public function testTituloSoloEspaciosLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Title::of('   ');
    }

    public function testTituloDeMasDe150CaracteresLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Title::of(str_repeat('a', 151));
    }

    public function testTituloDe150CaracteresSeAcepta(): void
    {
        $title = Title::of(str_repeat('a', 150));

        self::assertSame(150, strlen($title->value()));
    }

    public function testZonaHorariaInexistenteLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Experience::create(
            ExperienceId::generate(),
            ProviderId::generate(),
            Title::of('Kayak en el Sella'),
            Description::of('Descenso del río'),
            'Mars/Olympus',
        );
    }

    public function testExperienceNotFoundDevuelveExperienceNotFoundEnErrorType(): void
    {
        $error = new ExperienceNotFound(ExperienceId::generate());

        self::assertSame('experience-not-found', $error->errorType());
        self::assertInstanceOf(NotFound::class, $error);
    }

    public function testCreateConservaIdentidadYCampos(): void
    {
        $id = ExperienceId::generate();
        $providerId = ProviderId::generate();
        $title = Title::of('Kayak en el Sella');
        $description = Description::of('Descenso del río');

        $experience = Experience::create(
            $id,
            $providerId,
            $title,
            $description,
            'Europe/Madrid',
        );

        self::assertTrue($id->equals($experience->id()));
        self::assertTrue($providerId->equals($experience->providerId()));
        self::assertTrue($title->equals($experience->title()));
        self::assertTrue($description->equals($experience->description()));
        self::assertSame('Europe/Madrid', $experience->timezone()->getName());
    }
}
