<?php

use App\Models\TaxParameter;
use App\Services\Settlement\ItemAmounts;
use App\Services\Settlement\TaxCalculator;

function params2026(array $overrides = []): TaxParameter
{
    return new TaxParameter($overrides + [
        'year' => 2026,
        'pit_rate_1' => 12, 'pit_rate_2' => 32, 'pit_threshold' => 120000, 'pit_reducing_amount' => 3600,
        'zus_base' => 5652.00, 'zus_pension_rate' => 19.52, 'zus_disability_rate' => 8, 'zus_sickness_rate' => 2.45,
        'zus_accident_rate' => 1.67, 'zus_labour_fund_rate' => 2.45, 'zus_sickness_enabled' => true,
        'health_rate' => 9,
    ]);
}

it('liczy paliwo do auta mieszanego: 50% VAT odliczone, 75% reszty w koszty', function () {
    $amounts = ItemAmounts::forCost(net: 200, vat: 46, pitPercent: 75, vatPercent: 50);

    expect($amounts->vatDeductible)->toBe(23.0)
        ->and($amounts->pitCost)->toBe(167.25);
});

it('liczy koszt w pełni odliczalny', function () {
    $amounts = ItemAmounts::forCost(net: 100, vat: 8, pitPercent: 100, vatPercent: 100);

    expect($amounts->vatDeductible)->toBe(8.0)
        ->and($amounts->pitCost)->toBe(100.0);
});

it('dolicza cały VAT do kosztu, gdy VAT nie podlega odliczeniu', function () {
    $amounts = ItemAmounts::forCost(net: 100, vat: 8, pitPercent: 100, vatPercent: 0);

    expect($amounts->vatDeductible)->toBe(0.0)
        ->and($amounts->pitCost)->toBe(108.0);
});

it('liczy podatek wg skali poniżej i powyżej progu', function () {
    $calc = new TaxCalculator(params2026());

    expect($calc->scaleTax(20000))->toBe(0)
        ->and($calc->scaleTax(50000))->toBe(2400)
        ->and($calc->scaleTax(150000))->toBe(20400)
        ->and($calc->scaleTax(-500))->toBe(0);
});

it('liczy zaliczkę narastająco po odjęciu zaliczek z poprzednich miesięcy', function () {
    $calc = new TaxCalculator(params2026());

    $jan = $calc->pitAdvance(40000, 2000, 0);
    $feb = $calc->pitAdvance(80000, 4000, $jan['advance']);

    expect($jan['base'])->toBe(38000.0)
        ->and($jan['advance'])->toBe(960)
        ->and($feb['base'])->toBe(76000.0)
        ->and($feb['cumulative_tax'])->toBe(5520)
        ->and($feb['advance'])->toBe(4560);
});

it('przechodzi na stawkę 32% po przekroczeniu progu w trakcie roku', function () {
    $calc = new TaxCalculator(params2026());

    $result = $calc->pitAdvance(130000, 0, 10000);

    expect($result['above_threshold'])->toBeTrue()
        ->and($result['cumulative_tax'])->toBe(14000) // 120 000 × 12% − 3 600 + 10 000 × 32%
        ->and($result['advance'])->toBe(4000);
});

it('liczy pełny ZUS społeczny', function () {
    $zus = (new TaxCalculator(params2026()))->socialContributions();

    expect($zus['parts'])->toBe([
        'emerytalna' => 1103.27,
        'rentowa' => 452.16,
        'chorobowa' => 138.47,
        'wypadkowa' => 94.39,
        'fundusz_pracy' => 138.47,
    ])->and($zus['total'])->toBe(1926.76);
});

it('pomija chorobową, gdy jest wyłączona', function () {
    $zus = (new TaxCalculator(params2026(['zus_sickness_enabled' => false])))->socialContributions();

    expect($zus['parts']['chorobowa'])->toBe(0.0)
        ->and($zus['total'])->toBe(1788.29);
});

it('liczy zdrowotną od dochodu, nie mniej niż minimum', function () {
    $calc = new TaxCalculator(params2026());

    expect($calc->health(10000, 4806)['amount'])->toBe(900.0)
        ->and($calc->health(1000, 4806)['amount'])->toBe(432.54)
        ->and($calc->health(1000, 4806)['is_minimum'])->toBeTrue()
        ->and($calc->health(-5000, 3499.50)['amount'])->toBe(314.96);
});
