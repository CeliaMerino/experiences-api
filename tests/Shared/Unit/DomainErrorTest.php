<?php

declare(strict_types=1);

namespace App\Tests\Shared\Unit;

use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Domain\Exception\InvalidValue;
use PHPUnit\Framework\TestCase;

final class DomainErrorTest extends TestCase
{
    public function testHijaDeConflictDevuelveSuPropioErrorType(): void
    {
        $error = new ExampleConflict();

        self::assertSame('example-conflict', $error->errorType());
    }

    public function testHijaDeConflictEsCapturableComoDomainError(): void
    {
        $error = new ExampleConflict();

        self::assertInstanceOf(DomainError::class, $error);
        self::assertInstanceOf(Conflict::class, $error);
    }

    public function testInvalidValueDevuelveInvalidValueEnErrorType(): void
    {
        $error = new InvalidValue();

        self::assertSame('invalid-value', $error->errorType());
    }
}

final class ExampleConflict extends Conflict
{
    public function errorType(): string
    {
        return 'example-conflict';
    }
}
