<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Unit\Internal;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Internal\StartedFiber;
use Zodimo\Arrow\Internal\SteppableKleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;
use Zodimo\BaseReturn\IOMonad;

/**
 * @internal
 *
 * @coversNothing
 */
class StartedFiberTest extends TestCase
{
    use MockClosureTrait;

    public function testCanCreateFromResult()
    {
        $result = $this->createMock(IOMonad::class);
        $fiber = StartedFiber::createFromResult($result);
        $this->assertInstanceOf(StartedFiber::class, $fiber);
    }

    public function testCanCreateFromSteppeable()
    {
        $steppable = $this->createMock(SteppableKleisliIO::class);
        $fiber = StartedFiber::createFromSteppableArrow($steppable);
        $this->assertInstanceOf(StartedFiber::class, $fiber);
    }
}
