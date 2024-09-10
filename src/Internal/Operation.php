<?php

declare(strict_types=1);

namespace Zodimo\Arrow\Internal;

/**
 * base class to describe the operations.
 */
class Operation
{
    private string $_tag;
    private array $_args;

    public function __construct(string $tag, array $args)
    {
        $this->_tag = $tag;
        $this->_args = $args;
    }

    public static function create(string $tag): Operation
    {
        return new self($tag, []);
    }

    /**
     * Summary of setArg.
     *
     * @param int|string $key
     * @param mixed      $arg
     */
    public function setArg($key, $arg): Operation
    {
        $clone = clone $this;
        $clone->_args[$key] = $arg;

        return $clone;
    }

    /**
     * @param int|string $key
     * @param mixed      $default
     *
     * @return mixed
     */
    public function getArg($key, $default = null)
    {
        return $this->_args[$key] ?? $default;
    }

    public function getTag(): string
    {
        return $this->_tag;
    }

    public function getArgs(): array
    {
        return $this->_args;
    }

    public function hasTag(string $tag): bool
    {
        return $this->_tag === $tag;
    }
}
