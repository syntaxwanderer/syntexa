<?php

namespace Syntexa\Inspector\Storage;

class CircularBuffer
{
    private array $buffer = [];
    private int $capacity;
    private int $head = 0;

    public function __construct(int $capacity = 50)
    {
        $this->capacity = $capacity;
    }

    public function add(array $entry): void
    {
        $this->buffer[$this->head] = $entry;
        $this->head = ($this->head + 1) % $this->capacity;
    }

    /**
     * Returns entries in FIFO order (oldest to newest)
     */
    public function getAll(): array
    {
        $result = [];
        $count = count($this->buffer);
        
        if ($count < $this->capacity) {
            // Buffer not full yet
            return $this->buffer;
        }

        // Buffer is full, reconstruct from head (oldest is at head)
        for ($i = 0; $i < $this->capacity; $i++) {
            $index = ($this->head + $i) % $this->capacity;
            $result[] = $this->buffer[$index];
        }

        return $result;
    }
}
