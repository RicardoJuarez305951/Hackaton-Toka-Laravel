<?php

namespace App\Services;

class PlinkoPhysicsService
{
    private const DT = 1 / 120;
    private const MAX_STEPS = 1800;
    private const SAMPLE_EVERY_STEPS = 4;
    private const GRAVITY = 2400.0;
    private const RESTITUTION = 0.72;
    private const FRICTION = 0.06;
    private const BALL_RADIUS = 9.0;
    private const PEG_RADIUS = 6.0;
    private const MAX_SPEED = 2600.0;

    private const BOARD_WIDTH = 350.0;
    private const BOARD_HEIGHT = 350.0;
    private const BOARD_ROWS = 6;
    private const BOARD_SLOTS = 7;
    private const BOARD_START_X = 175.0;
    private const BOARD_START_Y = 20.0;
    private const BOARD_ROW_START_Y = 50.0;
    private const BOARD_ROW_SPACING = 40.0;
    private const BOARD_PEG_SPACING_X = 45.0;
    private const BOARD_SLOT_Y = 295.0;

    public function simulate(?int $seed = null): array
    {
        $seed = $seed ?? random_int(1, 2147483646);
        $rngState = max(1, $seed);

        $board = $this->buildBoardConfig();
        $pegs = $this->buildPegs($board);

        $x = self::BOARD_START_X + $this->randomRange($rngState, -1.5, 1.5);
        $y = self::BOARD_START_Y;
        $vx = $this->randomRange($rngState, -60.0, 60.0);
        $vy = 0.0;

        $keyframes = [];
        $this->addKeyframe($keyframes, 0, $x, $y, 'spawn');

        $legacyPositions = [$this->xToSlot($x, $board['width'], $board['slots'])];
        $rowsResolved = array_fill(0, $board['rows'], false);
        $lastHitByPeg = [];

        $slotIndex = $legacyPositions[0];
        $durationMs = 0;

        for ($step = 1; $step <= self::MAX_STEPS; $step++) {
            $timeMs = (int) round($step * self::DT * 1000);

            $vx += $this->randomRange($rngState, -20.0, 20.0) * self::DT;
            $vy += self::GRAVITY * self::DT;

            $vx *= (1 - (self::FRICTION * self::DT));
            $vy *= (1 - ((self::FRICTION * 0.35) * self::DT));

            $speed = hypot($vx, $vy);
            if ($speed > self::MAX_SPEED && $speed > 0) {
                $scale = self::MAX_SPEED / $speed;
                $vx *= $scale;
                $vy *= $scale;
            }

            $x += $vx * self::DT;
            $y += $vy * self::DT;

            if ($x < self::BALL_RADIUS) {
                $x = self::BALL_RADIUS;
                $vx = abs($vx) * self::RESTITUTION;
                $this->addKeyframe($keyframes, $timeMs, $x, $y, 'wall', ['side' => 'left']);
            } elseif ($x > ($board['width'] - self::BALL_RADIUS)) {
                $x = $board['width'] - self::BALL_RADIUS;
                $vx = -abs($vx) * self::RESTITUTION;
                $this->addKeyframe($keyframes, $timeMs, $x, $y, 'wall', ['side' => 'right']);
            }

            foreach ($pegs as $peg) {
                $dx = $x - $peg['x'];
                $dy = $y - $peg['y'];
                $distanceSq = ($dx * $dx) + ($dy * $dy);
                $minDistance = self::BALL_RADIUS + self::PEG_RADIUS;
                $minDistanceSq = $minDistance * $minDistance;

                if ($distanceSq >= $minDistanceSq) {
                    continue;
                }

                $distance = sqrt(max($distanceSq, 0.0001));
                $nx = $dx / $distance;
                $ny = $dy / $distance;

                $penetration = $minDistance - $distance;
                $x += $nx * $penetration;
                $y += $ny * $penetration;

                $normalVelocity = ($vx * $nx) + ($vy * $ny);
                if ($normalVelocity < 0) {
                    $vx -= (1 + self::RESTITUTION) * $normalVelocity * $nx;
                    $vy -= (1 + self::RESTITUTION) * $normalVelocity * $ny;

                    $tx = -$ny;
                    $ty = $nx;
                    $tangentVelocity = ($vx * $tx) + ($vy * $ty);
                    $vx -= self::FRICTION * $tangentVelocity * $tx;
                    $vy -= self::FRICTION * $tangentVelocity * $ty;

                    $vx += $this->randomRange($rngState, -12.0, 12.0);
                }

                $pegKey = $peg['row'].':'.$peg['col'];
                $lastHitStep = $lastHitByPeg[$pegKey] ?? -100;
                if (($step - $lastHitStep) >= 3) {
                    $lastHitByPeg[$pegKey] = $step;
                    $this->addKeyframe($keyframes, $timeMs, $x, $y, 'hit', [
                        'row' => $peg['row'],
                        'col' => $peg['col'],
                    ]);
                }
            }

            for ($row = 0; $row < $board['rows']; $row++) {
                if ($rowsResolved[$row]) {
                    continue;
                }

                $rowY = $board['row_start_y'] + ($row * $board['row_spacing']);
                if ($y >= $rowY) {
                    $legacyPositions[] = $this->xToSlot($x, $board['width'], $board['slots']);
                    $rowsResolved[$row] = true;
                }
            }

            if ($step % self::SAMPLE_EVERY_STEPS === 0) {
                $this->addKeyframe($keyframes, $timeMs, $x, $y, 'move');
            }

            if ($y >= $board['slot_y']) {
                $y = $board['slot_y'];
                $slotIndex = $this->xToSlot($x, $board['width'], $board['slots']);
                $this->addKeyframe($keyframes, $timeMs, $x, $y, 'slot', ['slot' => $slotIndex]);
                $durationMs = $timeMs;
                break;
            }
        }

        if ($durationMs === 0) {
            $slotIndex = $this->xToSlot($x, $board['width'], $board['slots']);
            $durationMs = (int) round(self::MAX_STEPS * self::DT * 1000);
            $this->addKeyframe($keyframes, $durationMs, $x, $board['slot_y'], 'slot', ['slot' => $slotIndex]);
        }

        while (count($legacyPositions) < ($board['rows'] + 1)) {
            $legacyPositions[] = $slotIndex;
        }

        return [
            'slot_index' => $slotIndex,
            'spline' => [
                'positions' => $legacyPositions,
                'final_slot' => $slotIndex,
                'keyframes' => $keyframes,
                'duration_ms' => $durationMs,
                'seed' => $seed,
                'board' => [
                    'width' => $board['width'],
                    'height' => $board['height'],
                    'rows' => $board['rows'],
                    'slots' => $board['slots'],
                    'start_x' => $board['start_x'],
                    'start_y' => $board['start_y'],
                    'row_start_y' => $board['row_start_y'],
                    'row_spacing' => $board['row_spacing'],
                    'slot_y' => $board['slot_y'],
                    'ball_radius' => self::BALL_RADIUS,
                    'peg_radius' => self::PEG_RADIUS,
                ],
                'physics' => [
                    'dt' => self::DT,
                    'max_steps' => self::MAX_STEPS,
                    'gravity' => self::GRAVITY,
                    'restitution' => self::RESTITUTION,
                    'friction' => self::FRICTION,
                    'sample_every_steps' => self::SAMPLE_EVERY_STEPS,
                ],
            ],
        ];
    }

    private function buildBoardConfig(): array
    {
        return [
            'width' => self::BOARD_WIDTH,
            'height' => self::BOARD_HEIGHT,
            'rows' => self::BOARD_ROWS,
            'slots' => self::BOARD_SLOTS,
            'start_x' => self::BOARD_START_X,
            'start_y' => self::BOARD_START_Y,
            'row_start_y' => self::BOARD_ROW_START_Y,
            'row_spacing' => self::BOARD_ROW_SPACING,
            'peg_spacing_x' => self::BOARD_PEG_SPACING_X,
            'slot_y' => self::BOARD_SLOT_Y,
        ];
    }

    private function buildPegs(array $board): array
    {
        $pegs = [];

        for ($row = 0; $row < $board['rows']; $row++) {
            $pegsInRow = $row + 1;
            $rowWidth = $pegsInRow * $board['peg_spacing_x'];
            $startX = (($board['width'] - $rowWidth) / 2.0) + ($board['peg_spacing_x'] / 2.0);

            for ($col = 0; $col < $pegsInRow; $col++) {
                $pegs[] = [
                    'row' => $row,
                    'col' => $col,
                    'x' => $startX + ($col * $board['peg_spacing_x']),
                    'y' => $board['row_start_y'] + ($row * $board['row_spacing']),
                ];
            }
        }

        return $pegs;
    }

    private function xToSlot(float $x, float $boardWidth, int $slotCount): int
    {
        $slotWidth = $boardWidth / $slotCount;
        $slotIndex = (int) floor($x / $slotWidth);

        return max(0, min($slotCount - 1, $slotIndex));
    }

    private function addKeyframe(array &$keyframes, int $tMs, float $x, float $y, string $event, array $meta = []): void
    {
        $frame = [
            't_ms' => max(0, $tMs),
            'x' => round($x, 3),
            'y' => round($y, 3),
            'event' => $event,
        ];

        foreach ($meta as $key => $value) {
            $frame[$key] = $value;
        }

        $keyframes[] = $frame;
    }

    private function randomRange(int &$state, float $min, float $max): float
    {
        $state = (int) ((1103515245 * $state + 12345) % 2147483648);
        $random = $state / 2147483647;

        return $min + (($max - $min) * $random);
    }
}
