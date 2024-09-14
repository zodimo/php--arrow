<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Transformers;

use Zodimo\Arrow\Internal\Operation;
use Zodimo\Arrow\KleisliIO;
use Zodimo\BaseReturn\Option;
use Zodimo\BaseReturn\Tuple;

/**
 * @template INPUT
 * @template OUTPUT
 * @template ERR
 */
class Prompt
{
    public static function create(KleisliIO $k): KleisliIO
    {
        $compositionTag = KleisliIO::TAG_AND_THEN;
        $controlTag = KleisliIO::TAG_CONTROL;

        $isComposition = fn (string $tag): bool => $compositionTag == $tag;

        /**
         * @var callable(array<KleisliIO>):Option<Tuple<int,KleisliIO>>
         */
        $getControlEffect = function (array $effects) use ($controlTag): Option {
            foreach ($effects as $index => $effect) {
                if ($controlTag === $effect->getTag()) {
                    return Option::some(Tuple::create($index, $effect));
                }
            }

            return Option::none();
        };

        $getEffects = function (KleisliIO $k) use ($isComposition): array {
            if ($isComposition($k->getTag())) {
                return $k->getArg('ks');
            }

            return [$k];
        };

        /**
         * IF control effects present it will be the first..
         */
        $effects = call_user_func($getEffects, $k);

        $controlEffectOption = call_user_func($getControlEffect, $effects);

        return $controlEffectOption->match(
            function (Tuple $control) use ($compositionTag, $effects) {
                // evaluate stack with controlEffect
                $controlEffect = $control->snd();
                $controlF = $controlEffect->getArg('f');

                // hole can be an effect...
                // valid terms for the hole
                // kleisliEffect or value
                // on value, stub the stack and replace the hole with id
                // on effect put the effect in the place of the hole
                $effectStackWithHole = function ($hole) use ($control, $effects, $compositionTag) {
                    $controlIndex = $control->fst();
                    $initialEffects = array_slice($effects, 0, $controlIndex);
                    $afterEffects = ($controlIndex < count($effects)) ? array_slice($effects, $controlIndex + 1) : [];

                    if ($hole instanceof KleisliIO) {
                        $newEffectStack = [
                            ...$initialEffects,
                            KleisliIO::prompt($hole),
                            ...$afterEffects,
                        ];

                        return KleisliIO::create(Operation::create($compositionTag)->setArg('ks', $newEffectStack));
                    }
                    $newEffectStack = [
                        ...$initialEffects,
                        KleisliIO::id(),
                        ...$afterEffects,
                    ];

                    return KleisliIO::create(Operation::create($compositionTag)->setArg('ks', $newEffectStack))->stubInput($hole);
                };

                return KleisliIO::prompt(call_user_func($controlF, $effectStackWithHole));
            },
            fn () => $k
        );
    }
}
