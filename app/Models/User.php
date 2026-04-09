<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'toka_id',
        'coins',
    ];

    protected $hidden = [
        'remember_token',
    ];

    public function streak(): HasOne
    {
        return $this->hasOne(UserStreak::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(GameHistory::class);
    }

    public function canPlay(int $bet): bool
    {
        return $this->coins >= $bet;
    }
}
