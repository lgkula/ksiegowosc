<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxParameter extends Model
{
    protected $fillable = [
        'year', 'pit_rate_1', 'pit_rate_2', 'pit_threshold', 'pit_reducing_amount',
        'zus_base', 'zus_pension_rate', 'zus_disability_rate', 'zus_sickness_rate', 'zus_accident_rate',
        'zus_labour_fund_rate', 'zus_sickness_enabled', 'health_rate',
    ];

    protected function casts(): array
    {
        return [
            'zus_sickness_enabled' => 'boolean',
        ];
    }

    /** Parametry dla roku albo, gdy ich brak, z najbliższego wcześniejszego roku. */
    public static function forYear(int $year): self
    {
        return static::where('year', $year)->first()
            ?? static::where('year', '<', $year)->orderByDesc('year')->first()
            ?? throw new \RuntimeException("Brak parametrów podatkowych dla roku {$year}.");
    }
}
