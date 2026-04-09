<?php

namespace App\Services;

class RuletaSimulationService
{
    public const RAW_PAYOUTS = [0, 2, 5, 8, 10, 12, 15, 24];

    public const SEGMENT_COLORS = ['#131313', '#51aa5f', '#80c2dc', '#3388c6', '#dd6860', '#e37d6b', '#22345a', '#c29706'];

    public function __construct(private readonly SecureRandomService $random)
    {
    }

    public function simulate(?int $winIndex = null): array
    {
        if ($winIndex !== null) {
            $this->assertValidWinIndex($winIndex);
        }

        $winIndex ??= $this->random->int(0, count(self::RAW_PAYOUTS) - 1);
        $rawPayout = self::RAW_PAYOUTS[$winIndex];

        return [
            'win_index' => $winIndex,
            'raw_payout' => $rawPayout,
            'multiplier' => (float) ($rawPayout / 10),
            'result_color' => self::SEGMENT_COLORS[$winIndex],
        ];
    }

    private function assertValidWinIndex(int $winIndex): void
    {
        if ($winIndex < 0 || $winIndex >= count(self::RAW_PAYOUTS)) {
            throw new \InvalidArgumentException('Ruleta win index is out of range.');
        }
    }
}
