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
    private StagedKleisliIO $stageKio;

    /**
     * @param StagedKleisliIO<INPUT,OUTPUT,ERR,ERRPREV> $stageKio
     */
    private function __construct(StagedKleisliIO $stageKio)
    {
        $this->stageKio = $stageKio;
    }

    /**
     * @template _INPUT
     * @template _OUTPUT
     * @template _ERR
     * @template _ERRPREV
     *
     * @param StagedKleisliIO<_INPUT,_OUTPUT,_ERR,_ERRPREV> $stageKio
     *
     * @return SteppableKleisliIO<_INPUT,_OUTPUT,_ERR,_ERRPREV>
     */
    public static function augment(StagedKleisliIO $stageKio)
    {
        return new self($stageKio);
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
            $input = $this->stageKio->getContext()->fst();
        }

        return $this->stageKio->getContext()->snd()->match(
            function (KleisliIO $kio) use ($input) {
                $operationTag = $kio->getTag();

                switch ($operationTag) {
                    case KleisliIO::TAG_ID:
                        // how to widen the error type ?
                        // @phpstan-ignore argument.type
                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($input));

                    case KleisliIO::TAG_ARR:
                        $k = $kio->getArg('f');

                        $result = $input->flatMap(fn ($x) => call_user_func($k, $x));

                        // @phpstan-ignore argument.type
                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result));

                    case KleisliIO::TAG_LIFT_PURE:
                        $k = $kio->getArg('f');

                        $result = $input->flatMap(fn ($x) => IOMonad::pure(call_user_func($k, $x)));

                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result));

                    case KleisliIO::TAG_LIFT_IMPURE:
                        $k = $kio->getArg('f');

                        $result = $input->flatMap(function ($x) use ($k) {
                            try {
                                return IOMonad::pure(call_user_func($k, $x));
                            } catch (\Throwable $e) {
                                return IOMonad::fail($e);
                            }
                        });

                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result));

                    case KleisliIO::TAG_FLAT_MAP:
                        $that = $kio->getArg('that');
                        $fs = $kio->getArg('fs');

                        // @phpstan-ignore argument.type
                        return FlatMap::create($that, $fs)->runStep($input);

                    case KleisliIO::TAG_AND_THEN:
                        $ks = $kio->getArg('ks');

                        // @phpstan-ignore argument.type
                        return AndThen::create($ks)->runStep($input);

                    case KleisliIO::TAG_PROMPT:
                        $k = $kio->getArg('k');

                        // we need a steppable prompt..
                        // currently the control kio is one step
                        $arrow = Prompt::create($k);

                        // @phpstan-ignore argument.type
                        return SteppableKleisliIO::augment(StagedKleisliIO::stageWithArrow($input, $arrow));

                    default:
                        // should this be a panic ?
                        $error = new \InvalidArgumentException('SteppableKleisliIO: [BUG] Unknown operation: '.$kio->getTag());

                        throw $error;
                        // return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow(IOMonad::fail($error)));
                }
            },
            fn () => $this
        );
    }

    public function hasMoreSteps(): bool
    {
        return $this->stageKio->getContext()->snd()->isSome();
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
            return Option::some($this->stageKio->getContext()->fst());
        }

        return Option::none();
    }
}
