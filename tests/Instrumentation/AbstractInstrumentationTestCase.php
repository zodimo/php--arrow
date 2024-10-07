<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Tests\Instrumentation;

use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Zodimo\BaseReturn\Option;

abstract class AbstractInstrumentationTestCase extends TestCase
{
    protected \ArrayObject $storage;
    private ScopeInterface $scope;

    public function setUp(): void
    {
        $this->storage = new \ArrayObject();
        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage)
            )
        );

        $this->scope = Configurator::create()
            ->withTracerProvider($tracerProvider)
            ->activate()
        ;
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->scope->detach();
    }

    public function hasSpanAtOffset(int $offset): bool
    {
        return $this->storage->offsetExists($offset);
    }

    /**
     * @return Option<ImmutableSpan>
     */
    public function getSpanAtOffset(int $offset): Option
    {
        if ($this->hasSpanAtOffset($offset)) {
            return Option::some($this->storage->offsetGet($offset));
        }

        return Option::none();
    }
}
