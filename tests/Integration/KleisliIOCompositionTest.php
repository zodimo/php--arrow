<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Integration\Arrow;

use PHPUnit\Framework\TestCase;
use Zodimo\BaseReturn\IOMonad;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\KleisliIOComposition;
use Zodimo\Arrow\Tests\MockClosureTrait;

/**
 * @internal
 *
 * @coversNothing
 */
class KleisliIOCompositionTest extends TestCase
{
    use MockClosureTrait;

    public function testCanCreateWithId()
    {
        $composition = KleisliIOComposition::id();
        $this->assertInstanceOf(KleisliIOComposition::class, $composition);
    }

    public function testCanCreateWithArr()
    {
        $idFn = fn ($x) => IOMonad::pure($x);
        $composition = KleisliIOComposition::arr($idFn);
        $this->assertInstanceOf(KleisliIOComposition::class, $composition);
    }

    public function testCanRunFromId()
    {
        $arrow = KleisliIOComposition::id();
        $result = $arrow->run(10);

        $expectedResult = 10;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanRunFromArr()
    {
        /**
         * @var callable(int):IOMonad<int, never> $func
         */
        $func = fn (int $a) => IOMonad::pure($a);

        $arrow = KleisliIOComposition::arr($func);
        $result = $arrow->run(10);

        $expectedResult = 10;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanAddArrows()
    {
        $arrow1 = KleisliIO::liftPure(fn (int $x) => $x + 10);
        $arrow2 = KleisliIO::liftPure(fn (int $x) => $x * 2);

        $arrow = KleisliIOComposition::id()->addArrow($arrow1)->addArrow($arrow2);
        $result = $arrow->run(10);

        $expectedResult = 40;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testAndThen()
    {
        $arrow1 = KleisliIO::liftPure(fn (int $x) => $x + 10);
        $arrow2 = KleisliIO::liftPure(fn (int $x) => $x * 2);

        $composition1 = KleisliIOComposition::id()->addArrow($arrow1)->addArrow($arrow2);
        $composition2 = KleisliIOComposition::id()->addArrow($arrow2)->addArrow($arrow1);

        $arrow = $composition1->andThen($composition2);
        $result = $arrow->run(10); // comp1 (10 + 10) * 2 = 40 , comp2 = (40*2)+10 = 90

        $expectedResult = 90;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStackSafety()
    {
        $arrow1 = KleisliIO::liftPure(fn (int $x) => $x + 1);
        $composition = KleisliIOComposition::id();

        foreach (range(0, 1999) as $_) {
            $composition = $composition->addArrow($arrow1);
        }
        $result = $composition->run(0);
        $expectedResult = 2000;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }
}
