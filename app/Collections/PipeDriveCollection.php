<?php

namespace App\Collections;

use Illuminate\Support\Collection;

class PipeDriveCollection extends Collection
{
    protected array $relatedObjects = [];

    public function __construct($items, $relatedObjects = [])
    {
        parent::__construct($items);
        $this->relatedObjects = $relatedObjects;
    }

    public function relatedObject(?string $object = null): ?array
    {
        if ($object){
            return $this->relatedObjects[$object] ?? null;
        }
        return $this->relatedObjects;
    }

    public function merge($items): static
    {
        return new static(
            array_merge($this->items, $this->getArrayableItems($items)),
            array_merge_recursive($this->relatedObjects, $items instanceof self ? $items->relatedObject() : [])
        );
    }
}
