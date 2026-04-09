<?php

namespace Tests\Feature;

use Tests\TestCase;

class RascaSimulationCommandTest extends TestCase
{
    public function test_rasca_simulate_command_prints_level_report_and_summary(): void
    {
        $this->artisan('rasca:simulate', ['--plays' => 3, '--bet' => 10])
            ->expectsOutput('Rasca Level Report')
            ->expectsOutput('Rasca Summary')
            ->assertExitCode(0);
    }
}
