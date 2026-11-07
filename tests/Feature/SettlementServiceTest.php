<?php

use App\Models\Category;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\HealthMinimum;
use App\Models\Loan;
use App\Models\MonthlySettlement;
use App\Services\Settlement\SettlementService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TaxParameterSeeder;

beforeEach(function () {
    $this->seed([TaxParameterSeeder::class, CategorySeeder::class]);
    $this->service = app(SettlementService::class);
});

function document(string $direction, string $date, string $category, float $net, float $vat, array $attributes = []): Document
{
    $document = Document::create($attributes + [
        'direction' => $direction,
        'source' => 'manual',
        'kind' => 'invoice',
        'number' => 'FV/'.uniqid(),
        'issue_date' => $date,
        'net' => $net,
        'vat' => $vat,
        'gross' => $net + $vat,
        'included' => true,
    ]);

    $document->items()->create([
        'name' => $category,
        'net' => $net,
        'vat' => $vat,
        'gross' => $net + $vat,
        'category_id' => Category::where('name', $category)->value('id'),
        'category_source' => 'manual',
    ]);

    return $document;
}

it('wylicza rozliczenie miesiąca: dochód, PIT, ZUS, zdrowotną i VAT', function () {
    document('revenue', '2026-01-15', 'Sprzedaż usług', 40000, 9200);
    document('cost', '2026-01-10', 'Paliwo', 200, 46);
    document('cost', '2026-01-20', 'ZUS społeczne', 1926.76, 0, ['kind' => 'transfer']);

    $result = $this->service->monthly('2026-01');

    expect($result['totals']['revenue'])->toBe(40000.0)
        ->and($result['totals']['costs'])->toBe(167.25)
        ->and($result['totals']['income'])->toBe(39832.75)
        ->and($result['pit']['base'])->toBe(37906.0)
        ->and($result['pit']['advance'])->toBe(949)
        ->and($result['zus']['total'])->toBe(1926.76)
        ->and($result['health']['is_minimum'])->toBeTrue()
        ->and($result['vat']['to_pay'])->toBe(9177);
});

it('stosuje minimalną zdrowotną obowiązującą w danym miesiącu, także przy zmianie w trakcie roku', function () {
    expect($this->service->monthly('2025-12')['health']['minimum'])->toBe(314.96)
        ->and($this->service->monthly('2026-01')['health']['minimum'])->toBe(314.96)
        ->and($this->service->monthly('2026-02')['health']['minimum'])->toBe(432.54)
        ->and($this->service->monthly('2026-11')['health']['amount'])->toBe(432.54);

    HealthMinimum::create(['valid_from' => '2026-07', 'base' => 5000]);

    expect($this->service->monthly('2026-06')['health']['minimum'])->toBe(432.54)
        ->and($this->service->monthly('2026-07')['health']['minimum'])->toBe(450.0)
        ->and($this->service->monthly('2026-07')['health']['minimum_base'])->toBe(5000.0);
});

it('liczy zdrowotną od dochodu poprzedniego miesiąca pomniejszonego o ZUS', function () {
    document('revenue', '2026-01-15', 'Sprzedaż usług', 40000, 9200);
    document('cost', '2026-01-10', 'Paliwo', 200, 46);
    document('cost', '2026-01-20', 'ZUS społeczne', 1926.76, 0, ['kind' => 'transfer']);

    $feb = $this->service->monthly('2026-02');

    expect($feb['health']['base_income'])->toBe(37905.99)
        ->and($feb['health']['amount'])->toBe(3411.54)
        ->and($feb['pit']['advance'])->toBe(0);
});

it('pomija dokumenty niezatwierdzone i oznaczone jako pominięte', function () {
    document('cost', '2026-03-10', 'Biuro i materiały', 1000, 230, ['included' => null]);
    document('cost', '2026-03-11', 'Biuro i materiały', 500, 115, ['included' => false]);
    document('cost', '2026-03-12', 'Biuro i materiały', 100, 23);

    $result = $this->service->monthly('2026-03');

    expect($result['totals']['costs'])->toBe(100.0)
        ->and($result['pending_documents'])->toBe(1);
});

it('przenosi nadwyżkę VAT naliczonego na kolejny miesiąc', function () {
    document('cost', '2026-04-10', 'Biuro i materiały', 1000, 230);
    document('revenue', '2026-05-10', 'Sprzedaż usług', 500, 115);

    $april = $this->service->monthly('2026-04');
    $may = $this->service->monthly('2026-05');

    expect($april['vat']['to_pay'])->toBe(0)
        ->and($april['vat']['carried_out'])->toBe(230.0)
        ->and($may['vat']['carried_in'])->toBe(230.0)
        ->and($may['vat']['to_pay'])->toBe(0)
        ->and($may['vat']['carried_out'])->toBe(115.0);
});

it('wydziela koszty kontrahenta z osobną sekcją', function () {
    $kokot = Contractor::create(['full_name' => 'Joanna Kokot', 'own_settlement_section' => true]);
    document('cost', '2026-06-01', 'Usługi obce', 3000, 690, ['contractor_id' => $kokot->id]);
    document('cost', '2026-06-02', 'Biuro i materiały', 100, 23);

    $result = $this->service->monthly('2026-06');

    expect($result['dedicated_cost_groups'])->toHaveCount(1)
        ->and($result['dedicated_cost_groups'][0]['name'])->toBe('Joanna Kokot')
        ->and($result['dedicated_cost_groups'][0]['pit_cost'])->toBe(3000.0)
        ->and(collect($result['cost_groups'])->pluck('name')->all())->toBe(['Biuro i materiały']);
});

it('wylicza ile zostawić na koncie i ile przelać na prywatne', function () {
    document('revenue', '2026-07-15', 'Sprzedaż usług', 10000, 2300);
    Loan::create(['name' => 'Kredyt auto', 'installment' => 1500, 'from_period' => '2026-01']);
    Loan::create(['name' => 'Spłacony', 'installment' => 999, 'from_period' => '2025-01', 'to_period' => '2025-12']);
    MonthlySettlement::create(['period' => '2026-07', 'account_balance' => 20000, 'extra_reserve' => 500]);

    $cash = $this->service->monthly('2026-07')['cash'];
    $names = array_column($cash['obligations'], 'name');

    expect($names)->toContain('Rata: Kredyt auto')
        ->and($names)->not->toContain('Rata: Spłacony')
        ->and($cash['to_keep'])->toBe(round(array_sum(array_column($cash['obligations'], 'amount')), 2))
        ->and($cash['to_transfer'])->toBe(round(20000 - $cash['to_keep'], 2));
});

it('dolicza inne przychody z rozliczenia miesiąca', function () {
    MonthlySettlement::create(['period' => '2026-08', 'other_income' => 250]);

    expect($this->service->monthly('2026-08')['totals']['revenue'])->toBe(250.0);
});

it('zwraca snapshot zamkniętego miesiąca mimo późniejszych zmian', function () {
    document('revenue', '2026-09-15', 'Sprzedaż usług', 1000, 230);
    $this->service->close('2026-09');
    document('revenue', '2026-09-16', 'Sprzedaż usług', 5000, 1150);

    expect($this->service->monthly('2026-09')['totals']['revenue'])->toEqual(1000)
        ->and($this->service->monthly('2026-09')['closed'])->toBeTrue()
        ->and($this->service->monthly('2026-09', fresh: true)['totals']['revenue'])->toBe(6000.0);
});

it('buduje zestawienie roczne ze średnim dochodem', function () {
    document('revenue', '2026-01-15', 'Sprzedaż usług', 10000, 2300);
    document('cost', '2026-01-16', 'Biuro i materiały', 2000, 460);
    document('revenue', '2026-02-15', 'Sprzedaż usług', 6000, 1380);

    $year = $this->service->yearly(2026);

    expect($year['rows'][0]['income'])->toBe(8000.0)
        ->and($year['rows'][1]['cumulative_income'])->toBe(14000.0)
        ->and($year['summary']['revenue'])->toBe(16000.0)
        ->and($year['summary']['costs'])->toBe(2000.0)
        ->and($year['summary']['months_with_data'])->toBe(2)
        ->and($year['summary']['average_income'])->toBe(7000.0);
});
