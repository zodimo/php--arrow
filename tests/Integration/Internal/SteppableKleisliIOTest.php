<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Integration\Internal;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Internal\StagedKleisliIO;
use Zodimo\Arrow\Internal\SteppableKleisliIO;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;
use Zodimo\Arrow\Transformers\Prompt;
use Zodimo\BaseReturn\IOMonad;

/**
 * @internal
 *
 * @coversNothing
 */
class SteppableKleisliIOTest extends TestCase
{
    use MockClosureTrait;

    public function testCanStepWithoutArrowInputSuccess()
    {
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithoutArrow($input);
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertFalse($steppable->hasMoreSteps());
        $this->assertTrue($steppable->getResult()->isSome());
        $this->assertEquals(
            10,
            $steppable->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithoutArrowInputFailure()
    {
        $input = IOMonad::fail(10);
        $staged = StagedKleisliIO::stageWithoutArrow($input);
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertFalse($steppable->hasMoreSteps());
        $this->assertTrue($steppable->getResult()->isSome());
        $this->assertEquals(
            10,
            $steppable->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    /**
     * ID.
     */
    public function testCanStepWithArrowIdInputSuccessWithoutAdditionalInput()
    {
        /**
         * ID: 1
         * input success
         * arrow[ID] return success
         * additional input null.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::id());
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            10,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowIdInputSuccessWithAdditionalInputSuccess()
    {
        /**
         * ID: 2
         * input success
         * arrow[ID] return success
         * additional input success.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::id());
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::pure(20));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            20,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowIdInputSuccessWithAdditionalInputFailure()
    {
        /**
         * ID: 3
         * input success
         * arrow[ID] return success
         * additional input success.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::id());
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::fail(20));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            20,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    /**
     * ARR.
     */
    public function testCanStepWithArrowArrInputSuccessWithoutAdditionalInput()
    {
        /**
         * ARR: 1
         * input success
         * arrow[ARR] return success
         * additional input null.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::arr(fn (int $x) => IOMonad::pure($x + 10)));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            20,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowArrInputSuccessWithAdditionalInputSuccess()
    {
        /**
         * ARR: 2
         * input success
         * arrow[ARR] return success
         * additional input success.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::arr(fn (int $x) => IOMonad::pure($x + 10)));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::pure(20));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            30,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowArrInputSuccessWithAdditionalInputFailure()
    {
        /**
         * ARR: 3
         * input success
         * arrow[ARR] return success
         * additional input fail.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::arr(fn (int $x) => IOMonad::pure($x + 10)));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::fail(100));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            100,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowArrInputSuccessWithAdditionalInputFailureArrFailure()
    {
        /**
         * ARR: 4
         * input success
         * arrow[ARR] return failure
         * additional input success.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::arr(fn (int $x) => IOMonad::fail('failed')));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::pure(100));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            'failed',
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    /**
     * LIFT_PURE.
     */
    public function testCanStepWithArrowLiftPureInputSuccessWithoutAdditionalInput()
    {
        /**
         * LIFT_PURE: 1
         * input success
         * arrow[LIFT_PURE] return success
         * additional input null.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::liftPure(fn (int $x) => $x + 10));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            20,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowLiftPureInputSuccessWithAdditionalInputSuccess()
    {
        /**
         * LIFT_PURE: 2
         * input success
         * arrow[LIFT_PURE] return success
         * additional input success.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::liftPure(fn (int $x) => $x + 10));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::pure(20));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            30,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowLiftPureInputSuccessWithAdditionalInputFailure()
    {
        /**
         * LIFT_PURE: 3
         * input success
         * arrow[LIFT_PURE] return success
         * additional input failure.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::liftPure($this->createClosureNotCalled()));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::fail(100));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            100,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    /**
     * LIFT_IMPURE.
     */
    public function testCanStepWithArrowLiftImpureInputSuccessWithoutAdditionalInput()
    {
        /**
         * LIFT_IMPURE: 1
         * input success
         * arrow[LIFT_IMPURE] return success
         * additional input null.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::liftImpure(fn (int $x) => $x + 10));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            20,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowLiftImpureInputSuccessWithAdditionalInputSuccess()
    {
        /**
         * LIFT_IMPURE: 2
         * input success
         * arrow[LIFT_IMPURE] return success
         * additional input success.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::liftImpure(fn (int $x) => $x + 10));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::pure(20));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            30,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowLiftImpureInputSuccessWithAdditionalInputFailure()
    {
        /**
         * LIFT_IMPURE: 3
         * input success
         * arrow[LIFT_IMPURE] return success
         * additional input failure.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::liftImpure(fn (int $x) => $x + 10));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::fail(100));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            100,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowLiftImpureInputSuccessWithAdditionalInputFailureLiftImpureFailure()
    {
        /**
         * LIFT_IMPURE: 4
         * input success
         * arrow[LIFT_IMPURE] return failure
         * additional input null.
         */
        $impureExpection = new \RuntimeException('impure error');

        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow($input, KleisliIO::liftImpure(function ($_) use ($impureExpection) {
            throw $impureExpection;
        }));
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertSame(
            $impureExpection,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    /**
     * FLAT_MAP.
     */
    public function testCanStepWithArrowFlatMapInputSuccessWithoutAdditionalInput()
    {
        /**
         * FLAT_MAP: 1
         * input success
         * arrow[FLAT_MAP] return success
         * additional input null.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            KleisliIO::id()
                ->flatMap(
                    fn (int $x) => KleisliIO::liftPure(fn ($y) => $y + 10)
                )
                ->flatMap(
                    fn (int $x) => KleisliIO::liftPure(fn ($y) => $y + 10)
                )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep()->runStep()->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            30,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowFlatMapInputSuccessWithAdditionalInputSuccess()
    {
        /**
         * FLAT_MAP: 2
         * input success
         * arrow[FLAT_MAP] return success
         * additional input success.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            KleisliIO::id()
                ->flatMap(
                    fn (int $x) => KleisliIO::liftPure(fn ($y) => $y + 10)
                )
                ->flatMap(
                    fn (int $x) => KleisliIO::liftPure(fn ($y) => $y + 10)
                )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::pure(20))->runStep()->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            40,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowFlatMapInputSuccessWithAdditionalInputFailure()
    {
        /**
         * FLAT_MAP: 3
         * input success
         * arrow[FLAT_MAP] return success
         * additional input failure.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            KleisliIO::id()
                ->flatMap(
                    fn (int $x) => KleisliIO::liftPure(fn ($y) => $y + 10)
                )
                ->flatMap(
                    fn (int $x) => KleisliIO::liftPure(fn ($y) => $y + 10)
                )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::fail(100));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            100,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowFlatMapInputSuccessWithAdditionalInputFailureFlatMapFailure()
    {
        /**
         * FLAT_MAP: 4
         * input success
         * arrow[FLAT_MAP] return failure
         * additional input null.
         */
        $impureExpection = new \RuntimeException('impure error');

        $input = IOMonad::pure(10);

        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            KleisliIO::id()
                ->flatMap(
                    fn (int $x) => KleisliIO::liftPure(fn ($y) => $y + 10)
                )
                ->flatMap(
                    fn (int $x) => KleisliIO::liftImpure(function ($_) use ($impureExpection) {
                        throw $impureExpection;
                    })
                )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep()->runStep()->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertSame(
            $impureExpection,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    /**
     * AND_THEN.
     */
    public function testCanStepWithArrowAndThenInputSuccessWithoutAdditionalInput()
    {
        /**
         * AND_THEN: 1
         * input success
         * arrow[AND_THEN] return success
         * additional input null.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            KleisliIO::id()
                ->andThen(
                    KleisliIO::liftPure(fn ($y) => $y + 10)
                )
                ->andThen(
                    KleisliIO::liftPure(fn ($y) => $y + 10)
                )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep()->runStep()->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            30,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowAndThenInputSuccessWithAdditionalInputSuccess()
    {
        /**
         * AND_THEN: 2
         * input success
         * arrow[AND_THEN] return success
         * additional input success.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            KleisliIO::id()
                ->andThen(
                    KleisliIO::liftPure(fn ($y) => $y + 10)
                )
                ->andThen(
                    KleisliIO::liftPure(fn ($y) => $y + 10)
                )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::pure(20))->runStep()->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            40,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowAndThenInputSuccessWithAdditionalInputFailure()
    {
        /**
         * AND_THEN: 3
         * input success
         * arrow[AND_THEN] return success
         * additional input failure.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            KleisliIO::id()
                ->andThen(
                    KleisliIO::liftPure(fn ($y) => $y + 10)
                )
                ->andThen(
                    KleisliIO::liftPure(fn ($y) => $y + 10)
                )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep(IOMonad::fail(100));
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            100,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    public function testCanStepWithArrowAndThenInputSuccessWithAdditionalInputFailureAndThenFailure()
    {
        /**
         * AND_THEN: 4
         * input success
         * arrow[AND_THEN] return failure
         * additional input null.
         */
        $impureExpection = new \RuntimeException('impure error');

        $input = IOMonad::pure(10);

        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            KleisliIO::id()
                ->flatMap(
                    fn (int $x) => KleisliIO::liftPure(fn ($y) => $y + 10)
                )
                ->flatMap(
                    fn (int $x) => KleisliIO::liftImpure(function ($_) use ($impureExpection) {
                        throw $impureExpection;
                    })
                )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep()->runStep()->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertSame(
            $impureExpection,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapFailure($this->createClosureNotCalled())
        );
    }

    /**
     * PROMPT.
     */
    public function testCanStepWithArrowPromptInputSuccessWithoutAdditionalInput()
    {
        /**
         * PROMPT: 1
         * input success
         * arrow[PROMPT] return success
         * additional input null.
         */
        $input = IOMonad::pure(10);
        $staged = StagedKleisliIO::stageWithArrow(
            $input,
            Prompt::create(
                KleisliIO::liftPure(fn (int $x) => $x + 10)
                    ->andThen(
                        KleisliIO::control(
                            fn ($k) => call_user_func(
                                $k,
                                KleisliIO::liftPure(fn (int $x) => $x * 2)
                                    ->andThen(KleisliIO::liftPure(fn (int $x) => $x + 2))
                            )
                        )
                    )
            )
        );
        $steppable = SteppableKleisliIO::augment($staged);
        $this->assertTrue($steppable->hasMoreSteps());
        $step = $steppable->runStep();
        $step = $step->runStep();
        $step = $step->runStep();
        $this->assertFalse($step->hasMoreSteps());
        $this->assertTrue($step->getResult()->isSome());
        $this->assertEquals(
            42,
            $step->getResult()
                ->unwrap($this->createClosureNotCalled())
                ->unwrapSuccess($this->createClosureNotCalled())
        );
    }
}
