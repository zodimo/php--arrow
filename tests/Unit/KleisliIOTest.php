<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Unit\Arrow;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;
use Zodimo\BaseReturn\IOMonad;

/**
 * @internal
 *
 * @coversNothing
 */
class KleisliIOTest extends TestCase
{
    use MockClosureTrait;

    public function testCanCreateWithArr()
    {
        $func = fn ($a) => IOMonad::pure($a);

        $arrow = KleisliIO::arr($func);
        $this->assertInstanceOf(KleisliIO::class, $arrow);
    }

    public function testCanCreateWithId()
    {
        $arrow = KleisliIO::id();
        $this->assertInstanceOf(KleisliIO::class, $arrow);
    }

    public function testCanRunFromArr()
    {
        /**
         * @var callable(int):IOMonad<int, never> $func
         */
        $func = fn (int $a) => IOMonad::pure($a);

        $arrow = KleisliIO::arr($func);
        $result = $arrow->run(10);

        $expectedResult = 10;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanRunFromId()
    {
        $arrow = KleisliIO::id();
        $result = $arrow->run(10);

        $expectedResult = 10;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testLiftPure()
    {
        $arrow = KleisliIO::liftPure(fn ($x) => $x + 1);
        $result = $arrow->run(10);

        $expectedResult = 11;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testLiftImpureWithSuccess()
    {
        $arrow = KleisliIO::liftImpure(fn ($x) => $x + 1);
        $result = $arrow->run(10);

        $expectedResult = 11;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testLiftImpureWithFailure()
    {
        $exception = new \RuntimeException('oops');
        $arrow = KleisliIO::liftImpure(function ($_) use ($exception) { throw $exception; });
        $result = $arrow->run(10);

        $expectedResult = IOMonad::fail($exception)->unwrapFailure($this->createClosureNotCalled());

        $this->assertEquals($expectedResult, $result->unwrapFailure($this->createClosureNotCalled()));
    }

    public function testFlatMap()
    {
        $arrow = KleisliIO::liftPure(fn ($x) => $x + 5);

        $choice = function (int $x) {
            /**
             * You have the option to ignore the x in the return computation.
             */
            if ($x < 10) {
                return KleisliIO::liftPure(fn ($y) => $y + 10);
            }

            return KleisliIO::liftPure(fn ($_) => $x + 20);
        };

        $flatMapArrow = $arrow->flatMap($choice);

        $this->assertEquals(12, $flatMapArrow->run(2)->unwrapSuccess($this->createClosureNotCalled()), '([2] + 5 )< 10  = [2] + 10');
        $this->assertEquals(32, $flatMapArrow->run(7)->unwrapSuccess($this->createClosureNotCalled()), '([7] + 5) > 10 = [7 + 5]  + 20');
    }

    public function testFlatMap2()
    {
        $arrow = KleisliIO::liftPure(fn ($x) => $x + 5);

        // not so intuitive...
        $func = fn (int $y) => KleisliIO::liftPure(fn ($_) => $y + 10);

        $flatMapArrow = $arrow->flatMap($func)->flatMap($func);

        $this->assertEquals(27, $flatMapArrow->run(2)->unwrapSuccess($this->createClosureNotCalled()), '[2] + 5 + 10 + 10');
    }

    public function testFlatMap3()
    {
        /**
         * instance Monad m => Monad (Kleisli m a) where
         *   Kleisli f >>= k = Kleisli $ \x -> f x >>= \a -> runKleisli (k a) x.
         */
        $input = 10;
        $that = $this;

        $arrow1 = KleisliIO::liftPure(function (int $x) use ($that, $input) {
            $that->assertEquals($input, $x, '$arrow1: $input=$x');

            return $x + 5;
        });
        $arrow2 = KleisliIO::liftPure(function (int $x) use ($that, $input) {
            $that->assertEquals($input, $x, '$arrow2: $input=$x');

            return $x + 10;
        });
        $arrow3 = KleisliIO::liftPure(function (int $x) use ($that, $input) {
            $that->assertEquals($input, $x, '$arrow3: $input=$x');

            return $x + 15;
        });

        $arrow = $arrow1
        // flatMap 1
            ->flatMap(
                function ($x1) use ($arrow2, $that) {
                    $that->assertEquals(15, $x1, 'flatMap[1]: $x1 = $arrow1->run($input) 10 + 5');

                    return $arrow2;
                }
            )
            // flatMap 2
            ->flatMap(
                function ($x2) use ($arrow3, $that) {
                    $that->assertEquals(20, $x2, 'flatMap[2]: $x2  $arrow2->run($input) 10 + 10');

                    return $arrow3;
                }
            )
        ;

        $result = $arrow->run($input);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(25, $result->unwrapSuccess($this->createClosureNotCalled()), 'result of $arrow3: input[10] + 15');
    }

    public function testAndThenK()
    {
        $arrow = KleisliIO::liftPure(fn (int $x) => $x + 5);

        $func = fn (int $x) => IOMonad::pure($x + 10);

        $flatMapArrow = $arrow->andThenK($func);

        $this->assertEquals(17, $flatMapArrow->run(2)->unwrapSuccess($this->createClosureNotCalled()), '[2] +7 + 10 ');
    }

    public function testAndThen()
    {
        $arrow = KleisliIO::liftPure(fn (int $x) => $x + 5);

        $func = fn (int $x) => IOMonad::pure($x + 10);

        $flatMapArrow = $arrow->andThen(KleisliIO::arr($func));

        $this->assertEquals(17, $flatMapArrow->run(2)->unwrapSuccess($this->createClosureNotCalled()), '[2] +7 + 10 ');
    }

    public function testStackSafetyAndThen()
    {
        $addOne = KleisliIO::liftPure(fn (int $x) => $x + 1);

        $composition = KleisliIO::id();

        foreach (range(0, 999) as $_) {
            $composition = $composition->andThen($addOne);
        }

        $this->assertEquals(1000, $composition->run(0)->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStackSafetyAndThenK()
    {
        $addOneK = fn (int $x) => IOMonad::pure($x + 1);

        $composition = KleisliIO::id();

        foreach (range(0, 999) as $_) {
            $composition = $composition->andThenK($addOneK);
        }

        $this->assertEquals(1000, $composition->run(0)->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStackSafetyFlatMap()
    {
        $addOneK = fn (int $x) => KleisliIO::liftPure(fn (int $_) => $x + 1);

        $composition = KleisliIO::id();

        foreach (range(0, 999) as $_) {
            $composition = $composition->flatMap($addOneK);
        }

        $this->assertEquals(1000, $composition->run(0)->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testIdentityLaw(): void
    {
        $arrowId = KleisliIO::id();
        $arrowA = KleisliIO::liftPure(fn (int $x) => $x + 5);

        $leftArrow = $arrowId->andThen($arrowA);
        $rightArrow = $arrowA->andThen($arrowId);

        $leftResult = $leftArrow->run(10)->unwrapSuccess($this->createClosureNotCalled());
        $rightResult = $rightArrow->run(10)->unwrapSuccess($this->createClosureNotCalled());

        $this->assertEquals($leftResult, $rightResult);
    }

    public function testAssociativityLaw()
    {
        $arrowA = KleisliIO::liftPure(fn (int $x) => $x + 5);
        $arrowB = KleisliIO::liftPure(fn (int $x) => $x * 5);
        $arrowC = KleisliIO::liftPure(fn (int $x) => $x - 5);
        // i.e arrowA->andThen(arrowB->andThen(arrowC)) == arrowA->andThen(arrowB)->andThen(arrowC)

        $leftArrow = $arrowA->andThen($arrowB->andThen($arrowC));
        $rightArrow = $arrowA->andThen($arrowB)->andThen($arrowC);

        $leftResult = $leftArrow->run(10)->unwrapSuccess($this->createClosureNotCalled());
        $rightResult = $rightArrow->run(10)->unwrapSuccess($this->createClosureNotCalled());

        $this->assertEquals($leftResult, $rightResult);
    }

    public function testPrompt()
    {
        $prompt = KleisliIO::prompt(KleisliIO::id());
        $result = $prompt->run(10);
        $this->assertEquals(10, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStubInput()
    {
        $arrow = KleisliIO::id()->stubInput(10);
        $result = $arrow->run(null);

        $expectedResult = 10;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testToFiber()
    {
        $fiber = KleisliIO::id()->andThen(KleisliIO::liftPure(fn (int $x) => $x + 10))->toFiber();
        $startedFiber = $fiber->start(10);
        $result = $startedFiber->run()->getResult();
        $this->assertEquals(20, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testStackSafetyAndThenToFiber()
    {
        $addOne = KleisliIO::liftPure(fn (int $x) => $x + 1);

        $composition = KleisliIO::id();

        foreach (range(0, 999) as $_) {
            $composition = $composition->andThen($addOne);
        }

        $fiber = $composition->toFiber();

        $startedFiber = $fiber->start(0);
        $result = $startedFiber->run()->getResult();
        $this->assertEquals(1000, $result->unwrapSuccess($this->createClosureNotCalled()));
    }
}
