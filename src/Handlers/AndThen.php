<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Handlers;

use Zodimo\Arrow\KleisliIO;
use Zodimo\BaseReturn\IOMonad;

/**
 * @template INPUT
 * @template OUTPUT
 * @template ERR
 */
class AndThen
{
    private array $arrows;

    private function __construct(array $arrows)
    {
        $this->arrows = $arrows;
    }

    /**
     * @template _INPUT of mixed
     *
     * @return AndThen<_INPUT, _INPUT, mixed>
     *
     * @phpstan-ignore method.templateTypeNotInParameter
     */
    public static function id(): AndThen
    {
        return new self([KleisliIO::id()]);
    }

    /**
     * This function is like andThen or >>>(compose) but is stacksafe.s.
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param KleisliIO<OUTPUT,_OUTPUTK, _ERRK> $arrow
     *
     * @return AndThen<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function addArrow(KleisliIO $arrow): AndThen
    {
        $clone = clone $this;
        $clone->arrows[] = $arrow;

        return $clone;
    }

    /**
     * @param INPUT $value
     *
     * @return IOMonad<OUTPUT ,ERR>
     */
    public function run($value)
    {
        // the iterative, stack safe version
        // from monad to monad...
        $stack = $this->arrows;
        $result = KleisliIO::id()->run($value);
        while (true) {
            if ($result->isFailure()) {
                return $result;
            }
            $next = array_shift($stack);

            if (!$next instanceof KleisliIO) {
                break;
            }
            $result = $result->flatmap(fn ($v) => $next->run($v));
        }

        return $result;
    }

    /**
     * @return KleisliIO<INPUT, OUTPUT ,ERR>
     */
    public function asKleisliIO(): KleisliIO
    {
        return KleisliIO::arr(fn ($value) => $this->run($value));
    }
}
