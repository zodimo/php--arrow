<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Unit\Internal;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Internal\KFiber;
use Zodimo\Arrow\Internal\StartedFiber;
use Zodimo\Arrow\KleisliIO;

/**
 * @internal
 *
 * @coversNothing
 */
class KFiberTest extends TestCase
{
    public function testCanCreate()
    {
        $fiber = KFiber::create(KleisliIO::id());
        $this->assertInstanceOf(KFiber::class, $fiber);
    }

    public function testCanStart()
    {
        $fiber = KFiber::create(KleisliIO::id());
        $startedFiber = $fiber->start(10);
        $this->assertInstanceOf(StartedFiber::class, $startedFiber);
    }
}
