<?php

namespace App\Services;

class GoldenTreeSimulationReportService
{
    public const DEFAULT_HOURS = [24, 168];

    public function __construct(private readonly GoldenTreeEconomicSimulationService $goldenTreeEconomicSimulationService)
    {
    }

    public function simulate(array $hours = self::DEFAULT_HOURS, string $profile = 'all', ?array $configOverrides = null): array
    {
        $this->assertValidInputs($hours, $profile);

        $profiles = $profile === 'all'
            ? array_keys(GoldenTreeEconomicSimulationService::PROFILES)
            : [$profile];

        $scenarios = [];

        foreach ($profiles as $selectedProfile) {
            foreach ($hours as $selectedHours) {
                $scenarios[] = $this->goldenTreeEconomicSimulationService->simulateProfile($selectedProfile, (int) $selectedHours, $configOverrides);
            }
        }

        $failingScenarios = array_values(array_filter($scenarios, static fn (array $scenario): bool => in_array($scenario['profile'], ['active', 'optimal'], true) && ! $scenario['target_met']));

        return [
            'scenarios' => $scenarios,
            'config' => array_replace(GoldenTreeService::economyConfig(), $configOverrides ?? []),
            'validation' => [
                'target_met' => $failingScenarios === [],
                'failing_scenarios' => array_map(
                    static fn (array $scenario): array => [
                        'profile' => $scenario['profile'],
                        'hours' => $scenario['hours'],
                        'wallet_delta' => $scenario['wallet_delta'],
                    ],
                    $failingScenarios
                ),
                'requires_rate_redesign' => $failingScenarios !== [],
            ],
        ];
    }

    private function assertValidInputs(array $hours, string $profile): void
    {
        if ($hours === []) {
            throw new \InvalidArgumentException('At least one horizon must be provided.');
        }

        foreach ($hours as $value) {
            if (! is_int($value) || $value < 1) {
                throw new \InvalidArgumentException('GoldenTree horizons must be positive integers.');
            }
        }

        if ($profile !== 'all' && ! array_key_exists($profile, GoldenTreeEconomicSimulationService::PROFILES)) {
            throw new \InvalidArgumentException('Unsupported GoldenTree profile.');
        }
    }
}
