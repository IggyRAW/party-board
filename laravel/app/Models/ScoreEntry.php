<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreEntry extends Model
{
    protected $fillable = [
        'team_id',
        'score',
        'label',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'scored_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
