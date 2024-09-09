<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Integration\Arrow;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zodimo\BaseReturn\IOMonad;
use Zodimo\BaseReturn\Tuple;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\KleisliIOOps;
use Zodimo\Arrow\Tests\MockClosureTrait;

/**
 * @internal
 *
 * @coversNothing
 */
class KleisliIOOpsTest extends TestCase
{
    use MockClosureTrait;

    public function testFirst()
    {
        $func = fn (int $x) => $x + 10;

        $kleisliArrow = KleisliIO::liftPure($func);
        $arrowFirst = KleisliIOOps::first($kleisliArrow);
        $result = $arrowFirst->run(Tuple::create(15, 'Joe'));
        $expectedResult = Tuple::create(25, 'Joe');
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testSecond()
    {
        $func = fn (int $x) => $x + 10;
        $kleisliArrow = KleisliIO::liftPure($func);
        $arrowSecond = KleisliIOOps::second($kleisliArrow);
        $result = $arrowSecond->run(Tuple::create('Joe', 15));
        $expectedResult = Tuple::create('Joe', 25);
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCompose()
    {
        // f >>> g = g . f
        // g after f
        $funcF = fn (int $x) => $x + 10;
        $funcG = fn (int $x) => $x * 10;

        $kleisliArrowF = KleisliIO::liftPure($funcF);
        $kleisliArrowG = KleisliIO::liftPure($funcG);

        $arrowComposed = KleisliIOOps::compose($kleisliArrowF, $kleisliArrowG);
        $result = $arrowComposed->run(10);
        $expectedResult = 200;
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testMerge()
    {
        $funcF = fn (int $x) => $x + 10;
        $funcG = fn (int $x) => $x * 10;

        $kleisliArrowF = KleisliIO::liftPure($funcF);
        $kleisliArrowG = KleisliIO::liftPure($funcG);
        $arrowMerged = KleisliIOOps::merge($kleisliArrowF, $kleisliArrowG);
        $result = $arrowMerged->run(Tuple::create(20, 30));
        $expectedResult = Tuple::create(30, 300);
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testSplit()
    {
        $funcF = fn (int $x) => $x + 10;
        $funcG = fn (int $x) => $x * 10;

        $kleisliArrowF = KleisliIO::liftPure($funcF);
        $kleisliArrowG = KleisliIO::liftPure($funcG);
        $arrowSplit = KleisliIOOps::split($kleisliArrowF, $kleisliArrowG);
        $result = $arrowSplit->run(50);
        $expectedResult = Tuple::create(60, 500);
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testIfThenElseTrue()
    {
        $funcCond = fn (int $x) => 10 == $x;
        $funcThen = fn (int $x) => $x + 2;
        $funcElse = fn (int $x) => $x - 2;

        $cond = KleisliIO::liftPure($funcCond);
        $then = KleisliIO::liftPure($funcThen);
        $else = KleisliIO::liftPure($funcElse);

        $arrow = KleisliIOOps::ifThenElse($cond, $then, $else);
        $result = $arrow->run(10);
        $expectedResult = 12;
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testIfThenElseFalse()
    {
        $funcCond = fn (int $x) => 10 == $x;
        $funcThen = fn (int $x) => $x + 2;
        $funcElse = fn (int $x) => $x - 2;

        $cond = KleisliIO::liftPure($funcCond);
        $then = KleisliIO::liftPure($funcThen);
        $else = KleisliIO::liftPure($funcElse);

        $arrow = KleisliIOOps::ifThenElse($cond, $then, $else);
        $result = $arrow->run(100);
        $expectedResult = 98;

        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testWhileDo()
    {
        $funcCheck = fn (int $x) => $x < 10;
        $funcBody = fn (int $x) => $x + 2;

        $check = KleisliIO::liftPure($funcCheck);
        $body = KleisliIO::liftPure($funcBody);

        $arrow = KleisliIOOps::whileDo($check, $body);
        $result = $arrow->run(0);
        $expectedResult = 10;
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testArrayFill1000()
    {
        $funcCheck = fn (array $x) => count($x) < 1000;
        $funcBody = function (array $x) {
            $size = count($x);
            $x[] = $size;

            return $x;
        };

        $check = KleisliIO::liftPure($funcCheck);
        $body = KleisliIO::liftPure($funcBody);

        $arrow = KleisliIOOps::whileDo($check, $body);

        $result = $arrow->run([])->unwrapSuccess($this->createClosureNotCalled());
        $this->assertEquals(1000, count($result));
    }

    public function testBracketV1()
    {
        /**
         * each level van fail..
         *  0 = fail
         *  1 = succeed
         * acquire during release.
         *
         * 1: 0 0 0 < 0 x x
         * 2: 0 0 1 < 0 x x
         * 3: 0 1 0 < 0 x x
         * 4: 0 1 1 < 0 x x
         * 5: 1 0 0
         * 6: 1 1 0
         * 7: 1 1 1
         *
         * Schenario: Acquire Fails
         * Expected Result:
         * Nothing should happen if acquire failed
         */
        $variant = 'acquire[0] during[x] release[x]';
        $exception = new \RuntimeException('Could not acquire resource');

        $acquireFn = function (int $x) use ($exception) {
            throw $exception;
        };

        $acquire = KleisliIO::liftImpure($acquireFn);
        $mockDuring = $this->createMock(KleisliIO::class);
        $mockDuring->expects($this->never())->method('run');
        $mockRelease = $this->createMock(KleisliIO::class);
        $mockRelease->expects($this->never())->method('run');

        $arrow = KleisliIOOps::bracket($acquire, $mockDuring, $mockRelease);
        $result = $arrow->run(10);
        $this->assertTrue($result->isFailure(), "{$variant}: isFailure");
        $this->assertSame($exception, $result->unwrapFailure($this->createClosureNotCalled()), "{$variant}: isFailure");
    }

    public function testBracketV5()
    {
        /**
         * each level van fail..
         *  0 = fail
         *  1 = succeed
         * acquire during release.
         *
         * 1: 0 0 0 < 0 x x
         * 2: 0 0 1 < 0 x x
         * 3: 0 1 0 < 0 x x
         * 4: 0 1 1 < 0 x x
         * 5: 1 0 0 <<
         * 6: 1 1 0
         * 7: 1 1 1
         *
         * Schenario: Acquire Succeeds, During and Release fail
         * Expected Result:
         * return tuple of results
         * first : during result
         * second : release result
         */
        $variant = 'acquire[1] during[0] release[0]';
        $exceptionDuring = new \RuntimeException('During expection');
        $exceptionRelease = new \RuntimeException('Release exception');

        $acquireFn = fn (int $x) => $x;

        $duringFn = function (int $x) use ($exceptionDuring) {
            throw $exceptionDuring;
        };
        $releaseFn = function (int $x) use ($exceptionRelease) {
            throw $exceptionRelease;
        };

        $acquire = KleisliIO::liftPure($acquireFn);
        $during = KleisliIO::liftImpure($duringFn);
        $release = KleisliIO::liftImpure($releaseFn);

        $arrow = KleisliIOOps::bracket($acquire, $during, $release);
        $result = $arrow->run(10);

        $expectedResult = Tuple::create(
            IOMonad::fail($exceptionDuring),
            IOMonad::fail($exceptionRelease)
        );

        $this->assertTrue($result->isSuccess(), "{$variant}: isSuccess");
        // @phpstan-ignore argument.type
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()), "{$variant}: result");
    }

    public function testBracketV6()
    {
        /**
         * each level van fail..
         *  0 = fail
         *  1 = succeed
         * acquire during release.
         *
         * 1: 0 0 0 < 0 x x
         * 2: 0 0 1 < 0 x x
         * 3: 0 1 0 < 0 x x
         * 4: 0 1 1 < 0 x x
         * 5: 1 0 0
         * 6: 1 1 0 <<
         * 7: 1 1 1
         *
         * Schenario: Acquire and During Succeed, Release fails
         * Expected Result:
         * return tuple of results
         * first : during result
         * second : release result
         */
        $variant = 'acquire[1] during[1] release[0]';
        $exceptionRelease = new \RuntimeException('Release exception');

        $acquireFn = fn (int $x) => $x;

        $duringFn = fn (int $x) => $x + 10;
        $releaseFn = function (int $x) use ($exceptionRelease) {
            throw $exceptionRelease;
        };

        $acquire = KleisliIO::liftPure($acquireFn);
        $during = KleisliIO::liftImpure($duringFn);
        $release = KleisliIO::liftImpure($releaseFn);

        $arrow = KleisliIOOps::bracket($acquire, $during, $release);
        $result = $arrow->run(10);

        $expectedResult = Tuple::create(
            IOMonad::pure(20),
            IOMonad::fail($exceptionRelease)
        );

        $this->assertTrue($result->isSuccess(), "{$variant}: isSuccess");
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()), "{$variant}: result");
    }

    public function testBracketV7()
    {
        /**
         * each level van fail..
         *  0 = fail
         *  1 = succeed
         * acquire during release.
         *
         * 1: 0 0 0 < 0 x x
         * 2: 0 0 1 < 0 x x
         * 3: 0 1 0 < 0 x x
         * 4: 0 1 1 < 0 x x
         * 5: 1 0 0
         * 6: 1 1 0
         * 7: 1 1 1 <<
         *
         * Schenario: Acquire, During and Release Succeed
         * Expected Result:
         * return tuple of results
         * first : during result
         * second : release result
         */
        $variant = 'acquire[1] during[1] release[1]';

        $acquireFn = fn (int $x) => $x;

        $duringFn = fn (int $x) => $x + 10;

        /**
         * @var callable|MockObject $releaseFnMock
         */
        $releaseFnMock = $this
            ->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock()
        ;
        $releaseFnMock->expects($this->once())->method('__invoke')->with(10);

        $acquire = KleisliIO::liftPure($acquireFn);
        $during = KleisliIO::liftImpure($duringFn);
        $release = KleisliIO::liftImpure($releaseFnMock);

        $arrow = KleisliIOOps::bracket($acquire, $during, $release);
        $result = $arrow->run(10);

        $expectedResult = Tuple::create(
            IOMonad::pure(20),
            IOMonad::pure(null),
        );

        $this->assertTrue($result->isSuccess(), "{$variant}: isSuccess");
        $this->assertEquals($expectedResult, $result->unwrapSuccess($this->createClosureNotCalled()), "{$variant}: result");
    }
}
