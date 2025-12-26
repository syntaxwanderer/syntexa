<?php

namespace Syntexa\Inspector\Storage;

use Swoole\Table;

class SharedCircularBuffer
{
    private Table $table;
    private Table $meta;
    private int $capacity;

    public function __construct(int $capacity = 50)
    {
        $this->capacity = $capacity;
        
        // Table for history entries
        // Each row is a JSON string of the entry
        $this->table = new Table($capacity);
        $this->table->column('data', Table::TYPE_STRING, 8192); // Adjust size as needed
        $this->table->create();

        // Table for metadata (head index)
        $this->meta = new Table(1);
        $this->meta->column('head', Table::TYPE_INT);
        $this->meta->create();
        $this->meta->set('current', ['head' => 0]);
    }

    public function add(array $entry): void
    {
        // Atomic increment of head
        $head = $this->meta->incr('current', 'head');
        // incr returns the NEW value. We want the one BEFORE increment for current slot.
        // Actually, let's just use the returned value and wrap it.
        $index = ($head - 1) % $this->capacity;
        
        $this->table->set((string)$index, [
            'data' => json_encode($entry)
        ]);
        
        // Wrap counter if it gets too high (Atomic increment can go up to 64bit int)
        if ($head > 1000000) {
             $this->meta->set('current', ['head' => $head % $this->capacity]);
        }
    }

    public function getAll(): array
    {
        $head = $this->meta->get('current', 'head') % $this->capacity;
        $result = [];
        
        $count = $this->table->count();
        if ($count === 0) return [];
        
        if ($count < $this->capacity) {
            // Buffer not full yet
            for ($i = 0; $i < $head; $i++) {
                $item = $this->table->get((string)$i);
                if ($item) {
                    $result[] = json_decode($item['data'], true);
                }
            }
            return $result;
        }

        // Buffer is full
        for ($i = 0; $i < $this->capacity; $i++) {
            $index = ($head + $i) % $this->capacity;
            $item = $this->table->get((string)$index);
            if ($item) {
                $result[] = json_decode($item['data'], true);
            }
        }

        return $result;
    }
}
