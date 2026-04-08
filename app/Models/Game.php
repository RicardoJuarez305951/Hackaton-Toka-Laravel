<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function prizes()
    {
        return $this->hasMany(GamePrize::class)->where('is_active', true);
    }

    public function allPrizes()
    {
        return $this->hasMany(GamePrize::class);
    }

    public function history()
    {
        return $this->hasMany(GameHistory::class);
    }
}
