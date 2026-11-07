<?php

namespace App\Services\Settlement;

/**
 * Kwoty jednej pozycji kosztowej po zastosowaniu limitów kategorii.
 * VAT nieodliczony powiększa koszt PIT, np. paliwo do auta mieszanego:
 * VAT odliczony = 50% VAT, koszt PIT = 75% × (netto + pozostałe 50% VAT).
 */
final readonly class ItemAmounts
{
    public function __construct(
        public float $net,
        public float $vat,
        public float $vatDeductible,
        public float $pitCost,
    ) {}

    public static function forCost(float $net, float $vat, float $pitPercent, float $vatPercent): self
    {
        $vatDeductible = round($vat * $vatPercent / 100, 2);
        $vatNonDeductible = round($vat - $vatDeductible, 2);
        $pitCost = round(($net + $vatNonDeductible) * $pitPercent / 100, 2);

        return new self($net, $vat, $vatDeductible, $pitCost);
    }
}
