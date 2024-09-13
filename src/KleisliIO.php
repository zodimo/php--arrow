<?php

declare(strict_types=1);

namespace Zodimo\Arrow;

use Zodimo\Arrow\Handlers\AndThen;
use Zodimo\Arrow\Handlers\FlatMap;
use Zodimo\Arrow\Internal\Operation;
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
    private const TAG_ID = 'id';
    private const TAG_ARR = 'arr';
    private const TAG_LIFT_PURE = 'lift-pure';
    private const TAG_LIFT_IMPURE = 'lift-impure';
    private const TAG_AND_THEN = 'and-then';
    private const TAG_FLAT_MAP = 'flat-map';
    private Operation $_operation;

    private function __construct(Operation $operation)
    {
        $this->_operation = $operation;
    }

    /**
     * ">>>".
     * A composition operator >>> that can attach a second arrow to a first
     * as long as the first function’s output and the second’s input have matching types.
     *
     * -- | Left-to-right composition
     * (>>>) :: Category cat => cat a b -> cat b c -> cat a c
     * f >>> g = g . f
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param KleisliIO<OUTPUT,_OUTPUTK, _ERRK> $g
     *
     * @return KleisliIO<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function andThen(KleisliIO $g): KleisliIO
    {
        $ks = [];
        if (self::TAG_AND_THEN == $this->getTag()) {
            $ks = [...$this->getArg('ks')];
        } else {
            $ks = [$this];
        }

        if (self::TAG_AND_THEN == $g->getTag()) {
            $ks = [...$ks, ...$g->getArg('ks')];
        } else {
            $ks[] = $g;
        }

        return new KleisliIO(
            Operation::create(self::TAG_AND_THEN)
                ->setArg('ks', $ks)
        );
    }

    /**
     * shortcut for andThen(KleisliIO::arr(f)).
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param callable(OUTPUT):IOMonad<_OUTPUTK, _ERRK> $f
     *
     * @return KleisliIO<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function andThenK(callable $f): KleisliIO
    {
        return $this->andThen(KleisliIO::arr($f));
    }

    /**
     * instance Monad m => Monad (Kleisli m a) where
     *   Kleisli f >>= k = Kleisli $ \x -> f x >>= \a -> runKleisli (k a) x.
     *
     * @template _OUTPUTK
     * @template _ERRK
     *
     * @param callable(OUTPUT):KleisliIO<INPUT,_OUTPUTK, _ERRK> $f
     *
     * @return KleisliIO<INPUT,_OUTPUTK, _ERRK|ERR>
     */
    public function flatMap(callable $f): KleisliIO
    {
        if (self::TAG_FLAT_MAP == $this->getTag()) {
            $that = $this->getArg('that');
            $fs = $this->getArg('fs');
        } else {
            $that = clone $this;
            $fs = [];
        }

        $fs[] = $f;

        return new KleisliIO(
            Operation::create(self::TAG_FLAT_MAP)
                ->setArg('that', $that)
                ->setArg('fs', $fs)
        );
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
        return new KleisliIO(Operation::create(self::TAG_ARR)->setArg('f', $f));
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
        return new KleisliIO(Operation::create(self::TAG_LIFT_PURE)->setArg('f', $f));
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
        return new KleisliIO(Operation::create(self::TAG_ID));
    }

    /**
     * @param INPUT $value
     *
     * @return IOMonad<OUTPUT, ERR>
     */
    public function run($value): IOMonad
    {
        // return self::evaluate($this, $value);
        // handlers....

        switch ($this->getTag()) {
            case self::TAG_ID:
                // @phpstan-ignore return.type
                return IOMonad::pure($value);

            case self::TAG_ARR:
                $f = $this->getArg('f');

                return call_user_func($f, $value);

            case self::TAG_LIFT_PURE:
                $f = $this->getArg('f');

                return IOMonad::pure(call_user_func($f, $value));

            case self::TAG_LIFT_IMPURE:
                $f = $this->getArg('f');

                try {
                    return IOMonad::pure(call_user_func($f, $value));
                } catch (\Throwable $e) {
                    // @phpstan-ignore return.type
                    return IOMonad::fail($e);
                }

            case self::TAG_FLAT_MAP:
                $that = $this->getArg('that');
                $fs = $this->getArg('fs');

                $flatMap = array_reduce($fs, function (FlatMap $acc, callable $item) {
                    return $acc->addF($item);
                }, FlatMap::initializeWith($that));

                return $flatMap->asKleisliIO()->run($value);

            case self::TAG_AND_THEN:
                $ks = $this->getArg('ks');

                /**
                 * @var AndThen $andThen
                 */
                $andThen = array_reduce($ks, function ($acc, $item) {
                    return $acc->addArrow($item);
                }, AndThen::id());

                return $andThen->asKleisliIO()->run($value);

            default:
                throw new \InvalidArgumentException('Unknown operation: '.$this->getTag());
        }
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
        return new KleisliIO(Operation::create(self::TAG_LIFT_IMPURE)->setArg('f', $f));
    }

    private function getTag(): string
    {
        return $this->_operation->getTag();
    }

    /**
     * @param mixed $argName
     *
     * @return mixed
     */
    private function getArg($argName)
    {
        return $this->_operation->getArg($argName);
    }
}
