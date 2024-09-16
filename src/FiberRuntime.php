<?php

declare(strict_types=1);

namespace Zodimo\Arrow;

use Zodimo\Arrow\Internal\KFiber;

class FiberRuntime
{
    /**
     * @var array<KFiber>
     */
    private array $fibers = [];

    public function __construct(array $fibers)
    {
        $this->fibers = $fibers;
    }

    public function fork(KleisliIO $kio): KFiber
    {
        $fiber = $kio->toFiber();
        $this->fibers[] = $fiber;

        return $fiber;
    }

    // public function run()
    // {
    //     while (count($this->fibers) > 0) {
    //         $fiber = array_shift($this->fibers);
    //         $nextFiber=$fiber->
    //     }
    // }
}
