<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Integration\Arrow;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Internal\KFiber;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;

/**
 * @internal
 *
 * @coversNothing
 */
class KleisliIOTest extends TestCase
{
    use MockClosureTrait;

    // public function testAsFiber()
    // {
    //     $arrow = KleisliIO::id();
    //     $fiber = $arrow->toFiber()->run(10)->unwrapSuccess($this->createClosureNotCalled());
    //     $this->assertInstanceOf(KFiber::class, $fiber);
    // }

    // public function testAsFiberRun()
    // {
    //     $arrow = KleisliIO::id();
    //     $fiber = $arrow->toFiber()->run();
    //     $result = $fiber->start(10)->run()->getResult();
    //     $this->assertEquals(10, $result->unwrapSuccess($this->createClosureNotCalled()));
    // }

    public function testOne()
    {
        $this->assertTrue(true);
    }
}
