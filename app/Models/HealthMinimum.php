<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthMinimum extends Model
{
    protected $fillable = ['valid_from', 'base', 'note'];

    protected function casts(): array
    {
        return [
            'base' => 'decimal:2',
        ];
    }

    /** Wpis obowiązujący w danym miesiącu: ostatni z valid_from nie późniejszym niż okres. */
    public static function forPeriod(string $period): self
    {
        return static::where('valid_from', '<=', $period)->orderByDesc('valid_from')->first()
            ?? throw new \RuntimeException("Brak minimalnej podstawy składki zdrowotnej dla {$period}. Uzupełnij ją w Ustawienia → Minimalna zdrowotna.");
    }
}
