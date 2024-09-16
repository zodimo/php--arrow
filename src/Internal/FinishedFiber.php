<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Internal;

use Zodimo\BaseReturn\IOMonad;

/**
 * @template OUTPUT
 * @template ERR
 */
class FinishedFiber
{
    /**
     * @var IOMonad<OUTPUT,ERR>
     */
    private IOMonad $result;

    /**
     * @param IOMonad<OUTPUT,ERR> $result
     */
    private function __construct(IOMonad $result)
    {
        $this->result = $result;
    }

    /**
     * @template _OUTPUT
     * @template _ERR
     *
     * @param IOMonad<_OUTPUT,_ERR> $result
     *
     * @return FinishedFiber<_OUTPUT,_ERR>
     */
    public static function create(IOMonad $result): FinishedFiber
    {
        return new self($result);
    }

    public function getResult(): IOMonad
    {
        return $this->result;
    }
}
