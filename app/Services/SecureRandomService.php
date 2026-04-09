<?php

namespace App\Services;

class SecureRandomService
{
    public function int(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    public function coinFlip(): int
    {
        return $this->int(0, 1);
    }

    public function pickIndex(array $items): int
    {
        if ($items === []) {
            throw new \InvalidArgumentException('Cannot pick an index from an empty array.');
        }

        return $this->int(0, count($items) - 1);
    }
}
