<?php

declare(strict_types=1);

namespace Zodimo\Arrow;

use Zodimo\BaseReturn\IOMonad;

/**
 * it assumes that a handles exists to perform A->E[B].
 *
 * @template INPUT
 * @template OUTPUT
 * @template ERR
 */
class KleisliIO
{
    private $f;

    /**
     * @param callable(INPUT):IOMonad<OUTPUT,ERR> $f
     */
    private function __construct($f)
    {
        $this->f = $f;
    }

    /**
     * instance Monad m => Monad (Kleisli m a) where
     * Kleisli f >>= k = Kleisli $ \x -> f x >>= \a -> runKleisli (k a) x.
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param KleisliIO<OUTPUT,_OUTPUTK, _ERRK> $k
     *
     * @return KleisliIO<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function andThen(KleisliIO $k): KleisliIO
    {
        $that = $this;

        /**
         * @var callable(INPUT):IOMonad<_OUTPUTK, mixed> $func
         */
        $func = function ($input) use ($that, $k) {
            return $that->run($input)->flatmap(fn ($value) => $k->run($value));
        };

        return new KleisliIO($func);
    }

    /**
     * shortcut for andThen(KleisliIO::arr(f)).
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param callable(OUTPUT):IOMonad<_OUTPUTK, _ERRK> $k
     *
     * @return KleisliIO<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function andThenK(callable $k): KleisliIO
    {
        return $this->andThen(KleisliIO::arr($k));
    }

    /**
     *   Kleisli f >>= k = Kleisli $ \x -> f x >>= \a -> runKleisli (k a) x.
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param callable(OUTPUT):KleisliIO<INPUT,_OUTPUTK, _ERRK> $k
     *
     * @return KleisliIO<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function flatMap(callable $k): KleisliIO
    {
        $that = $this;

        /**
         * @var callable(INPUT):IOMonad<_OUTPUTK, _ERRK|ERR> $func
         */
        $func = function ($x) use ($that, $k) {
            $result = $that->run($x); // M[B]

            return $result->flatmap(function ($a) use ($k, $x) {
                return call_user_func($k, $a)->run($x);
            });
        };

        return new KleisliIO($func);
    }

    /**
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param callable(OUTPUT):IOMonad<_OUTPUTK, _ERRK> $f
     *
     * @return KleisliIO<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function flatMapK(callable $f): KleisliIO
    {
        // same as
        // return $this->andThen(KleisliIO::arr($f));

        $that = $this;

        /**
         * @var callable(INPUT):IOMonad<_OUTPUTK, _ERRK|ERR>
         */
        $func = function ($input) use ($that, $f) {
            return $that->run($input)->flatmap($f);
        };

        return new KleisliIO($func);
    }

    /**
     * f = B=>M[C].
     *
     * @template _INPUT
     * @template _OUPUT
     * @template _ERR
     *
     * @param callable(_INPUT):IOMonad<_OUPUT, _ERR> $f
     *
     * @return KleisliIO<_INPUT, _OUPUT, _ERR>
     */
    public static function arr(callable $f): KleisliIO
    {
        return new KleisliIO($f);
    }

    /**
     * @template _INPUT
     * @template _OUPUT
     *
     * @param callable(_INPUT):_OUPUT $f
     *
     * @return KleisliIO<_INPUT, _OUPUT, mixed>
     */
    public static function liftPure(callable $f): KleisliIO
    {
        return KleisliIO::arr(fn ($x) => IOMonad::pure(call_user_func($f, $x)));
    }

    /**
     * @template _INPUT
     *
     * @return KleisliIO<_INPUT, _INPUT, mixed>
     *
     * @phpstan-ignore method.templateTypeNotInParameter
     */
    public static function id(): KleisliIO
    {
        $func = fn ($a) => IOMonad::pure($a);

        return new KleisliIO($func);
    }

    /**
     * @param INPUT $value
     *
     * @return IOMonad<OUTPUT, ERR>
     */
    public function run($value)
    {
        return call_user_func($this->f, $value);
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     *
     * @param callable(_INPUT):_OUTPUT $f
     *
     * @return KleisliIO<_INPUT, _OUTPUT, mixed>
     */
    public static function liftImpure($f): KleisliIO
    {
        /**
         * @var callable(_INPUT):IOMonad<_OUTPUT, mixed>
         */
        $try = function ($a) use ($f) {
            try {
                return IOMonad::pure(call_user_func($f, $a));
            } catch (\Throwable $e) {
                return IOMonad::fail($e);
            }
        };

        return self::arr($try);
    }
}
