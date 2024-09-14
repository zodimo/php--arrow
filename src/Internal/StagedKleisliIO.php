<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Internal;

use Zodimo\Arrow\KleisliIO;
use Zodimo\BaseReturn\IOMonad;
use Zodimo\BaseReturn\Option;
use Zodimo\BaseReturn\Tuple;

/**
 * This may be a defunctionalized continuation of a computation.
 *
 * @template INPUT
 * @template OUTPUT
 * @template ERR
 * @template ERRPREV
 */
class StagedKleisliIO
{
    /**
     * @var Tuple<IOMonad<INPUT,ERRPREV>,Option<KleisliIO<INPUT,OUTPUT,ERR>>>
     */
    private Tuple $context;

    /**
     * @param IOMonad<INPUT,ERRPREV>              $input
     * @param Option<KleisliIO<INPUT,OUTPUT,ERR>> $arrowOption
     */
    private function __construct($input, Option $arrowOption)
    {
        $this->context = Tuple::create($input, $arrowOption);
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _ERR
     * @template _ERRPREV
     *
     * @param IOMonad<_INPUT,_ERRPREV>       $input
     * @param KleisliIO<_INPUT,_OUTPUT,_ERR> $arrow
     *
     * @return StagedKleisliIO<_INPUT,_OUTPUT,_ERR,_ERRPREV>
     */
    public static function stageWithArrow($input, KleisliIO $arrow): StagedKleisliIO
    {
        return new self($input, Option::some($arrow));
    }

    /**
     * @template _INPUT
     * @template _ERR
     *
     * @param IOMonad<_INPUT,_ERR> $input
     *
     * @return StagedKleisliIO<_INPUT,_INPUT,_ERR, mixed>
     */
    public static function stageWithoutArrow($input): StagedKleisliIO
    {
        return new self($input, Option::none());
    }

    /**
     * @return IOMonad<OUTPUT, ERR>
     */
    public function resume(): IOMonad
    {
        $previousResult = $this->context->fst();
        $arrowOption = $this->context->snd();

        return $previousResult->match(
            function ($input) use ($arrowOption, $previousResult) {
                return $arrowOption->match(
                    function ($arrow) use ($input) {
                        return $arrow->run($input);
                    },
                    fn () => $previousResult
                );
            },
            fn ($_) => $previousResult
        );
    }

    /**
     * @param callable(INPUT):INPUT $f
     *
     * @return StagedKleisliIO<INPUT,OUTPUT,ERR,ERRPREV>
     */
    public function mapInput(callable $f): StagedKleisliIO
    {
        $input = call_user_func($f, $this->context->fst());

        return new self($input, $this->context->snd());
    }

    /**
     * @return Tuple<IOMonad<INPUT,ERRPREV>,Option<KleisliIO<INPUT,OUTPUT,ERR>>>
     */
    public function getContext(): Tuple
    {
        return $this->context;
    }
}
