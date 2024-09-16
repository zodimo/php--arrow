<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Handlers\Steppable;

use Zodimo\Arrow\Internal\Operation;
use Zodimo\Arrow\Internal\StagedKleisliIO;
use Zodimo\Arrow\Internal\SteppableKleisliIO;
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

    public static function create(array $arrows): self
    {
        return new self($arrows);
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
        $ks = $this->arrows;

        /**
         * @var KleisliIO $k
         */
        $k = array_shift($ks);
        $result = $input->flatMap(function ($x) use ($k) {
            return $k->run($x);
        });
        // $nextK = array_shift($ks);

        return $result->match(
            function ($x) use ($ks, $result) {
                if (count($ks) > 0) {
                    $arrow = KleisliIO::create(
                        Operation::create(KleisliIO::TAG_AND_THEN)
                            ->setArg('ks', $ks)
                    );

                    return SteppableKleisliIO::augment(StagedKleisliIO::stageWithArrow($result, $arrow));
                }

                return SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result));
            },
            fn ($_) => SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result))
        );
    }
}
