<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WinRecord extends Model
{
    protected $fillable = [
        'prize_name',
        'winner_name',
        'drawn_at',
    ];

    protected function casts(): array
    {
        return [
            'drawn_at' => 'datetime',
        ];
    }
}
