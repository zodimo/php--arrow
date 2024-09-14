<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Internal;

use Zodimo\BaseReturn\IOMonad;
use Zodimo\BaseReturn\Option;

/**
 * @template INPUT
 * @template OUTPUT
 * @template ERR
 * @template ERRPREV
 */
class StartedFiber
{
    /**
     * @var Option<SteppableKleisliIO<INPUT,OUTPUT,ERR,ERRPREV>>
     */
    private Option $steppableArrowOption;

    /**
     * @var Option<IOMonad<OUTPUT,ERRPREV>>
     */
    private Option $resultOption;

    /**
     * @param Option<SteppableKleisliIO<INPUT,OUTPUT,ERR,ERRPREV>> $steppableArrowOption
     * @param Option<IOMonad<OUTPUT,ERRPREV>>                      $resultOption
     */
    private function __construct(Option $steppableArrowOption, Option $resultOption)
    {
        $this->steppableArrowOption = $steppableArrowOption;
        $this->resultOption = $resultOption;
    }

    /**
     * @template _OUTPUT
     * @template _ERR
     *
     * @param IOMonad<_OUTPUT,_ERR> $result
     *
     * @return StartedFiber<mixed,_OUTPUT,mixed,_ERR>
     */
    public static function createFromResult(IOMonad $result): StartedFiber
    {
        return new self(
            Option::none(),
            Option::some($result)
        );
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _ERR
     * @template _ERRPREV
     *
     * @param SteppableKleisliIO<_INPUT,_OUTPUT,_ERR,_ERRPREV> $steppableArrow
     *
     * @return StartedFiber<_INPUT,_OUTPUT,_ERR,_ERRPREV>
     */
    public static function createFromSteppableArrow(SteppableKleisliIO $steppableArrow): StartedFiber
    {
        return new self(
            Option::some($steppableArrow),
            Option::none(),
        );
    }

    /**
     * @param null|mixed $input
     */
    public function resume($input = null): StartedFiber
    {
        return $this->steppableArrowOption->match(
            function ($steppableArrow) use ($input) {
                $r = $steppableArrow->runStep($input);

                return $r->getResult()->match(
                    fn ($result) => StartedFiber::createFromResult($result),
                    fn () => StartedFiber::createFromSteppableArrow($r)
                );
            },
            fn () => $this
        );
    }

    public function isSuspended()
    {
        // it is suspended if we have something left to do..
        return $this->steppableArrowOption->isSome();
    }

    /**
     * @return Option<IOMonad<OUTPUT,ERRPREV>>
     */
    public function getResult(): Option
    {
        return $this->resultOption;
    }

    public function run(): FinishedFiber
    {
        $fiber = $this;
        while ($fiber->isSuspended()) {
            $fiber = $fiber->resume();
        }

        return $fiber->resultOption->match(
            fn ($result) => FinishedFiber::create($result),
            fn () => FinishedFiber::create(IOMonad::fail('StartedFiber: This should not have happened.')),
        );
    }
}
