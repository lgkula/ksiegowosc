<?php

namespace App\Services\Settlement;

/** Sumy jednego miesiąca potrzebne do wyliczeń podatkowych. */
final class PeriodTotals
{
    public function __construct(
        public string $period,
        public float $revenue = 0,
        public float $costs = 0,
        public float $incomeDeductions = 0,
        public float $healthPaid = 0,
        public float $vatOutput = 0,
        public float $vatInput = 0,
    ) {}

    public function income(): float
    {
        return round($this->revenue - $this->costs, 2);
    }

    /** Dochód pomniejszony o składki społeczne - podstawa składki zdrowotnej. */
    public function healthBaseIncome(): float
    {
        return round($this->revenue - $this->costs - $this->incomeDeductions, 2);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
