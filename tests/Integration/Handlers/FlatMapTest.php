<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Integration\Handlers;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Handlers\FlatMap;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;

/**
 * @internal
 *
 * @coversNothing
 */
class FlatMapTest extends TestCase
{
    use MockClosureTrait;

    public function testCanCreateWithId()
    {
        $composition = FlatMap::initializeWith(KleisliIO::id());
        $this->assertInstanceOf(FlatMap::class, $composition);
    }

    public function testCanRunFromId()
    {
        $composition = FlatMap::initializeWith(KleisliIO::id());
        $result = $composition->run(10);

        $expectedResult = 10;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanAddArrows()
    {
        $f1 = fn ($x) => KleisliIO::liftPure(fn (int $_) => $x + 10);
        $f2 = fn ($x) => KleisliIO::liftPure(fn (int $_) => $x * 2);

        $arrow = FlatMap::initializeWith(KleisliIO::id())->addF($f1)->addF($f2);
        $result = $arrow->run(10);

        $expectedResult = 40;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStackSafety()
    {
        $f1 = fn ($x) => KleisliIO::liftPure(fn (int $_) => $x + 1);
        $composition = FlatMap::initializeWith(KleisliIO::id());

        foreach (range(0, 1999) as $_) {
            $composition = $composition->addF($f1);
        }
        $result = $composition->run(0);
        $expectedResult = 2000;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }
}
