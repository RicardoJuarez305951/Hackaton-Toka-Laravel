<?php

namespace Tests\Unit;

use App\Services\PlinkoPhysicsService;
use Tests\TestCase;

class PlinkoPhysicsServiceTest extends TestCase
{
    private const ASSUMED_BET_TOKA = 10;
    private const DEFAULT_DISTRIBUTION_RUNS = 5000;
    private const DISTRIBUTION_RUNS_ENV = 'PLINKO_DISTRIBUTION_RUNS';

    public function test_simulation_is_deterministic_for_same_seed(): void
    {
        $service = app(PlinkoPhysicsService::class);

        $first = $service->simulate(123456);
        $second = $service->simulate(123456);

        $this->assertSame($first['slot_index'], $second['slot_index']);
        $this->assertSame($first['spline']['positions'], $second['spline']['positions']);
        $this->assertSame($first['spline']['keyframes'], $second['spline']['keyframes']);
    }

    public function test_simulation_returns_valid_keyframes_and_slot(): void
    {
        $service = app(PlinkoPhysicsService::class);
        $result = $service->simulate(20260408);

        $this->assertArrayHasKey('slot_index', $result);
        $this->assertArrayHasKey('spline', $result);
        $this->assertIsArray($result['spline']['positions']);
        $this->assertIsArray($result['spline']['keyframes']);
        $this->assertNotEmpty($result['spline']['keyframes']);

        $this->assertTrue($this->hasTopPegHit($result['spline']['keyframes']));

        $slotIndex = $result['slot_index'];
        $this->assertGreaterThanOrEqual(0, $slotIndex);
        $this->assertLessThanOrEqual(6, $slotIndex);

        $lastFrame = end($result['spline']['keyframes']);
        $this->assertSame('slot', $lastFrame['event']);
        $this->assertSame($slotIndex, $lastFrame['slot']);
        $this->assertSame($slotIndex, $result['spline']['final_slot']);
        $this->assertCount(7, $result['spline']['positions']);
    }

    public function test_peg_branching_is_roughly_balanced_across_contacts(): void
    {
        $service = app(PlinkoPhysicsService::class);

        $left = 0;
        $right = 0;

        for ($seed = 20261000; $seed < 20261120; $seed++) {
            $result = $service->simulate($seed);
            $keyframes = $result['spline']['keyframes'];

            $this->assertTrue($this->hasTopPegHit($keyframes));

            foreach ($this->inferHitBranchDirections($keyframes) as $direction) {
                if ($direction < 0) {
                    $left++;
                } else {
                    $right++;
                }
            }
        }

        $total = $left + $right;
        $this->assertGreaterThan(100, $total);
        $this->assertGreaterThan(0, $left);
        $this->assertGreaterThan(0, $right);

        $leftRatio = $left / $total;
        $this->assertGreaterThanOrEqual(0.30, $leftRatio);
        $this->assertLessThanOrEqual(0.70, $leftRatio);
    }

    public function test_reports_1000_random_runs_distribution_by_slot(): void
    {
        $service = app(PlinkoPhysicsService::class);
        $slotMultipliers = [5, 2, 1, 0.5, 1, 2, 5];
        $slotCounts = array_fill(0, count($slotMultipliers), 0);
        $runs = $this->resolveDistributionRuns();

        for ($i = 0; $i < $runs; $i++) {
            $result = $service->simulate();
            $slotIndex = (int) ($result['slot_index'] ?? -1);

            $this->assertGreaterThanOrEqual(0, $slotIndex, "Invalid slot index at run {$i}");
            $this->assertLessThanOrEqual(6, $slotIndex, "Invalid slot index at run {$i}");

            $slotCounts[$slotIndex]++;
        }

        $this->assertCount(7, $slotCounts);
        $this->assertSame($runs, array_sum($slotCounts));

        $requireAllSlotsHit = $runs >= self::DEFAULT_DISTRIBUTION_RUNS;
        $totalBetCoins = 0.0;
        $totalRewardCoins = 0.0;
        $reportLines = [
            sprintf('Plinko distribution report (%d random runs):', $runs),
            sprintf('Assumed bet per run: %d Toka Coins', self::ASSUMED_BET_TOKA),
        ];
        for ($slot = 0; $slot < count($slotCounts); $slot++) {
            $multiplier = $slotMultipliers[$slot];
            $count = $slotCounts[$slot];
            $betCoins = $count * self::ASSUMED_BET_TOKA;
            $rewardCoins = $count * $multiplier * self::ASSUMED_BET_TOKA;
            $netCoins = $rewardCoins - $betCoins;

            if ($requireAllSlotsHit) {
                $this->assertGreaterThan(0, $count, "Slot {$slot} (x{$multiplier}) was never hit");
            }
            $totalBetCoins += $betCoins;
            $totalRewardCoins += $rewardCoins;
            $reportLines[] = sprintf(
                'slot %d (x%s): %d | apuesta=%s | recompensa=%s | neto=%s',
                $slot,
                $multiplier,
                $count,
                number_format($betCoins, 2, '.', ''),
                number_format($rewardCoins, 2, '.', ''),
                number_format($netCoins, 2, '.', '')
            );
        }

        $this->assertSame((float) ($runs * self::ASSUMED_BET_TOKA), $totalBetCoins);
        $totalNetCoins = $totalRewardCoins - $totalBetCoins;

        $reportLines[] = sprintf('TOTAL apuesta: %s', number_format($totalBetCoins, 2, '.', ''));
        $reportLines[] = sprintf('TOTAL recompensa: %s', number_format($totalRewardCoins, 2, '.', ''));
        $reportLines[] = sprintf('TOTAL neto: %s', number_format($totalNetCoins, 2, '.', ''));

        fwrite(STDOUT, implode(PHP_EOL, $reportLines).PHP_EOL);
    }

    private function resolveDistributionRuns(): int
    {
        $raw = getenv(self::DISTRIBUTION_RUNS_ENV);
        if ($raw === false || $raw === '') {
            return self::DEFAULT_DISTRIBUTION_RUNS;
        }

        $runs = filter_var($raw, FILTER_VALIDATE_INT);
        if (! is_int($runs) || $runs < 1) {
            return self::DEFAULT_DISTRIBUTION_RUNS;
        }

        return $runs;
    }

    private function hasTopPegHit(array $keyframes): bool
    {
        return collect($keyframes)->contains(
            fn (array $frame) => ($frame['event'] ?? null) === 'hit'
                && ($frame['row'] ?? null) === 0
                && ($frame['col'] ?? null) === 0
        );
    }

    private function inferHitBranchDirections(array $keyframes): array
    {
        $directions = [];

        for ($i = 0; $i < count($keyframes); $i++) {
            $frame = $keyframes[$i];

            if (($frame['event'] ?? null) !== 'hit') {
                continue;
            }

            if (! isset($frame['x'], $frame['t_ms'])) {
                continue;
            }

            $hitX = (float) $frame['x'];
            $hitTime = (int) $frame['t_ms'];

            for ($j = $i + 1; $j < count($keyframes); $j++) {
                $next = $keyframes[$j];

                if (! isset($next['x'], $next['t_ms'])) {
                    continue;
                }

                if ((int) $next['t_ms'] <= $hitTime) {
                    continue;
                }

                $delta = ((float) $next['x']) - $hitX;
                if (abs($delta) < 0.35) {
                    continue;
                }

                $directions[] = $delta > 0 ? 1 : -1;
                break;
            }
        }

        return $directions;
    }
}
