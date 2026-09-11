<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardSetting extends Model
{
    public const FINALE_PRIZE_LIMIT = 'finale_prize_limit';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public static function setValue(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    public static function finalePrizeLimit(): int
    {
        return max(0, (int) static::getValue(
            self::FINALE_PRIZE_LIMIT,
            config('roulette.finale_prize_limit', 5),
        ));
    }
}
