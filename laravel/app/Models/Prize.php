<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Prize extends Model
{
    protected $fillable = [
        'name',
        'won_at',
        'is_finale',
    ];

    protected function casts(): array
    {
        return [
            'won_at' => 'datetime',
            'is_finale' => 'boolean',
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('won_at');
    }

    public function isWon(): bool
    {
        return $this->won_at !== null;
    }
}
