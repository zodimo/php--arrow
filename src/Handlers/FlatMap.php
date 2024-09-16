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
class FlatMap
{
    private KleisliIO $that;
    private array $fs;

    private function __construct(KleisliIO $that, array $fs)
    {
        $this->that = $that;
        $this->fs = $fs;
    }

    /**
     * This function is like arr.
     *
     * @template _INPUT
     * @template _OUTPUT
     * @template _ERR
     *
     * @param KleisliIO<_INPUT,_OUTPUT,_ERR> $that
     *
     * @return FlatMap<_INPUT,_OUTPUT,_ERR>
     */
    public static function initializeWith(KleisliIO $that): FlatMap
    {
        return new self($that, []);
    }

    /**
     * This function is like andThen or >>>(compose) but is stacksafe.s.
     *
     * @template _OUTPUTF
     * @template _ERRF
     *
     * @param callable(OUTPUT):KleisliIO<INPUT,_OUTPUTF,_ERRF> $f
     *
     * @return FlatMap<INPUT,_OUTPUTF,_ERRF|ERR>
     */
    public function addF(callable $f): FlatMap
    {
        $clone = clone $this;
        $clone->fs[] = $f;

        return $clone;
    }

    /**
     * @param INPUT $value
     *
     * @return IOMonad<OUTPUT,ERR>
     */
    public function run($value): IOMonad
    {
        // -- | @since base-4.14.0.0
        // instance Monad m => Monad (Kleisli m a) where
        //   Kleisli f >>= k = Kleisli $ \x -> f x >>= \a -> runKleisli (k a) x
        //   {-# INLINE (>>=) #-}

        $stack = $this->fs;
        $that = $this->that;
        $result = $that->run($value);

        while ($result->isSuccess()) {
            $f = array_shift($stack);
            if (is_null($f)) {
                return $result;
            }

            $result = $result->match(
                fn ($b) => call_user_func($f, $b)->run($value),
                fn () => $result,
            );
        }

        return $result;
    }

    /**
     * @return KleisliIO<INPUT,OUTPUT,ERR>
     */
    public function asKleisliIO(): KleisliIO
    {
        return KleisliIO::arr(fn ($value) => $this->run($value));
    }
}
