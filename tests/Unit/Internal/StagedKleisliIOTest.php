<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Unit\Internal;

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

    public function testCanCreateWithoutArrow()
    {
        $input = $this->createMock(IOMonad::class);
        $stage = StagedKleisliIO::stageWithoutArrow($input);
        $this->assertInstanceOf(StagedKleisliIO::class, $stage);
    }

    public function testCanCreateWithArrow()
    {
        $input = $this->createMock(IOMonad::class);
        $arrow = $this->createMock(KleisliIO::class);
        $stage = StagedKleisliIO::stageWithArrow($input, $arrow);
        $this->assertInstanceOf(StagedKleisliIO::class, $stage);
    }
}
