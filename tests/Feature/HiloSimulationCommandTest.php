<?php

namespace Tests\Feature;

use Tests\TestCase;

class HiloSimulationCommandTest extends TestCase
{
    public function test_hilo_simulate_command_prints_policy_and_mixed_summaries(): void
    {
        $this->artisan('hilo:simulate', ['--rounds' => 3, '--bet' => 10, '--policy' => 'mixed'])
            ->expectsOutput('Hilo Policy Summary')
            ->expectsOutput('Hilo Mixed Summary')
            ->expectsOutput('Hilo Worst Case Summary')
            ->assertExitCode(0);
    }
}
