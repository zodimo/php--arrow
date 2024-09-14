<?php

declare(strict_types=1);

namespace Zodimo\Arrow;

class DelimitedKIO
{
    public static function prompt(KleisliIO $k): KleisliIO
    {
        $compositionTag = KleisliEffect::createTag('composition');
        $controlTag = KleisliEffect::createTag('control');

        $isComposition = fn (string $tag): bool => $compositionTag == $tag;

        /**
         * @var callable(array<KleisliEffect>):Option<Tuple<int,KleisliEffect>>
         */
        $getControlEffect = function (array $effects) use ($controlTag): Option {
            foreach ($effects as $index => $effect) {
                if ($controlTag === $effect->getTag()) {
                    return Option::some(Tuple::create($index, $effect));
                }
            }

            return Option::none();
        };

        $getEffects = function (KleisliEffect $k) use ($isComposition): array {
            if ($isComposition($k->getTag())) {
                return $k->getArg('effects');
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

                    if ($hole instanceof KleisliEffect) {
                        $newEffectStack = [
                            ...$initialEffects,
                            KleisliEffect::prompt($hole),
                            ...$afterEffects,
                        ];

                        return KleisliEffect::create(Operation::create($compositionTag)->setArg('effects', $newEffectStack));
                    }
                    $newEffectStack = [
                        ...$initialEffects,
                        KleisliEffect::id(),
                        ...$afterEffects,
                    ];

                    return KleisliEffect::create(Operation::create($compositionTag)->setArg('effects', $newEffectStack))->stubInput($hole);
                };

                return KleisliEffect::prompt(call_user_func($controlF, $effectStackWithHole));
            },
            fn () => $k
        );
    }
}
