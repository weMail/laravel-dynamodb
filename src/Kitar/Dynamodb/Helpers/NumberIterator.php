<?php

namespace Kitar\Dynamodb\Helpers;

class NumberIterator implements \Iterator
{
    private $start = 0;
    private $current = 0;
    private $prefix = '';

    public function __construct($start = 1, $prefix = '')
    {
        $this->start = $this->current = $start;
        $this->prefix = $prefix;
    }

    public function rewind(): void
    {
        $this->current = $this->start;
    }

    public function current(): string
    {
        return "{$this->prefix}{$this->current}";
    }

    public function key(): mixed
    {
        return $this->current;
    }

    public function next(): void
    {
        $this->current++;
    }

    public function valid(): bool
    {
        return true;
    }
}
