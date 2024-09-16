<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Internal;

use Zodimo\Arrow\Handlers\Steppable\AndThen;
use Zodimo\Arrow\Handlers\Steppable\FlatMap;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Transformers\Prompt;
use Zodimo\BaseReturn\IOMonad;
use Zodimo\BaseReturn\Option;

/**
 * it assumes that a handles exists to perform A->E[B].
 *
 * @template INPUT
 * @template OUTPUT
 * @template ERR
 * @template ERRPREV
 */
class SteppableKleisliIO
{
    /**
     * @var StagedKleisliIO<INPUT,OUTPUT,ERR, ERRPREV>
     */
    private StagedKleisliIO $skio;

    /**
     * @param StagedKleisliIO<INPUT,OUTPUT,ERR,ERRPREV> $skio
     */
    private function __construct(StagedKleisliIO $skio)
    {
        $this->skio = $skio;
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _ERR
     * @template _ERRPREV
     *
     * @param StagedKleisliIO<_INPUT,_OUTPUT,_ERR,_ERRPREV> $skio
     *
     * @return SteppableKleisliIO<_INPUT,_OUTPUT,_ERR,_ERRPREV>
     */
    public static function augment(StagedKleisliIO $skio)
    {
        return new self($skio);
    }

    /**
     * This function allows you to inject a value into this step replacing the value from the previous step.
     *
     * @template _ERRPREV
     *
     * @param null|IOMonad<INPUT,_ERRPREV> $input
     */
    public function runStep($input = null): SteppableKleisliIO
    {
        if (is_null($input)) {
            $input = $this->skio->getContext()->fst();
        }

        return $this->skio->getContext()->snd()->match(
            function (KleisliIO $skio) use ($input) {
                $operationTag = $skio->getTag();

                switch ($operationTag) {
                    case KleisliIO::TAG_ID:
                        // how to widen the error type ?
                        // @phpstan-ignore argument.type
                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($input));

                    case KleisliIO::TAG_ARR:
                        $k = $skio->getArg('f');

                        $result = $input->flatMap(fn ($x) => call_user_func($k, $x));

                        // @phpstan-ignore argument.type
                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result));

                    case KleisliIO::TAG_LIFT_PURE:
                        $k = $skio->getArg('f');

                        $result = $input->flatMap(fn ($x) => IOMonad::pure(call_user_func($k, $x)));

                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result));

                    case KleisliIO::TAG_LIFT_IMPURE:
                        $k = $skio->getArg('f');

                        $result = $input->flatMap(function ($x) use ($k) {
                            try {
                                return IOMonad::pure(call_user_func($k, $x));
                            } catch (\Throwable $e) {
                                return IOMonad::fail($e);
                            }
                        });

                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result));

                    case KleisliIO::TAG_FLAT_MAP:
                        $that = $skio->getArg('that');
                        $fs = $skio->getArg('fs');

                        // @phpstan-ignore argument.type
                        return FlatMap::create($that, $fs)->runStep($input);

                    case KleisliIO::TAG_AND_THEN:
                        $ks = $skio->getArg('ks');

                        // @phpstan-ignore argument.type
                        return AndThen::create($ks)->runStep($input);

                    case KleisliIO::TAG_PROMPT:
                        $k = $skio->getArg('k');

                        // we need a steppable prompt..
                        // currently the control kio is one step
                        $arrow = Prompt::create($k);

                        // @phpstan-ignore argument.type
                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithArrow($input, $arrow));

                    default:
                        // should this be a panic ?
                        $error = new \InvalidArgumentException('SteppableKleisliIO: [BUG] Unknown operation: '.$skio->getTag());

                        throw $error;
                        // return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow(IOMonad::fail($error)));
                }
            },
            fn () => $this
        );
    }

    public function hasMoreSteps(): bool
    {
        return $this->skio->getContext()->snd()->isSome();
    }

    /**
     * If no more steps then the input, i.e result of pervious step is the final result.
     * only available if there are no more steps.
     *
     * @return Option<IOMonad<INPUT,ERRPREV>>
     */
    public function getResult(): Option
    {
        if (!$this->hasMoreSteps()) {
            return Option::some($this->skio->getContext()->fst());
        }

        return Option::none();
    }
}
