<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameHistory extends Model
{
    use HasFactory;

    protected $table = 'game_histories';

    protected $fillable = [
        'user_id',
        'game_id',
        'game_prize_id',
        'bet',
        'prize',
        'balance_before',
        'balance_after',
        'meta',
        'played_at',
    ];

    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePrize(): BelongsTo
    {
        return $this->belongsTo(GamePrize::class, 'game_prize_id');
    }
}
