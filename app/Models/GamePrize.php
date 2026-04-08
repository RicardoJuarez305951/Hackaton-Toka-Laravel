<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePrize extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'name',
        'multiplier',
        'probability',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
            'probability' => 'decimal:5',
            'is_active' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
