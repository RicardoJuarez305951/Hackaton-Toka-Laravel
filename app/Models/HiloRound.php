<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiloRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bet',
        'starting_balance',
        'current_rank',
        'current_suit',
        'streak',
        'current_multiplier',
        'potential_payout',
        'is_active',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starting_balance' => 'integer',
            'current_multiplier' => 'float',
            'potential_payout' => 'integer',
            'is_active' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
