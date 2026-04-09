<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoldenTreeSimulationCommandTest extends TestCase
{
    public function test_goldentree_simulate_command_prints_scenarios_and_validation(): void
    {
        $this->artisan('goldentree:simulate', ['--hours' => [24], '--profile' => 'casual'])
            ->expectsOutput('GoldenTree Economic Scenarios')
            ->expectsOutput('GoldenTree Validation')
            ->assertExitCode(0);
    }
}
