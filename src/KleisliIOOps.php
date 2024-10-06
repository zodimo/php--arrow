<?php

declare(strict_types=1);

namespace Zodimo\Arrow;

use Zodimo\BaseReturn\Either;
use Zodimo\BaseReturn\IOMonad;
use Zodimo\BaseReturn\Tuple;

class KleisliIOOps
{
    /**
     * first :: a b c -> a (b,d) (c,d).
     *
     * return is the return from monad (a)
     *
     *
     * first (Kleisli f) = Kleisli (\ ~(b,d) -> f b >>= \c -> return (c,d)).
     *
     * f :: a -> m b
     * return of ^ this M
     * M here is IOMonad
     *
     * A piping method first that takes an arrow between two types and
     * converts it into an arrow between tuples. The first elements in
     * the tuples represent the portion of the input and output that is altered,
     * while the second elements are a third type u describing an unaltered
     * portion that bypasses the computation.
     *
     * @template _INPUT b
     * @template _OUTPUT d
     * @template _ERR
     *
     * @param KleisliIO<_INPUT,_OUTPUT,_ERR> $arrow
     *
     * @return KleisliIO<Tuple<_INPUT,mixed>,Tuple<_OUTPUT,mixed>,_ERR>
     */
    public static function first(KleisliIO $arrow): KleisliIO
    {
        /**
         * @var callable(Tuple<_INPUT, mixed>):IOMonad<Tuple<_OUTPUT, mixed>, _ERR> $func
         */
        $func = function (Tuple $args) use ($arrow) {
            $input = $args->fst();
            $d = $args->snd();

            return $arrow->run($input)->flatMap(fn ($c) => IOMonad::pure(Tuple::create($c, $d)));
        };

        // @phpstan-ignore return.type
        return KleisliIO::arr($func);
    }

    /**
     * second :: a b c -> a (d,b) (d,c)
     * second = (id ***).
     *
     * @template _INPUT b
     * @template _OUTPUT d
     * @template _ERR
     *
     * @param KleisliIO<_INPUT,_OUTPUT,_ERR> $arrow
     *
     * @return KleisliIO<Tuple<mixed,_INPUT>,Tuple<mixed,_OUTPUT>,_ERR>
     */
    public static function second(KleisliIO $arrow): KleisliIO
    {
        /**
         * @var callable(Tuple<mixed, _INPUT>):IOMonad<Tuple<mixed, _OUTPUT>, _ERR> $func
         */
        $func = function (Tuple $args) use ($arrow) {
            $input = $args->snd();
            $d = $args->fst();

            return $arrow->run($input)->flatMap(fn ($c) => IOMonad::pure(Tuple::create($d, $c)));
        };

        // @phpstan-ignore return.type
        return KleisliIO::arr($func);
    }

    /**
     * "***".
     * A merging operator *** that can take two arrows, possibly with different
     * input and output types, and fuse them into one arrow between two compound types.
     *
     * (***) :: a b c -> a b' c' -> a (b,b') (c,c')
     * f *** g = first f >>> arr swap >>> first g >>> arr swap
     *  where swap ~(x,y) = (y,x)
     *
     * @template _INPUTF
     * @template _INPUTG
     * @template _OUTPUTF
     * @template _OUTPUTG
     * @template _ERRF
     * @template _ERRG
     *
     * @param KleisliIO<_INPUTF,_OUTPUTF,_ERRF> $f
     * @param KleisliIO<_INPUTG,_OUTPUTG,_ERRG> $g
     *
     * @return KleisliIO<Tuple<_INPUTF,_INPUTG>,Tuple<_OUTPUTF,_OUTPUTG>,_ERRF|_ERRG>
     */
    public static function merge(KleisliIO $f, KleisliIO $g): KleisliIO
    {
        /**
         * 1:1 translation.
         * first f >>> arr swap >>> first g >>> arr swap.
         */

        // @phpstan-ignore return.type
        return KleisliIOOps::first($f)->andThen(
            // @phpstan-ignore argument.type
            KleisliIO::arr(fn (Tuple $t) => IOMonad::pure($t->swap()))
        )->andThen(
            // @phpstan-ignore argument.type
            KleisliIOOps::first($g)->andThen(KleisliIO::arr(fn (Tuple $t) => IOMonad::pure($t->swap())))
        );
    }

    /**
     * "&&&".
     * (&&&) :: a b c -> a b c' -> a b (c,c')
     * f &&& g = arr (\b -> (b,b)) >>> f *** g.
     *
     * @template _INPUT
     * @template _OUPUTF
     * @template _OUTPUTG
     * @template _ERRF
     * @template _ERRG
     *
     * @param KleisliIO<_INPUT,_OUPUTF,_ERRF>  $f
     * @param KleisliIO<_INPUT,_OUTPUTG,_ERRG> $g
     *
     * @return KleisliIO<_INPUT,Tuple<_OUPUTF,_OUTPUTG>,_ERRF|_ERRG>
     */
    public static function split(KleisliIO $f, KleisliIO $g): KleisliIO
    {
        /**
         * 1:1 translation
         * f &&& g = arr (\b -> (b,b)) >>> f *** g.
         *
         * @phpstan-ignore argument.type
         */
        return KleisliIO::arr(fn ($b) => IOMonad::pure(Tuple::create($b, $b)))->andThen(
            KleisliIOOps::merge($f, $g)
        );
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _LEFTERR
     * @template _RIGHTERR
     *
     * @param KleisliIO<_INPUT,_OUTPUT,_LEFTERR>  $onLeft
     * @param KleisliIO<_INPUT,_OUTPUT,_RIGHTERR> $onRight
     *
     * @return KleisliIO<Either<_INPUT,_INPUT>,_OUTPUT,_LEFTERR|_RIGHTERR>
     */
    public static function choice(KleisliIO $onLeft, KleisliIO $onRight): KleisliIO
    {
        /**
         * @var callable(Either<_INPUT, _INPUT>):IOMonad<_OUTPUT, _LEFTERR|_RIGHTERR>
         */
        $func = function (Either $input) use ($onLeft, $onRight) {
            return $input->match(
                fn ($left) => $onLeft->run($left),
                fn ($right) => $onRight->run($right)
            );
        };

        // @phpstan-ignore return.type
        // @phpstan-ignore return.type
        return KleisliIO::arr($func);
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _CONDERR
     * @template _THENERR
     * @template _ELSEERR
     *
     * @param KleisliIO< _INPUT,bool,_CONDERR>    $cond
     * @param KleisliIO< _INPUT,_OUTPUT,_THENERR> $then
     * @param KleisliIO< _INPUT,_OUTPUT,_ELSEERR> $else
     *
     * @return KleisliIO< _INPUT,_OUTPUT,_ELSEERR|_THENERR>
     */
    public static function ifThenElse(KleisliIO $cond, KleisliIO $then, KleisliIO $else): KleisliIO
    {
        /**
         * @var callable(_INPUT):IOMonad<_OUTPUT, _ELSEERR|_THENERR>
         */
        $func = function ($input) use ($cond, $then, $else) {
            return $cond->run($input)->match(
                function ($condResult) use ($input, $then, $else) {
                    if ($condResult) {
                        return $then->run($input);
                    }

                    return $else->run($input);
                },
                fn ($err) => IOMonad::fail($err)
            );
        };

        return KleisliIO::arr($func);
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _CHECKERR
     * @template _BODYERR
     *
     * @param KleisliIO<_INPUT,bool,_CHECKERR>   $check
     * @param KleisliIO<_INPUT,_OUTPUT,_BODYERR> $body
     *
     * @return KleisliIO<_INPUT,_OUTPUT,_BODYERR|_CHECKERR>
     */
    public static function whileDo(KleisliIO $check, KleisliIO $body): KleisliIO
    {
        /**
         * @var callable(_INPUT):IOMonad<_OUTPUT, _BODYERR|_CHECKERR>
         */
        $func = function ($value) use ($check, $body) {
            $a = $value;
            while (true) {
                $checkResult = $check->run($a);
                if ($checkResult->isFailure()) {
                    return $checkResult;
                }
                if ($checkResult->unwrapSuccess(fn ($_) => false)) {
                    $bodyResult = $body->run($a);
                    if ($bodyResult->isFailure()) {
                        return $bodyResult;
                    }
                    $a = $bodyResult->unwrapSuccess(fn ($_) => $a);
                } else {
                    break;
                }
            }

            return IOMonad::pure($a);
        };

        return KleisliIO::arr($func);
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _ERR
     * @template _OUTPUTF
     * @template _ERRF
     * @template _ERRG
     *
     * @param KleisliIO<_INPUT,_OUTPUT,_ERR>    $acquire
     * @param KleisliIO<_OUTPUT,_OUTPUTF,_ERRF> $during
     * @param KleisliIO<_OUTPUT,null,_ERRG>     $release
     *
     * @return KleisliIO<_INPUT,Tuple<IOMonad<_OUTPUTF,_ERRF|\Throwable>,IOMonad<null,_ERRG>>,never>
     */
    public static function bracket(KleisliIO $acquire, KleisliIO $during, KleisliIO $release): KleisliIO
    {
        /**
         * @var callable(_INPUT):IOMonad<Tuple<IOMonad<_OUTPUTF, _ERRF|\Throwable>, IOMonad<null, _ERRG>, _ERR>
         */
        $func = function ($input) use ($acquire, $during, $release) {
            $acquireResult = $acquire->run($input);

            return $acquireResult->flatMap(
                function ($acquiredResource) use ($during, $release) {
                    try {
                        $duringResult = $during->run($acquiredResource);
                    } catch (\Throwable $duringError) {
                        $duringResult = IOMonad::fail($duringError);
                    }

                    try {
                        $releaseResult = $release->run($acquiredResource);
                    } catch (\Throwable $releaseError) {
                        $releaseResult = IOMonad::fail($releaseError);
                    }

                    return IOMonad::pure(Tuple::create($duringResult, $releaseResult));
                }
            );
        };

        return KleisliIO::arr($func);
    }
}
