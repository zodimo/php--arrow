<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Integration\Internal;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Internal\FinishedFiber;
use Zodimo\Arrow\Internal\StagedKleisliIO;
use Zodimo\Arrow\Internal\StartedFiber;
use Zodimo\Arrow\Internal\SteppableKleisliIO;
use Zodimo\Arrow\KleisliIO;
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

    public function testCanRunWithoutArrow()
    {
        $staged = StagedKleisliIO::stageWithoutArrow(IOMonad::pure(10));
        $steppable = SteppableKleisliIO::augment($staged);
        $fiber = StartedFiber::createFromSteppableArrow($steppable);
        $finished = $fiber->run();
        $this->assertInstanceOf(FinishedFiber::class, $finished);
        $this->assertEquals(10, $finished->getResult()->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanRunWithtArrow()
    {
        $staged = StagedKleisliIO::stageWithArrow(IOMonad::pure(10), KleisliIO::liftPure(fn ($x) => $x * 2));
        $steppable = SteppableKleisliIO::augment($staged);
        $fiber = StartedFiber::createFromSteppableArrow($steppable);
        $finished = $fiber->run();
        $this->assertInstanceOf(FinishedFiber::class, $finished);
        $this->assertEquals(20, $finished->getResult()->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStackSafetyAndThen()
    {
        $input = IOMonad::pure(0);
        $addOne = KleisliIO::liftPure(fn (int $x) => $x + 1);

        $composition = KleisliIO::id();

        foreach (range(0, 999) as $_) {
            $composition = $composition->andThen($addOne);
        }

        $staged = StagedKleisliIO::stageWithArrow($input, $composition);
        $steppable = SteppableKleisliIO::augment($staged);
        $fiber = StartedFiber::createFromSteppableArrow($steppable);
        $finished = $fiber->run();
        $this->assertInstanceOf(FinishedFiber::class, $finished);
        $this->assertEquals(1000, $finished->getResult()->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStackSafetyFlatMap()
    {
        $input = IOMonad::pure(0);
        $addOneK = fn (int $x) => KleisliIO::liftPure(fn (int $_) => $x + 1);

        $composition = KleisliIO::id();

        foreach (range(0, 999) as $_) {
            $composition = $composition->flatmap($addOneK);
        }

        $staged = StagedKleisliIO::stageWithArrow($input, $composition);
        $steppable = SteppableKleisliIO::augment($staged);
        $fiber = StartedFiber::createFromSteppableArrow($steppable);
        $finished = $fiber->run();
        $this->assertInstanceOf(FinishedFiber::class, $finished);
        $this->assertEquals(1000, $finished->getResult()->unwrapSuccess($this->createClosureNotCalled()));
    }
}
