<?php

declare(strict_types=1);

namespace Zodimo\Arrow;

use Zodimo\BaseReturn\IOMonad;

/**
 * @template INPUT
 * @template OUTPUT
 * @template ERR
 */
class KleisliIOComposition
{
    private array $arrows;

    private function __construct(array $arrows)
    {
        $this->arrows = $arrows;
    }

    /**
     * This function is like arr.
     *
     * @template _INPUT
     * @template _OUTPUT
     * @template _ERR
     *
     * @param KleisliIO<_INPUT,_OUTPUT, _ERR> $arrow
     *
     * @return KleisliIOComposition<_INPUT,_OUTPUT, _ERR>
     */
    public static function initializeWith(KleisliIO $arrow): KleisliIOComposition
    {
        return new self([$arrow]);
    }

    /**
     * This function is like andThen or >>>(compose) but is stacksafe.s.
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param KleisliIO<OUTPUT,_OUTPUTK, _ERRK> $arrow
     *
     * @return KleisliIOComposition<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function addArrow(KleisliIO $arrow): KleisliIOComposition
    {
        $clone = clone $this;
        $clone->arrows[] = $arrow;

        return $clone;
    }

    /**
     * @template _INPUT
     * @template _OUPUT
     * @template _ERR
     *
     * @param callable(_INPUT):IOMonad<_OUPUT, _ERR> $f
     *
     * @return KleisliIOComposition< _INPUT, _OUPUT, _ERR>
     */
    public static function arr(callable $f): KleisliIOComposition
    {
        return self::initializeWith(KleisliIO::arr($f));
    }

    /**
     * @return KleisliIOComposition<INPUT, INPUT, mixed>
     */
    public static function id(): KleisliIOComposition
    {
        return new self([KleisliIO::id()]);
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

    /**
     * instance Monad m => Monad (Kleisli m a) where
     * Kleisli f >>= k = Kleisli $ \x -> f x >>= \a -> runKleisli (k a) x.
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param KleisliIOComposition<OUTPUT,_OUTPUTK, _ERRK> $k
     *
     * @return KleisliIOComposition<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function andThen(KleisliIOComposition $k): KleisliIOComposition
    {
        $that = $this;
        // just append all the arrows contained in K into the current
        foreach ($k->arrows as $arrow) {
            $that = $that->addArrow($arrow);
        }

        return $that;
    }
}
