<?php

namespace App\Services;

class PlinkoPhysicsService
{
    public const SLOT_MULTIPLIERS = [4.2, 1.7, 0.8, 0.4, 0.8, 1.7, 4.2];

    private const BOARD_WIDTH = 350.0;
    private const BOARD_HEIGHT = 350.0;
    private const BOARD_ROWS = 6;
    private const BOARD_SLOTS = 7;
    private const BOARD_START_X = 175.0;
    private const BOARD_START_Y = 20.0;
    private const BOARD_ROW_START_Y = 50.0;
    private const BOARD_ROW_SPACING = 40.0;
    private const BOARD_STEP_X = 45.0;
    private const BOARD_SLOT_Y = 295.0;

    public function __construct(private readonly SecureRandomService $random)
    {
    }

    public function simulate(?array $path = null): array
    {
        $path = $path ?? $this->generatePath();
        $this->assertValidPath($path);

        $board = $this->buildBoardConfig();
        $x = self::BOARD_START_X;
        $y = self::BOARD_START_Y;
        $rights = 0;
        $positions = [$this->xToSlot($x, $board['width'], $board['slots'])];
        $keyframes = [];
        $timeMs = 0;

        $this->addKeyframe($keyframes, $timeMs, $x, $y, 'spawn');

        foreach ($path as $row => $step) {
            $hitCol = $rights;
            $hitX = self::BOARD_START_X + (((2 * $hitCol) - $row) * self::BOARD_STEP_X);
            $hitY = self::BOARD_ROW_START_Y + ($row * self::BOARD_ROW_SPACING);
            $timeMs += 180;
            $this->addKeyframe($keyframes, $timeMs, $hitX, $hitY, 'hit', [
                'row' => $row,
                'col' => $hitCol,
            ]);

            if ((int) $step === 1) {
                $rights++;
                $x = $hitX + self::BOARD_STEP_X;
            } else {
                $x = $hitX - self::BOARD_STEP_X;
            }

            $y = min(self::BOARD_SLOT_Y, $hitY + self::BOARD_ROW_SPACING);
            $timeMs += 180;
            $this->addKeyframe($keyframes, $timeMs, $x, $y, 'move');
            $positions[] = $this->xToSlot($x, $board['width'], $board['slots']);
        }

        $finalSlot = $rights;
        $finalX = self::BOARD_START_X + (((2 * $finalSlot) - self::BOARD_ROWS) * self::BOARD_STEP_X);
        $timeMs += 220;
        $this->addKeyframe($keyframes, $timeMs, $finalX, self::BOARD_SLOT_Y, 'slot', ['slot' => $finalSlot]);

        return [
            'path' => $path,
            'slot_index' => $finalSlot,
            'spline' => [
                'path' => $path,
                'positions' => $positions,
                'final_slot' => $finalSlot,
                'keyframes' => $keyframes,
                'duration_ms' => $timeMs,
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
                ],
                'physics' => [
                    'mode' => 'path_driven',
                    'steps' => count($path),
                ],
            ],
        ];
    }

    private function generatePath(): array
    {
        $path = [];

        for ($step = 0; $step < self::BOARD_ROWS; $step++) {
            $path[] = $this->random->coinFlip();
        }

        return $path;
    }

    private function assertValidPath(array $path): void
    {
        if (count($path) !== self::BOARD_ROWS) {
            throw new \InvalidArgumentException('Plinko path must have exactly 6 steps.');
        }

        foreach ($path as $step) {
            if (! in_array((int) $step, [0, 1], true)) {
                throw new \InvalidArgumentException('Plinko path values must be binary.');
            }
        }
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
            'slot_y' => self::BOARD_SLOT_Y,
        ];
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
            't_ms' => $tMs,
            'x' => round($x, 3),
            'y' => round($y, 3),
            'event' => $event,
        ];

        foreach ($meta as $key => $value) {
            $frame[$key] = $value;
        }

        $keyframes[] = $frame;
    }
}
