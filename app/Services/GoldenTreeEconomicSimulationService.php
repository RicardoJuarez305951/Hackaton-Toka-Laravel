<?php

namespace App\Services;

class GoldenTreeEconomicSimulationService
{
    public const INITIAL_WALLET = 1000;

    public const PROFILES = [
        'casual' => [
            'collect_interval_hours' => 12,
            'water_interval_hours' => null,
            'resolve_chance_percent' => 50,
            'resolve_delay_seconds' => 30,
        ],
        'active' => [
            'collect_interval_hours' => 6,
            'water_interval_hours' => 12,
            'resolve_chance_percent' => 80,
            'resolve_delay_seconds' => 20,
        ],
        'optimal' => [
            'collect_interval_hours' => 4,
            'water_interval_hours' => 6,
            'resolve_chance_percent' => 100,
            'resolve_delay_seconds' => 0,
        ],
    ];

    public function __construct(private readonly SecureRandomService $random)
    {
    }

    public function simulateProfile(string $profile, int $hours, ?array $configOverrides = null): array
    {
        $this->assertValidInputs($profile, $hours);

        $config = array_replace(GoldenTreeService::economyConfig(), $configOverrides ?? []);
        $profileConfig = self::PROFILES[$profile];
        $horizonSeconds = $hours * 3600;
        $wallet = self::INITIAL_WALLET;
        $state = $this->initialState();
        $metrics = $this->initialMetrics();

        $nextCollectAt = $profileConfig['collect_interval_hours'] !== null ? $profileConfig['collect_interval_hours'] * 3600 : null;
        $nextWaterAt = $profileConfig['water_interval_hours'] !== null ? $profileConfig['water_interval_hours'] * 3600 : null;
        $pendingResolutionAt = null;
        $pendingResolutionEventId = null;
        $currentTime = 0;

        while ($currentTime < $horizonSeconds) {
            $nextTime = $horizonSeconds;
            $nextEventCheckAt = $state['active_event_id'] === null
                ? $state['last_event_check_at'] + $config['event_interval_seconds']
                : null;

            foreach ([$nextCollectAt, $nextWaterAt, $pendingResolutionAt, $nextEventCheckAt] as $candidate) {
                if ($candidate !== null && $candidate < $nextTime) {
                    $nextTime = $candidate;
                }
            }

            $this->syncStateToTime($state, $nextTime, $config, $metrics);
            $currentTime = $nextTime;

            if ($state['active_event_id'] !== null && $pendingResolutionAt === null) {
                [$pendingResolutionAt, $pendingResolutionEventId] = $this->scheduleResolution($state, $currentTime, $profileConfig);
            }

            $handledAction = true;

            while ($handledAction) {
                $handledAction = false;

                if (
                    $pendingResolutionAt !== null &&
                    $pendingResolutionAt <= $currentTime &&
                    $state['active_event_id'] !== null &&
                    $state['active_event_id'] === $pendingResolutionEventId
                ) {
                    $this->resolveEvent($state, $currentTime);
                    $pendingResolutionAt = null;
                    $pendingResolutionEventId = null;
                    $handledAction = true;
                }

                if ($nextCollectAt !== null && $nextCollectAt <= $currentTime) {
                    $collection = $this->collect($state, $currentTime, $config);
                    $metrics['collected_gross'] += $collection['gross'];
                    $metrics['commission_retained'] += $collection['commission'];
                    $metrics['collected_net'] += $collection['net'];
                    $wallet += $collection['net'];
                    $nextCollectAt += $profileConfig['collect_interval_hours'] * 3600;
                    $handledAction = true;
                }

                if ($nextWaterAt !== null && $nextWaterAt <= $currentTime) {
                    if ($state['active_event_id'] === null && $wallet >= $config['water_cost']) {
                        $wallet -= $config['water_cost'];
                        $metrics['water_spent'] += $config['water_cost'];
                        $this->water($state, $currentTime, $config);
                    }

                    $nextWaterAt += $profileConfig['water_interval_hours'] * 3600;
                    $handledAction = true;
                }

                if ($state['active_event_id'] !== null && $pendingResolutionAt === null) {
                    [$pendingResolutionAt, $pendingResolutionEventId] = $this->scheduleResolution($state, $currentTime, $profileConfig);
                }

                if ($state['active_event_id'] === null) {
                    $pendingResolutionAt = null;
                    $pendingResolutionEventId = null;
                }
            }
        }

        $this->syncStateToTime($state, $horizonSeconds, $config, $metrics);

        $metrics['generated_gross'] = (int) $state['total_generated'];
        $metrics['ending_banked_tp'] = (int) $state['banked_tp'];
        $metrics['wallet_delta'] = $wallet - self::INITIAL_WALLET;
        $metrics['system_retained'] = $metrics['commission_retained'] + $metrics['water_spent'] + $metrics['penalty_value_lost'] + $metrics['ending_banked_tp'];
        $metrics['events_attended'] = (int) $state['events_attended'];

        return [
            'profile' => $profile,
            'hours' => $hours,
            'initial_wallet' => self::INITIAL_WALLET,
            'wallet_end' => $wallet,
            'generated_gross' => $metrics['generated_gross'],
            'collected_gross' => $metrics['collected_gross'],
            'commission_retained' => $metrics['commission_retained'],
            'water_spent' => $metrics['water_spent'],
            'collected_net' => $metrics['collected_net'],
            'wallet_delta' => $metrics['wallet_delta'],
            'events_attended' => $metrics['events_attended'],
            'missed_events' => $metrics['missed_events'],
            'penalties' => $metrics['penalties'],
            'system_retained' => $metrics['system_retained'],
            'ending_banked_tp' => $metrics['ending_banked_tp'],
            'target_met' => ! in_array($profile, ['active', 'optimal'], true) || $metrics['wallet_delta'] <= 0,
        ];
    }

    private function initialState(): array
    {
        return [
            'stage' => 0,
            'growth_seconds' => 0,
            'banked_tp' => 0,
            'total_generated' => 0,
            'events_attended' => 0,
            'last_processed_at' => 0,
            'last_event_check_at' => 0,
            'active_event_id' => null,
            'active_event_expires_at' => null,
            'extra_pause_until' => null,
        ];
    }

    private function initialMetrics(): array
    {
        return [
            'generated_gross' => 0,
            'collected_gross' => 0,
            'commission_retained' => 0,
            'water_spent' => 0,
            'collected_net' => 0,
            'wallet_delta' => 0,
            'events_attended' => 0,
            'missed_events' => 0,
            'penalty_value_lost' => 0,
            'ending_banked_tp' => 0,
            'penalties' => [
                'drought' => ['count' => 0, 'tp_lost' => 0],
                'plague' => ['count' => 0, 'growth_seconds_lost' => 0],
                'storm' => ['count' => 0, 'tp_lost' => 0],
                'leaves' => ['count' => 0, 'pause_seconds' => 0],
            ],
        ];
    }

    private function syncStateToTime(array &$state, int $now, array $config, array &$metrics): void
    {
        while ($state['last_processed_at'] < $now) {
            if ($state['active_event_id'] !== null) {
                $expiry = $state['active_event_expires_at'] ?? $state['last_processed_at'];

                if ($expiry > $now) {
                    $state['last_processed_at'] = $now;

                    break;
                }

                $state['last_processed_at'] = $expiry;
                $this->applyEventPenalty($state, $state['active_event_id'], $config, $metrics);
                $state['active_event_id'] = null;
                $state['active_event_expires_at'] = null;
                $state['last_event_check_at'] = $expiry;
                $metrics['missed_events']++;
                continue;
            }

            if ($state['extra_pause_until'] !== null && $state['extra_pause_until'] > $state['last_processed_at']) {
                if ($state['extra_pause_until'] >= $now) {
                    $state['last_processed_at'] = $now;

                    break;
                }

                $state['last_processed_at'] = $state['extra_pause_until'];
                $state['extra_pause_until'] = null;
                continue;
            }

            if ($state['extra_pause_until'] !== null && $state['extra_pause_until'] <= $state['last_processed_at']) {
                $state['extra_pause_until'] = null;
            }

            $nextEventCheckAt = $state['last_event_check_at'] + $config['event_interval_seconds'];
            $segmentEnd = min($nextEventCheckAt, $now);
            $segmentSeconds = $segmentEnd - $state['last_processed_at'];

            if ($segmentSeconds > 0) {
                $this->applyGrowth($state, $segmentSeconds, $config);
                $state['last_processed_at'] = $segmentEnd;
            }

            if ($state['last_processed_at'] >= $nextEventCheckAt) {
                $state['last_event_check_at'] = $nextEventCheckAt;
                $this->maybeTriggerEvent($state, $nextEventCheckAt, $config);
            }
        }

        $state['stage'] = $this->stageForSeconds($state['growth_seconds'], $config);
    }

    private function applyGrowth(array &$state, int $seconds, array $config): void
    {
        while ($seconds > 0) {
            $stageIndex = $this->stageForSeconds($state['growth_seconds'], $config);
            $stageConfig = $config['stages'][$stageIndex];
            $nextThreshold = $config['stages'][$stageIndex + 1]['threshold'] ?? null;
            $chunk = $nextThreshold === null ? $seconds : min($seconds, max(1, $nextThreshold - $state['growth_seconds']));

            $cyclesBefore = intdiv((int) $state['growth_seconds'], $config['generation_interval_seconds']);
            $cyclesAfter = intdiv((int) $state['growth_seconds'] + $chunk, $config['generation_interval_seconds']);
            $generated = ($cyclesAfter - $cyclesBefore) * $stageConfig['rate'];

            $state['growth_seconds'] += $chunk;
            $state['banked_tp'] += $generated;
            $state['total_generated'] += $generated;
            $seconds -= $chunk;
        }
    }

    private function maybeTriggerEvent(array &$state, int $at, array $config): void
    {
        if ($this->random->int(1, 100) > $config['event_trigger_chance_percent']) {
            return;
        }

        $eventIds = array_keys($config['events']);
        $eventId = $eventIds[$this->random->pickIndex($eventIds)];
        $state['active_event_id'] = $eventId;
        $state['active_event_expires_at'] = $at + $config['event_timeout_seconds'];
    }

    private function applyEventPenalty(array &$state, string $eventId, array $config, array &$metrics): void
    {
        switch ($eventId) {
            case 'drought':
                $before = (int) $state['banked_tp'];
                $state['banked_tp'] = (int) floor($state['banked_tp'] * $config['drought_bank_retain_rate']);
                $lost = $before - (int) $state['banked_tp'];
                $metrics['penalties']['drought']['count']++;
                $metrics['penalties']['drought']['tp_lost'] += $lost;
                $metrics['penalty_value_lost'] += $lost;
                break;
            case 'plague':
                if ($state['stage'] > 0) {
                    $previousGrowth = $state['growth_seconds'];
                    $newStage = $state['stage'] - 1;
                    $state['growth_seconds'] = $config['stages'][$newStage]['threshold'];
                    $state['stage'] = $newStage;
                    $metrics['penalties']['plague']['growth_seconds_lost'] += max(0, $previousGrowth - $state['growth_seconds']);
                }
                $metrics['penalties']['plague']['count']++;
                break;
            case 'storm':
                $lost = (int) $state['banked_tp'];
                $state['banked_tp'] = 0;
                $metrics['penalties']['storm']['count']++;
                $metrics['penalties']['storm']['tp_lost'] += $lost;
                $metrics['penalty_value_lost'] += $lost;
                break;
            case 'leaves':
                $state['extra_pause_until'] = $state['last_processed_at'] + $config['leaves_penalty_seconds'];
                $metrics['penalties']['leaves']['count']++;
                $metrics['penalties']['leaves']['pause_seconds'] += $config['leaves_penalty_seconds'];
                break;
        }
    }

    private function collect(array &$state, int $now, array $config): array
    {
        $gross = (int) $state['banked_tp'];
        $commission = (int) floor($gross * $config['commission_rate']);
        $net = max(0, $gross - $commission);

        $state['banked_tp'] = 0;
        $state['last_processed_at'] = $now;

        return [
            'gross' => $gross,
            'commission' => $commission,
            'net' => $net,
        ];
    }

    private function water(array &$state, int $now, array $config): void
    {
        $state['growth_seconds'] += $config['water_bonus_seconds'];
        $state['stage'] = $this->stageForSeconds($state['growth_seconds'], $config);
        $state['last_processed_at'] = $now;
    }

    private function resolveEvent(array &$state, int $now): void
    {
        $state['active_event_id'] = null;
        $state['active_event_expires_at'] = null;
        $state['events_attended']++;
        $state['last_processed_at'] = $now;
        $state['last_event_check_at'] = $now;
    }

    private function scheduleResolution(array $state, int $currentTime, array $profileConfig): array
    {
        if ($state['active_event_id'] === null) {
            return [null, null];
        }

        if ($this->random->int(1, 100) > $profileConfig['resolve_chance_percent']) {
            return [null, null];
        }

        return [
            min($state['active_event_expires_at'], $currentTime + $profileConfig['resolve_delay_seconds']),
            $state['active_event_id'],
        ];
    }

    private function stageForSeconds(int $seconds, array $config): int
    {
        for ($index = count($config['stages']) - 1; $index >= 0; $index--) {
            if ($seconds >= $config['stages'][$index]['threshold']) {
                return $index;
            }
        }

        return 0;
    }

    private function assertValidInputs(string $profile, int $hours): void
    {
        if (! array_key_exists($profile, self::PROFILES)) {
            throw new \InvalidArgumentException('Unsupported GoldenTree profile.');
        }

        if ($hours < 1) {
            throw new \InvalidArgumentException('HOURS must be at least 1.');
        }
    }
}
