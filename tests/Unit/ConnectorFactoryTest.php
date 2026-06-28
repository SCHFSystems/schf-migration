<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\ConnectorFactory;

class ConnectorFactoryTest extends TestCase
{
    public function test_throws_exception_for_unsupported_source_type(): void
    {
        $factory = new ConnectorFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No connector available for source type: unknown');

        $factory->make('unknown', []);
    }

    public function test_constructor_does_not_throw(): void
    {
        $factory = new ConnectorFactory();
        $this->assertInstanceOf(ConnectorFactory::class, $factory);
    }
}
