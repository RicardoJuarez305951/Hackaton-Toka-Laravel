<?php

namespace App\Services;

class RascaSimulationService
{
    public const LEVELS = [
        ['probability' => 100, 'raw_payout' => 5, 'name' => 'common'],
        ['probability' => 66, 'raw_payout' => 10, 'name' => 'uncommon'],
        ['probability' => 16, 'raw_payout' => 20, 'name' => 'rare'],
        ['probability' => 4, 'raw_payout' => 50, 'name' => 'epic'],
        ['probability' => 1, 'raw_payout' => 200, 'name' => 'legendary'],
    ];

    public function __construct(private readonly SecureRandomService $random)
    {
    }

    public function simulate(?array $rolls = null): array
    {
        if ($rolls !== null) {
            $this->assertValidRolls($rolls);
        }

        $steps = [];
        $finalLevel = 0;
        $rawPayout = 0;

        foreach (self::LEVELS as $index => $level) {
            $roll = $rolls[$index] ?? $this->random->int(1, 100);
            $passed = $roll <= $level['probability'];

            $steps[] = [
                'level' => $index + 1,
                'passed' => $passed,
                'raw_payout' => $level['raw_payout'],
                'probability' => $level['probability'],
                'roll' => $roll,
                'name' => $level['name'],
            ];

            if (! $passed) {
                break;
            }

            $finalLevel = $index + 1;
            $rawPayout = $level['raw_payout'];
        }

        return [
            'steps' => $steps,
            'final_level' => $finalLevel,
            'raw_payout' => $rawPayout,
            'multiplier' => (float) ($rawPayout / 10),
            'prize_map' => $this->prizeMap(),
        ];
    }

    public function prizeMap(): array
    {
        return array_map(static fn (array $level) => $level['raw_payout'], self::LEVELS);
    }

    private function assertValidRolls(array $rolls): void
    {
        if (count($rolls) !== count(self::LEVELS)) {
            throw new \InvalidArgumentException('Rasca rolls must have exactly 5 values.');
        }

        foreach ($rolls as $roll) {
            if (! is_int($roll) || $roll < 1 || $roll > 100) {
                throw new \InvalidArgumentException('Rasca rolls must be integers between 1 and 100.');
            }
        }
    }
}
