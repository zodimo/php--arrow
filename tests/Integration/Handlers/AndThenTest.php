<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Integration\Handlers;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Handlers\AndThen;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;

/**
 * @internal
 *
 * @coversNothing
 */
class AndThenTest extends TestCase
{
    use MockClosureTrait;

    public function testCanCreateWithId()
    {
        $composition = AndThen::id();
        $this->assertInstanceOf(AndThen::class, $composition);
    }

    public function testCanRunFromId()
    {
        $arrow = AndThen::id();
        $result = $arrow->run(10);

        $expectedResult = 10;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanAddArrows()
    {
        $arrow1 = KleisliIO::liftPure(fn (int $x) => $x + 10);
        $arrow2 = KleisliIO::liftPure(fn (int $x) => $x * 2);

        $arrow = AndThen::id()->addArrow($arrow1)->addArrow($arrow2);
        $result = $arrow->run(10);

        $expectedResult = 40;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStackSafety()
    {
        $arrow1 = KleisliIO::liftPure(fn (int $x) => $x + 1);
        $composition = AndThen::id();

        foreach (range(0, 1999) as $_) {
            $composition = $composition->addArrow($arrow1);
        }
        $result = $composition->run(0);
        $expectedResult = 2000;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }
}
