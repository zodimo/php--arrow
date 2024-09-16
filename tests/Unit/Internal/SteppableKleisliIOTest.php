<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Unit\Internal;

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\Internal\StagedKleisliIO;
use Zodimo\Arrow\Internal\SteppableKleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;

/**
 * @internal
 *
 * @coversNothing
 */
class SteppableKleisliIOTest extends TestCase
{
    use MockClosureTrait;

    public function testCanAugmentStaged()
    {
        $stagedMock = $this->createMock(StagedKleisliIO::class);
        $steppable = SteppableKleisliIO::augment($stagedMock);
        $this->assertInstanceOf(SteppableKleisliIO::class, $steppable);
    }
}
