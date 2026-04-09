<?php

namespace Tests\Feature;

use Tests\TestCase;

class RuletaSimulationCommandTest extends TestCase
{
    public function test_ruleta_simulate_command_prints_segment_report_and_summary(): void
    {
        $this->artisan('ruleta:simulate', ['--plays' => 3, '--bet' => 10])
            ->expectsOutput('Ruleta Segment Report')
            ->expectsOutput('Ruleta Summary')
            ->assertExitCode(0);
    }
}
