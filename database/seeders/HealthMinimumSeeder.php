<?php

namespace Database\Seeders;

use App\Models\HealthMinimum;
use Illuminate\Database\Seeder;

/** Minimalne podstawy składki zdrowotnej (skala). Kolejne zmiany dodaje się w aplikacji. */
class HealthMinimumSeeder extends Seeder
{
    public function run(): void
    {
        $minimums = [
            '2025-01' => [3499.50, '75% minimalnego wynagrodzenia 4 666 zł (składka 314,96 zł)'],
            '2026-02' => [4806.00, 'Minimalne wynagrodzenie 4 806 zł (składka 432,54 zł), do stycznia 2027'],
        ];

        foreach ($minimums as $validFrom => [$base, $note]) {
            HealthMinimum::updateOrCreate(['valid_from' => $validFrom], ['base' => $base, 'note' => $note]);
        }
    }
}
