<?php

namespace App\Services\Settlement;

use App\Models\TaxParameter;

/** Wyliczenia PIT (skala), ZUS i składki zdrowotnej na podstawie parametrów roku. */
class TaxCalculator
{
    public function __construct(private TaxParameter $params) {}

    /** Podatek wg skali od podstawy (zaokrąglonej do pełnych złotych), już po kwocie zmniejszającej. */
    public function scaleTax(float $base): int
    {
        $base = max(0, round($base));
        $threshold = (float) $this->params->pit_threshold;
        $rate1 = (float) $this->params->pit_rate_1 / 100;
        $rate2 = (float) $this->params->pit_rate_2 / 100;
        $reducing = (float) $this->params->pit_reducing_amount;

        $tax = $base <= $threshold
            ? $base * $rate1 - $reducing
            : $threshold * $rate1 - $reducing + ($base - $threshold) * $rate2;

        return (int) max(0, round($tax));
    }

    /**
     * Zaliczka na PIT za miesiąc metodą narastającą.
     *
     * @param  float  $cumulativeIncome  dochód od początku roku
     * @param  float  $cumulativeDeductions  składki społeczne zapłacone od początku roku
     * @param  float  $previousAdvances  zaliczki należne za poprzednie miesiące roku
     */
    public function pitAdvance(float $cumulativeIncome, float $cumulativeDeductions, float $previousAdvances): array
    {
        $base = max(0, round($cumulativeIncome - $cumulativeDeductions));
        $cumulativeTax = $this->scaleTax($base);

        return [
            'base' => $base,
            'cumulative_tax' => $cumulativeTax,
            'previous_advances' => $previousAdvances,
            'advance' => (int) max(0, $cumulativeTax - $previousAdvances),
            'above_threshold' => $base > (float) $this->params->pit_threshold,
        ];
    }

    /** Składki społeczne z pełnej podstawy. */
    public function socialContributions(): array
    {
        $base = (float) $this->params->zus_base;
        $parts = [
            'emerytalna' => $this->params->zus_pension_rate,
            'rentowa' => $this->params->zus_disability_rate,
            'chorobowa' => $this->params->zus_sickness_enabled ? $this->params->zus_sickness_rate : 0,
            'wypadkowa' => $this->params->zus_accident_rate,
            'fundusz_pracy' => $this->params->zus_labour_fund_rate,
        ];

        $amounts = array_map(fn ($rate) => round($base * (float) $rate / 100, 2), $parts);

        return ['base' => $base, 'parts' => $amounts, 'total' => round(array_sum($amounts), 2)];
    }

    /**
     * Składka zdrowotna na skali: % dochodu z poprzedniego miesiąca, nie mniej niż minimum.
     *
     * @param  float  $minimumBase  minimalna podstawa obowiązująca w miesiącu, za który liczona jest składka
     */
    public function health(float $previousMonthIncome, float $minimumBase): array
    {
        $rate = (float) $this->params->health_rate / 100;
        $minimum = round($minimumBase * $rate, 2);
        $fromIncome = round(max(0, $previousMonthIncome) * $rate, 2);

        return [
            'base_income' => round($previousMonthIncome, 2),
            'minimum_base' => round($minimumBase, 2),
            'minimum' => $minimum,
            'amount' => max($minimum, $fromIncome),
            'is_minimum' => $fromIncome <= $minimum,
        ];
    }
}
