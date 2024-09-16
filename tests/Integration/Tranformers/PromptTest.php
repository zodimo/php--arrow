<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Zodimo\Arrow\KleisliIO;
use Zodimo\Arrow\Tests\MockClosureTrait;
use Zodimo\Arrow\Transformers\Prompt;

/**
 * @internal
 *
 * @coversNothing
 */
class PromptTest extends TestCase
{
    use MockClosureTrait;

    public function testCanCreate()
    {
        $prompt = Prompt::create(KleisliIO::id());
        $this->assertInstanceOf(KleisliIO::class, $prompt);
    }

    public function testCanHandleControl()
    {
        $prompt = Prompt::create(KleisliIO::control(fn ($k) => call_user_func($k, KleisliIO::id())));
        $this->assertInstanceOf(KleisliIO::class, $prompt);
    }

    public function testPC1()
    {
        $prompt = Prompt::create(
            KleisliIO::liftPure(fn (int $x) => $x + 10)
                ->andThen(
                    KleisliIO::control(fn ($k) => call_user_func($k, KleisliIO::id()))
                )
        );

        $result = $prompt->run(10);
        $this->assertEquals(20, $result->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testPC2()
    {
        $mockedEffect = $this->createMock(KleisliIO::class);
        $mockedEffect->expects($this->never())->method('getArg');

        $arrow = KleisliIO::liftPure(fn ($x) => $x + 100)
            ->andThen(
                KleisliIO::prompt(
                    KleisliIO::id()
                        ->andThen($mockedEffect)
                        ->control(
                            function (callable $_) {
                                return KleisliIO::liftPure(fn ($x) => $x + 10);
                            }
                        )
                        ->andThen($mockedEffect)
                )
            )
        ;

        $this->assertEquals(100 + 10 + 10, $arrow->run(10)->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testPC3a()
    {
        $arrow = KleisliIO::liftPure(fn ($x) => $x + 100)
            ->andThen(
                KleisliIO::prompt(
                    KleisliIO::liftPure(fn ($x) => $x + 100)
                        ->andThen(
                            KleisliIO::control(
                                function (callable $k) {
                                    return call_user_func($k, KleisliIO::liftPure(fn ($x) => $x + 10));
                                }
                            )
                        )
                )
            )
        ;

        $this->assertEquals(10 + 100 + 100 + 10, $arrow->run(10)->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testPC3b()
    {
        $arrow = KleisliIO::liftPure(fn ($x) => $x + 100)
            ->andThen(
                KleisliIO::prompt(
                    KleisliIO::liftPure(fn ($x) => $x + 100)
                        ->andThen(
                            KleisliIO::control(
                                function (callable $k) {
                                    return call_user_func($k, KleisliIO::liftPure(fn ($x) => $x + 10));
                                }
                            )
                                ->andThen(KleisliIO::liftPure(fn ($x) => $x + 200))
                        )
                )
            )
        ;

        $this->assertEquals(100 + 100 + 10 + 200 + 10, $arrow->run(10)->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testPC4()
    {
        $arrow = KleisliIO::liftPure(fn ($x) => $x + 100) // not pass on because input stubbed in control
            ->andThen(
                KleisliIO::prompt(
                    KleisliIO::liftPure(fn ($x) => $x + 100)
                        ->andThen(
                            KleisliIO::control(
                                function (callable $k) {
                                    // all the effect will be run eveytime k is called...
                                    // stub the prompt input
                                    return call_user_func($k, KleisliIO::liftPure(fn ($x) => $x + 10))->stubInput(10);
                                }
                            )
                        )
                )
            )
        ;

        $this->assertEquals(100 + 10 + 10, $arrow->run(null)->unwrapSuccess($this->createClosureNotCalled()));
    }

    public function testCanReturnTheContinuation()
    {
        $arrow = KleisliIO::prompt(
            KleisliIO::liftPure(fn (int $x) => $x + 100)
                ->andThen(
                    KleisliIO::control(
                        function (callable $k) {
                            // we are just thunking the application of the $k
                            return KleisliIO::id()->flatMap(
                                fn ($x) => call_user_func(
                                    $k,
                                    KleisliIO::liftPure(fn (int $x) => $x * 2)
                                )->stubInput($x)
                            );
                        }
                    )
                )
        );

        $result = $arrow->run(10);
        $this->assertEquals((10 + 100) * 2, $result->unwrapSuccess($this->createClosureNotCalled()));
    }
}
