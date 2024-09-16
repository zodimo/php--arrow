<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Integration\Internal;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Internal\StagedKleisliIO;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;
use Zodimo\BaseReturn\IOMonad;

/**
 * @internal
 *
 * @coversNothing
 */
class StagedKleisliIOTest extends TestCase
{
    use MockClosureTrait;

    public function testCanResumeWithoutArrowSuccessInput()
    {
        $input = IOMonad::pure(10);
        $stage = StagedKleisliIO::stageWithoutArrow($input);
        $result = $stage->resume();
        $this->assertEquals(10, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanResumeWithoutArrowFailureInput()
    {
        $input = IOMonad::fail(10);
        $stage = StagedKleisliIO::stageWithoutArrow($input);
        $result = $stage->resume();
        $this->assertEquals(10, $result->unwrapFailure($this->createClosureNotCalled()));
    }

    public function testCanResumeWithArrowSuccessInput()
    {
        $input = IOMonad::pure(10);
        $arrow = KleisliIO::id();
        $stage = StagedKleisliIO::stageWithArrow($input, $arrow);
        $result = $stage->resume();
        $this->assertEquals(10, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanResumeWithArrowFailureInput()
    {
        $input = IOMonad::fail(10);
        $arrow = KleisliIO::liftPure($this->createClosureNotCalled());
        $stage = StagedKleisliIO::stageWithArrow($input, $arrow);
        $result = $stage->resume();
        $this->assertEquals(10, $result->unwrapFailure($this->createClosureNotCalled()));
    }

    public function testCanResumeWithArrowSuccessInputFailureOutput()
    {
        $input = IOMonad::pure(10);
        $arrow = KleisliIO::arr(fn ($_) => IOMonad::fail('fail'));
        $stage = StagedKleisliIO::stageWithArrow($input, $arrow);
        $result = $stage->resume();
        $this->assertEquals('fail', $result->unwrapFailure($this->createClosureNotCalled()));
    }
}
