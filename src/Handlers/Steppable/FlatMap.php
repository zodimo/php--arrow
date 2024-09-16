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
class FlatMap
{
    private KleisliIO $that;
    private array $fs;

    private function __construct(KleisliIO $that, array $fs)
    {
        $this->that = $that;
        $this->fs = $fs;
    }

    public static function create(KleisliIO $kio, array $fs): self
    {
        return new self($kio, $fs);
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
        $that = $this->that;
        $fs = $this->fs;

        $k = array_shift($fs);

        // Kleisli f >>= k = Kleisli $ \x -> f x >>= \a -> runKleisli (k a) x

        $result = $input->flatMap(function ($x) use ($that) {
            return $that->run($x);
        });

        return $result->match(
            function ($x) use ($k, $fs, $result) {
                $that = call_user_func($k, $x);

                if (count($fs) > 0) {
                    $arrow = KleisliIO::create(
                        Operation::create(KleisliIO::TAG_FLAT_MAP)
                            ->setArg('that', $that)
                            ->setArg('fs', $fs)
                    );

                    return SteppableKleisliIO::augment(StagedKleisliIO::stageWithArrow($result, $arrow));
                }

                return SteppableKleisliIO::augment(StagedKleisliIO::stageWithArrow($result, $that));
            },
            fn ($_) => SteppableKleisliIO::augment(StagedKleisliIO::stageWithoutArrow($result))
        );
    }
}
