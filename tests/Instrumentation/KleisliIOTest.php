<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Instrumentation;

use OpenTelemetry\SDK\Trace\Event;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;
use Zodimo\BaseReturn\IOMonad;

/**
 * @internal
 *
 * @coversNothing
 */
class KleisliIOTest extends AbstractInstrumentationTestCase
{
    use MockClosureTrait;

    public function testWithSpan(): void
    {
        $spanName = 'my-span-name';
        $arrow = KleisliIO::id()->withSpan($spanName);

        $arrow->run(10);

        $spanOption = $this->getSpanAtOffset(0);
        $this->assertTrue($spanOption->isSome());
        $span = $spanOption->unwrap($this->createClosureNotCalled());
        $this->assertEquals($spanName, $span->getName());
    }

    public function testWithSpanError1(): void
    {
        $spanName = 'my-span-name';
        $exception = new \RuntimeException('error');
        $arrow = KleisliIO::liftImpure(function ($_) use ($exception) {throw $exception; })->withSpan($spanName);

        $arrow->run(10);

        $spanOption = $this->getSpanAtOffset(0);
        $this->assertTrue($spanOption->isSome());
        $span = $spanOption->unwrap($this->createClosureNotCalled());
        $this->assertEquals($spanName, $span->getName());
        $events = $span->getEvents();
        $this->assertNotEmpty($events, 'has events');

        /**
         * @var Event $event
         */
        $event = $events[0];
        $this->assertEquals('exception', $event->getName(), 'event name');
    }

    public function testWithSpanError2(): void
    {
        $spanName = 'my-span-name';
        $error = 'error string';
        $arrow = KleisliIO::arr(fn ($_) => IOMonad::fail($error))->withSpan($spanName);

        $arrow->run(10);

        $spanOption = $this->getSpanAtOffset(0);
        $this->assertTrue($spanOption->isSome());
        $span = $spanOption->unwrap($this->createClosureNotCalled());
        $this->assertEquals($spanName, $span->getName());
        $events = $span->getEvents();
        $this->assertNotEmpty($events, 'has events');

        /**
         * @var Event $event
         */
        $event = $events[0];
        $this->assertEquals('exception', $event->getName(), 'event name');
    }
}
