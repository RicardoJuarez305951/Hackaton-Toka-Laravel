<?php

use App\Services\GoldenTreeSimulationReportService;
use App\Services\HiloSimulationReportService;
use App\Services\RascaSimulationReportService;
use App\Services\PlinkoSimulationReportService;
use App\Services\RuletaSimulationReportService;
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

Artisan::command('rasca:simulate {--plays=} {--bet=}', function () {
    $defaultPlays = RascaSimulationReportService::DEFAULT_TOTAL_PLAYS;
    $defaultBet = RascaSimulationReportService::DEFAULT_TOTAL_BET;

    $plays = $this->option('plays') === null ? $defaultPlays : filter_var($this->option('plays'), FILTER_VALIDATE_INT);
    $bet = $this->option('bet') === null ? $defaultBet : filter_var($this->option('bet'), FILTER_VALIDATE_INT);

    if ($plays === false || $bet === false) {
        $this->error('The --plays and --bet options must be integers.');

        return SymfonyCommand::FAILURE;
    }

    try {
        $report = app(RascaSimulationReportService::class)->simulate((int) $plays, (int) $bet);
    } catch (\InvalidArgumentException $e) {
        $this->error($e->getMessage());

        return SymfonyCommand::FAILURE;
    }

    $levelRows = array_map(static function (array $level): array {
        return [
            'final_level' => $level['final_level'],
            'name' => $level['name'],
            'raw_payout' => $level['raw_payout'],
            'multiplier' => $level['multiplier'],
            'plays' => $level['plays'],
            'bet_total' => $level['bet_total'],
            'prize_total' => $level['prize_total'],
        ];
    }, $report['levels']);

    $summary = $report['summary'];

    $this->info('Rasca Level Report');
    $this->table(['FINAL_LEVEL', 'NAME', 'RAW_PAYOUT', 'MULTIPLIER', 'TOTAL_PLAYS', 'TOTAL_BET', 'TOTAL_PRIZE'], $levelRows);
    $this->newLine();
    $this->info('Rasca Summary');
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
})->purpose('Run N Rasca simulations and print an aggregate level report');

Artisan::command('ruleta:simulate {--plays=} {--bet=}', function () {
    $defaultPlays = RuletaSimulationReportService::DEFAULT_TOTAL_PLAYS;
    $defaultBet = RuletaSimulationReportService::DEFAULT_TOTAL_BET;

    $plays = $this->option('plays') === null ? $defaultPlays : filter_var($this->option('plays'), FILTER_VALIDATE_INT);
    $bet = $this->option('bet') === null ? $defaultBet : filter_var($this->option('bet'), FILTER_VALIDATE_INT);

    if ($plays === false || $bet === false) {
        $this->error('The --plays and --bet options must be integers.');

        return SymfonyCommand::FAILURE;
    }

    try {
        $report = app(RuletaSimulationReportService::class)->simulate((int) $plays, (int) $bet);
    } catch (\InvalidArgumentException $e) {
        $this->error($e->getMessage());

        return SymfonyCommand::FAILURE;
    }

    $segmentRows = array_map(static function (array $segment): array {
        return [
            'win_index' => $segment['win_index'],
            'raw_payout' => $segment['raw_payout'],
            'multiplier' => $segment['multiplier'],
            'plays' => $segment['plays'],
            'bet_total' => $segment['bet_total'],
            'prize_total' => $segment['prize_total'],
        ];
    }, $report['segments']);

    $summary = $report['summary'];

    $this->info('Ruleta Segment Report');
    $this->table(['WIN_INDEX', 'RAW_PAYOUT', 'MULTIPLIER', 'TOTAL_PLAYS', 'TOTAL_BET', 'TOTAL_PRIZE'], $segmentRows);
    $this->newLine();
    $this->info('Ruleta Summary');
    $this->table(
        ['TOTAL_PLAYS', 'TOTAL_BET', 'TOTAL_WAGERED', 'TOTAL_PRIZE', 'NET_RESULT', 'RTP'],
        [[
            'total_plays' => $summary['total_plays'],
            'total_bet' => $summary['total_bet'],
            'total_wagered' => $summary['total_wagered'],
            'total_prize' => $summary['total_prize'],
            'net_result' => $summary['net_result'],
            'rtp' => $summary['rtp'],
        ]]
    );

    return SymfonyCommand::SUCCESS;
})->purpose('Run N Ruleta simulations and print an aggregate segment report');

Artisan::command('hilo:simulate {--rounds=} {--bet=} {--policy=mixed}', function () {
    $defaultRounds = HiloSimulationReportService::DEFAULT_TOTAL_ROUNDS;
    $defaultBet = HiloSimulationReportService::DEFAULT_TOTAL_BET;

    $rounds = $this->option('rounds') === null ? $defaultRounds : filter_var($this->option('rounds'), FILTER_VALIDATE_INT);
    $bet = $this->option('bet') === null ? $defaultBet : filter_var($this->option('bet'), FILTER_VALIDATE_INT);
    $policy = (string) $this->option('policy');

    if ($rounds === false || $bet === false) {
        $this->error('The --rounds and --bet options must be integers.');

        return SymfonyCommand::FAILURE;
    }

    if (! in_array($policy, ['first_win', 'two_wins', 'three_wins', 'mixed'], true)) {
        $this->error('The --policy option must be one of: first_win, two_wins, three_wins, mixed.');

        return SymfonyCommand::FAILURE;
    }

    try {
        $report = app(HiloSimulationReportService::class)->simulate((int) $rounds, (int) $bet);
    } catch (\InvalidArgumentException $e) {
        $this->error($e->getMessage());

        return SymfonyCommand::FAILURE;
    }

    $policyRows = array_map(static function (array $summary): array {
        return [
            'policy' => $summary['policy'],
            'target_wins' => $summary['target_wins'],
            'total_rounds' => $summary['total_rounds'],
            'total_prize' => $summary['total_prize'],
            'net_result' => $summary['net_result'],
            'rtp' => $summary['rtp'],
        ];
    }, $report['policies']);

    if ($policy === 'mixed') {
        $this->info('Hilo Policy Summary');
        $this->table(['POLICY', 'TARGET_WINS', 'TOTAL_ROUNDS', 'TOTAL_PRIZE', 'NET_RESULT', 'RTP'], $policyRows);
        $this->newLine();
        $this->info('Hilo Mixed Summary');
        $this->table(['POLICY', 'TOTAL_ROUNDS', 'TOTAL_PRIZE', 'NET_RESULT', 'RTP'], [[
            'policy' => $report['mixed_summary']['policy'],
            'total_rounds' => $report['mixed_summary']['total_rounds'],
            'total_prize' => $report['mixed_summary']['total_prize'],
            'net_result' => $report['mixed_summary']['net_result'],
            'rtp' => $report['mixed_summary']['rtp'],
        ]]);
        $this->newLine();
        $this->info('Hilo Worst Case Summary');
        $this->table(['POLICY', 'TOTAL_ROUNDS', 'TOTAL_PRIZE', 'NET_RESULT', 'RTP'], [[
            'policy' => $report['worst_case_summary']['policy'],
            'total_rounds' => $report['worst_case_summary']['total_rounds'],
            'total_prize' => $report['worst_case_summary']['total_prize'],
            'net_result' => $report['worst_case_summary']['net_result'],
            'rtp' => $report['worst_case_summary']['rtp'],
        ]]);
    } else {
        $selected = collect($report['policies'])->firstWhere('policy', $policy);
        $this->info('Hilo Policy Summary');
        $this->table(['POLICY', 'TARGET_WINS', 'TOTAL_ROUNDS', 'TOTAL_PRIZE', 'NET_RESULT', 'RTP'], [[
            'policy' => $selected['policy'],
            'target_wins' => $selected['target_wins'],
            'total_rounds' => $selected['total_rounds'],
            'total_prize' => $selected['total_prize'],
            'net_result' => $selected['net_result'],
            'rtp' => $selected['rtp'],
        ]]);
    }

    return SymfonyCommand::SUCCESS;
})->purpose('Run N Hilo simulations and print policy summaries');

Artisan::command('goldentree:simulate {--hours=*} {--profile=all}', function () {
    $hours = $this->option('hours');
    $profile = (string) $this->option('profile');
    $parsedHours = $hours === [] ? GoldenTreeSimulationReportService::DEFAULT_HOURS : array_map(static fn ($value) => filter_var($value, FILTER_VALIDATE_INT), $hours);

    if (in_array(false, $parsedHours, true)) {
        $this->error('The --hours option must contain integers.');

        return SymfonyCommand::FAILURE;
    }

    try {
        $report = app(GoldenTreeSimulationReportService::class)->simulate(array_map('intval', $parsedHours), $profile);
    } catch (\InvalidArgumentException $e) {
        $this->error($e->getMessage());

        return SymfonyCommand::FAILURE;
    }

    $scenarioRows = array_map(static function (array $scenario): array {
        return [
            'profile' => $scenario['profile'],
            'hours' => $scenario['hours'],
            'generated_gross' => $scenario['generated_gross'],
            'collected_net' => $scenario['collected_net'],
            'commission_retained' => $scenario['commission_retained'],
            'water_spent' => $scenario['water_spent'],
            'wallet_delta' => $scenario['wallet_delta'],
            'system_retained' => $scenario['system_retained'],
            'target_met' => $scenario['target_met'] ? 'yes' : 'no',
        ];
    }, $report['scenarios']);

    $this->info('GoldenTree Economic Scenarios');
    $this->table(['PROFILE', 'HOURS', 'GENERATED_GROSS', 'COLLECTED_NET', 'COMMISSION', 'WATER_SPENT', 'WALLET_DELTA', 'SYSTEM_RETAINED', 'TARGET_MET'], $scenarioRows);
    $this->newLine();
    $this->info('GoldenTree Validation');
    $this->table(['TARGET_MET', 'REQUIRES_RATE_REDESIGN'], [[
        'target_met' => $report['validation']['target_met'] ? 'yes' : 'no',
        'requires_rate_redesign' => $report['validation']['requires_rate_redesign'] ? 'yes' : 'no',
    ]]);

    return SymfonyCommand::SUCCESS;
})->purpose('Run GoldenTree economic simulations for configured profiles and horizons');
