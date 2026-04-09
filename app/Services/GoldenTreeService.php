<?php

namespace App\Services;

use App\Models\GoldenTreeState;
use App\Models\User;
use Illuminate\Support\Carbon;

class GoldenTreeService
{
    private const STAGES = [
        ['name' => 'Semilla', 'rate' => 1, 'threshold' => 0],
        ['name' => 'Brote', 'rate' => 1, 'threshold' => 300],
        ['name' => 'Plantula', 'rate' => 1, 'threshold' => 900],
        ['name' => 'Arbusto', 'rate' => 2, 'threshold' => 1800],
        ['name' => 'Arbol Joven', 'rate' => 2, 'threshold' => 3600],
        ['name' => 'Arbol Fuerte', 'rate' => 3, 'threshold' => 7200],
        ['name' => 'Arbol Grande', 'rate' => 4, 'threshold' => 14400],
        ['name' => 'Arbol Frondoso', 'rate' => 5, 'threshold' => 28800],
        ['name' => 'Arbol Dorado', 'rate' => 6, 'threshold' => 57600],
        ['name' => 'Arbol Mistico', 'rate' => 7, 'threshold' => 115200],
    ];

    private const EVENTS = [
        'drought' => ['name' => 'Sequia', 'action' => 'water'],
        'plague' => ['name' => 'Plaga', 'action' => 'spray'],
        'storm' => ['name' => 'Tormenta', 'action' => 'protect'],
        'leaves' => ['name' => 'Hojas Secas', 'action' => 'prune'],
    ];

    private const EVENT_INTERVAL_SECONDS = 14400;
    private const GENERATION_INTERVAL_SECONDS = 30;
    private const EVENT_TIMEOUT_SECONDS = 60;
    private const LEAVES_PENALTY_SECONDS = 300;
    private const WATER_COST = 20;
    private const WATER_BONUS_SECONDS = 600;
    private const COMMISSION_RATE = 0.15;

    public function __construct(private readonly SecureRandomService $random)
    {
    }

    public function getOrCreateState(User $user): GoldenTreeState
    {
        $now = Carbon::now();

        return GoldenTreeState::firstOrCreate(
            ['user_id' => $user->id],
            [
                'stage' => 0,
                'growth_seconds' => 0,
                'banked_tp' => 0,
                'total_generated' => 0,
                'events_attended' => 0,
                'last_processed_at' => $now,
                'last_event_check_at' => $now,
            ]
        );
    }

    public function syncState(GoldenTreeState $state): GoldenTreeState
    {
        $now = Carbon::now();

        if (! $state->last_processed_at) {
            $state->last_processed_at = $now->copy();
        }

        if (! $state->last_event_check_at) {
            $state->last_event_check_at = $state->last_processed_at->copy();
        }

        while ($state->last_processed_at->lt($now)) {
            if ($state->active_event_id) {
                $expiry = $state->active_event_expires_at?->copy() ?? $state->last_processed_at->copy();

                if ($expiry->gt($now)) {
                    $state->last_processed_at = $now->copy();
                    break;
                }

                $state->last_processed_at = $expiry;
                $this->applyEventPenalty($state, $state->active_event_id);
                $state->active_event_id = null;
                $state->active_event_expires_at = null;
                $state->last_event_check_at = $expiry->copy();
                continue;
            }

            if ($state->extra_pause_until && $state->extra_pause_until->gt($state->last_processed_at)) {
                $pauseEnd = $state->extra_pause_until->copy();

                if ($pauseEnd->gte($now)) {
                    $state->last_processed_at = $now->copy();
                    break;
                }

                $state->last_processed_at = $pauseEnd;
                $state->extra_pause_until = null;
                continue;
            }

            if ($state->extra_pause_until && $state->extra_pause_until->lte($state->last_processed_at)) {
                $state->extra_pause_until = null;
            }

            $nextEventCheckAt = $state->last_event_check_at->copy()->addSeconds(self::EVENT_INTERVAL_SECONDS);
            $segmentEnd = $nextEventCheckAt->lt($now) ? $nextEventCheckAt : $now->copy();
            $segmentSeconds = $state->last_processed_at->diffInSeconds($segmentEnd);

            if ($segmentSeconds > 0) {
                $this->applyGrowth($state, $segmentSeconds);
                $state->last_processed_at = $segmentEnd;
            }

            if ($state->last_processed_at->greaterThanOrEqualTo($nextEventCheckAt)) {
                $state->last_event_check_at = $nextEventCheckAt->copy();
                $this->maybeTriggerEvent($state, $nextEventCheckAt);
            }
        }

        $state->stage = $this->stageForSeconds($state->growth_seconds);
        $state->save();

        return $state->fresh();
    }

    public function water(GoldenTreeState $state): GoldenTreeState
    {
        $state->growth_seconds += self::WATER_BONUS_SECONDS;
        $state->stage = $this->stageForSeconds($state->growth_seconds);
        $state->last_processed_at = Carbon::now();
        $state->save();

        return $state->fresh();
    }

    public function resolveEvent(GoldenTreeState $state): GoldenTreeState
    {
        $state->active_event_id = null;
        $state->active_event_expires_at = null;
        $state->events_attended += 1;
        $state->last_processed_at = Carbon::now();
        $state->last_event_check_at = Carbon::now();
        $state->save();

        return $state->fresh();
    }

    public function collect(GoldenTreeState $state): array
    {
        $gross = (int) $state->banked_tp;
        $commission = (int) floor($gross * self::COMMISSION_RATE);
        $net = max(0, $gross - $commission);

        $state->banked_tp = 0;
        $state->last_processed_at = Carbon::now();
        $state->save();

        return [
            'gross' => $gross,
            'commission' => $commission,
            'net' => $net,
            'state' => $state->fresh(),
        ];
    }

    public function buildVisualData(GoldenTreeState $state): array
    {
        $stageIndex = $this->stageForSeconds($state->growth_seconds);
        $stageConfig = self::STAGES[$stageIndex];
        $isMaxStage = $stageIndex >= (count(self::STAGES) - 1);
        $nextStage = $isMaxStage ? null : self::STAGES[$stageIndex + 1];
        $currentThreshold = $stageConfig['threshold'];
        $nextThreshold = $nextStage['threshold'] ?? $currentThreshold;
        $range = max(1, $nextThreshold - $currentThreshold);
        $progressWithinStage = $isMaxStage ? 100 : min(100, (int) floor((($state->growth_seconds - $currentThreshold) / $range) * 100));
        $gross = (int) $state->banked_tp;
        $commission = (int) floor($gross * self::COMMISSION_RATE);
        $secondsToNext = $isMaxStage ? 0 : max(0, $nextThreshold - $state->growth_seconds);

        return [
            'stage' => $stageIndex,
            'stage_name' => $stageConfig['name'],
            'stage_rate' => $stageConfig['rate'],
            'growth_seconds' => (int) $state->growth_seconds,
            'banked_tp' => $gross,
            'total_generated' => (int) $state->total_generated,
            'events_attended' => (int) $state->events_attended,
            'progress_percent' => $progressWithinStage,
            'next_stage_name' => $nextStage['name'] ?? 'MAX',
            'seconds_to_next_stage' => $secondsToNext,
            'is_max_stage' => $isMaxStage,
            'commission_tp' => $commission,
            'net_tp' => max(0, $gross - $commission),
            'water_cost' => self::WATER_COST,
            'generating' => ! $state->active_event_id && ! ($state->extra_pause_until && $state->extra_pause_until->isFuture()),
            'active_event' => $this->serializeActiveEvent($state),
            'extra_pause_seconds' => $state->extra_pause_until && $state->extra_pause_until->isFuture()
                ? Carbon::now()->diffInSeconds($state->extra_pause_until)
                : 0,
        ];
    }

    public function getWaterCost(): int
    {
        return self::WATER_COST;
    }

    public function isValidEventId(?string $eventId): bool
    {
        return $eventId !== null && array_key_exists($eventId, self::EVENTS);
    }

    private function applyGrowth(GoldenTreeState $state, int $seconds): void
    {
        while ($seconds > 0) {
            $stageIndex = $this->stageForSeconds($state->growth_seconds);
            $stageConfig = self::STAGES[$stageIndex];
            $nextThreshold = self::STAGES[$stageIndex + 1]['threshold'] ?? null;

            if ($nextThreshold === null) {
                $chunk = $seconds;
            } else {
                $remainingToNextStage = max(1, $nextThreshold - $state->growth_seconds);
                $chunk = min($seconds, $remainingToNextStage);
            }

            $cyclesBefore = intdiv((int) $state->growth_seconds, self::GENERATION_INTERVAL_SECONDS);
            $cyclesAfter = intdiv((int) $state->growth_seconds + $chunk, self::GENERATION_INTERVAL_SECONDS);
            $generated = ($cyclesAfter - $cyclesBefore) * $stageConfig['rate'];

            $state->growth_seconds += $chunk;
            $state->banked_tp += $generated;
            $state->total_generated += $generated;
            $state->stage = $this->stageForSeconds($state->growth_seconds);
            $seconds -= $chunk;
        }
    }

    private function maybeTriggerEvent(GoldenTreeState $state, Carbon $at): void
    {
        if ($this->random->int(1, 100) > 30) {
            return;
        }

        $eventIds = array_keys(self::EVENTS);
        $eventId = $eventIds[$this->random->pickIndex($eventIds)];
        $state->active_event_id = $eventId;
        $state->active_event_expires_at = $at->copy()->addSeconds(self::EVENT_TIMEOUT_SECONDS);
    }

    private function applyEventPenalty(GoldenTreeState $state, string $eventId): void
    {
        switch ($eventId) {
            case 'drought':
                $state->banked_tp = (int) floor($state->banked_tp * 0.8);
                break;
            case 'plague':
                if ($state->stage > 0) {
                    $newStage = $state->stage - 1;
                    $state->growth_seconds = self::STAGES[$newStage]['threshold'];
                    $state->stage = $newStage;
                }
                break;
            case 'storm':
                $state->banked_tp = 0;
                break;
            case 'leaves':
                $state->extra_pause_until = $state->last_processed_at->copy()->addSeconds(self::LEAVES_PENALTY_SECONDS);
                break;
        }
    }

    private function serializeActiveEvent(GoldenTreeState $state): ?array
    {
        if (! $state->active_event_id || ! isset(self::EVENTS[$state->active_event_id])) {
            return null;
        }

        $config = self::EVENTS[$state->active_event_id];

        return [
            'id' => $state->active_event_id,
            'name' => $config['name'],
            'required_action' => $config['action'],
            'expires_at' => $state->active_event_expires_at?->toIso8601String(),
            'remaining_seconds' => $state->active_event_expires_at && $state->active_event_expires_at->isFuture()
                ? Carbon::now()->diffInSeconds($state->active_event_expires_at)
                : 0,
        ];
    }

    private function stageForSeconds(int $seconds): int
    {
        for ($index = count(self::STAGES) - 1; $index >= 0; $index--) {
            if ($seconds >= self::STAGES[$index]['threshold']) {
                return $index;
            }
        }

        return 0;
    }
}
