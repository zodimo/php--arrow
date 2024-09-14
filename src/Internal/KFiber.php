<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Internal;

use Zodimo\Arrow\KleisliIO;
use Zodimo\BaseReturn\IOMonad;
use Zodimo\BaseReturn\Option;

/**
 * @template INPUT
 * @template OUTPUT
 * @template ERR
 */
class KFiber
{
    private KleisliIO $arrow;

    private function __construct(KleisliIO $arrow)
    {
        $this->arrow = $arrow;
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _ERR
     *
     * @param KleisliIO<_INPUT,_OUTPUT,_ERR> $arrow
     *
     * @return KFiber<_INPUT,_OUTPUT,_ERR>
     */
    public static function create(KleisliIO $arrow): KFiber
    {
        return new self($arrow);
    }

    /**
     * @param INPUT $input
     *
     * @return StartedFiber<INPUT,OUTPUT,ERR,mixed>
     */
    public function start($input): StartedFiber
    {
        $stagedArrowResult = $this->arrow->run($input);

        $stagedArrowOption = $stagedArrowResult->match(
            function ($result) {
                if ($result instanceof SteppableKleisliIO) {
                    return Option::some($result);
                }

                return Option::none();
            },
            function ($err) {
                return Option::some(
                    SteppableKleisliIO::augment(
                        StagedKleisliIO::stageWithoutArrow(IOMonad::fail($err))
                    )
                );
            }
        );

        return $stagedArrowOption->match(
            fn ($stagedArrow) => StartedFiber::createFromSteppableArrow($stagedArrow),
            fn () => StartedFiber::createFromResult($stagedArrowResult)
        );
    }
}
