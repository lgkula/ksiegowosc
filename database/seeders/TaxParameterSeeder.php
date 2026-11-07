<?php

namespace Database\Seeders;

use App\Models\TaxParameter;
use Illuminate\Database\Seeder;

/**
 * Parametry startowe - do weryfikacji i edycji w aplikacji (Ustawienia → Parametry podatkowe).
 * Podstawa ZUS = 60% prognozowanego przeciętnego wynagrodzenia.
 * Minimalna podstawa zdrowotnej ma osobne okresy obowiązywania (HealthMinimumSeeder).
 */
class TaxParameterSeeder extends Seeder
{
    public function run(): void
    {
        $years = [
            2025 => ['zus_base' => 5203.80],
            2026 => ['zus_base' => 5652.00],
        ];

        foreach ($years as $year => $values) {
            TaxParameter::updateOrCreate(['year' => $year], $values + [
                'pit_rate_1' => 12,
                'pit_rate_2' => 32,
                'pit_threshold' => 120000,
                'pit_reducing_amount' => 3600,
                'zus_pension_rate' => 19.52,
                'zus_disability_rate' => 8,
                'zus_sickness_rate' => 2.45,
                'zus_accident_rate' => 1.67,
                'zus_labour_fund_rate' => 2.45,
                'zus_sickness_enabled' => true,
                'health_rate' => 9,
            ]);
        }

        $this->call(HealthMinimumSeeder::class);
    }
}
