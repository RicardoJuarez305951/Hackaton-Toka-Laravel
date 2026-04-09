<?php

use App\Services\PlinkoSimulationReportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('plinko:simulate {--plays=} {--bet=}', function () {
    $defaultPlays = PlinkoSimulationReportService::DEFAULT_TOTAL_PLAYS;
    $defaultBet = PlinkoSimulationReportService::DEFAULT_TOTAL_BET;

    $plays = $this->option('plays') === null ? $defaultPlays : filter_var($this->option('plays'), FILTER_VALIDATE_INT);
    $bet = $this->option('bet') === null ? $defaultBet : filter_var($this->option('bet'), FILTER_VALIDATE_INT);

    if ($plays === false || $bet === false) {
        $this->error('The --plays and --bet options must be integers.');

        return SymfonyCommand::FAILURE;
    }

    try {
        $report = app(PlinkoSimulationReportService::class)->simulate((int) $plays, (int) $bet);
    } catch (\InvalidArgumentException $e) {
        $this->error($e->getMessage());

        return SymfonyCommand::FAILURE;
    }

    $slotRows = array_map(static function (array $slot): array {
        return [
            'slot' => $slot['slot'],
            'multiplier' => $slot['multiplier'],
            'plays' => $slot['plays'],
            'bet_total' => $slot['bet_total'],
            'prize_total' => $slot['prize_total'],
        ];
    }, $report['slots']);

    $summary = $report['summary'];

    $this->info('Plinko Slot Report');
    $this->table(['SLOT', 'MULTIPLIER', 'TOTAL_PLAYS', 'TOTAL_BET', 'TOTAL_PRIZE'], $slotRows);
    $this->newLine();
    $this->info('Plinko Summary');
    $this->table(
        ['TOTAL_PLAYS', 'TOTAL_BET', 'TOTAL_WAGERED', 'TOTAL_PRIZE', 'NET_RESULT'],
        [[
            'total_plays' => $summary['total_plays'],
            'total_bet' => $summary['total_bet'],
            'total_wagered' => $summary['total_wagered'],
            'total_prize' => $summary['total_prize'],
            'net_result' => $summary['net_result'],
        ]]
    );

    return SymfonyCommand::SUCCESS;
})->purpose('Run N Plinko simulations and print an aggregate slot report');
