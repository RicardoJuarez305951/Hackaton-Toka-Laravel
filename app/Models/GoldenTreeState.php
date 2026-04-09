<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldenTreeState extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stage',
        'growth_seconds',
        'banked_tp',
        'total_generated',
        'events_attended',
        'last_processed_at',
        'last_event_check_at',
        'active_event_id',
        'active_event_expires_at',
        'extra_pause_until',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'growth_seconds' => 'integer',
            'banked_tp' => 'integer',
            'total_generated' => 'integer',
            'events_attended' => 'integer',
            'last_processed_at' => 'datetime',
            'last_event_check_at' => 'datetime',
            'active_event_expires_at' => 'datetime',
            'extra_pause_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
