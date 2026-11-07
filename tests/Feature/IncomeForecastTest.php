<?php

use App\Filament\Pages\IncomeForecastPage;
use App\Models\Category;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\IncomeForecast;
use App\Models\User;
use App\Services\Forecast\IncomeForecastService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TaxParameterSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([TaxParameterSeeder::class, CategorySeeder::class]);
    $this->service = app(IncomeForecastService::class);

    $this->kruk = Contractor::create(['full_name' => 'KRUK Spółka Akcyjna', 'nip' => '8940006767']);
    $this->kokot = Contractor::create([
        'full_name' => 'Joanna Kokot Usługi Informatyczne',
        'nip' => 'PL9161389290',
        'own_settlement_section' => true,
        'default_category_id' => Category::where('name', 'Usługi obce')->value('id'),
    ]);
});

function forecastDocument(string $direction, string $date, array $items, array $attributes = []): Document
{
    $document = Document::create($attributes + [
        'direction' => $direction,
        'source' => 'ksef',
        'kind' => 'invoice',
        'number' => 'FV/'.uniqid(),
        'issue_date' => $date,
        'included' => true,
    ]);

    foreach ($items as $i => $item) {
        $document->items()->create($item + ['position' => $i + 1, 'vat_rate' => '23', 'category_source' => 'manual']);
    }
    $document->recalculateTotals();

    return $document;
}

it('zaczytuje fakturę dla Kruka z podziałem na wynagrodzenie i dodatki', function () {
    forecastDocument('revenue', '2026-11-05', [
        ['name' => 'Wynagrodzenie za listopad', 'net' => 20000, 'vat' => 4600, 'gross' => 24600],
        ['name' => 'Dodatek za dyżury', 'net' => 1500, 'vat' => 345, 'gross' => 1845],
    ], ['contractor_id' => $this->kruk->id, 'number' => 'FV/11/2026']);

    $input = $this->service->defaults('2026-11');

    expect($input['kruk_base'])->toBe(20000.0)
        ->and($input['kruk_additions'])->toBe(1500.0)
        ->and($input['kruk_vat_rate'])->toBe('23');
});

it('wylicza fakturę podwykonawcy z godzin, stawki i dodatkowego wynagrodzenia', function () {
    $invoice = $this->service->subcontractorInvoice(120, 95.5, 540, 12);

    expect($invoice['base'])->toBe(11460.0)
        ->and($invoice['net'])->toBe(12000.0)
        ->and($invoice['vat'])->toBe(2760.0)
        ->and($invoice['gross'])->toBe(14760.0)
        ->and($invoice['pit'])->toBe(1440.0)
        ->and($invoice['burden'])->toBe(4200.0)
        ->and($invoice['pit_cost'])->toBe(12000.0);
});

it('podpowiada stawkę godzinową wpisaną w poprzednim miesiącu', function () {
    // Stawka z ostatniej faktury jest starsza niż stawka wpisana w modelu poprzedniego miesiąca.
    forecastDocument('cost', '2026-09-04', [['name' => 'Usługi IT', 'qty' => 100, 'unit_net' => 80, 'net' => 8000, 'vat' => 1840, 'gross' => 9840]], ['contractor_id' => $this->kokot->id]);
    $this->service->save('2026-10', ['sub_hours' => 150, 'sub_rate' => 95, 'sub_pit_rate' => 19]);

    $input = $this->service->defaults('2026-11');

    expect($input['sub_rate'])->toBe(95.0)
        ->and($input['sub_hours'])->toBe(0.0)
        ->and($input['sub_pit_rate'])->toBe(19.0);

    IncomeForecast::where('period', '2026-10')->delete();
    expect($this->service->defaults('2026-11')['sub_rate'])->toBe(80.0);
});

it('zaczytuje fakturę podwykonawcy z miesiąca: pierwsza pozycja to godziny, kolejne to dodatkowe wynagrodzenie', function () {
    forecastDocument('cost', '2026-11-04', [
        ['name' => 'Usługi IT', 'qty' => 120, 'unit_net' => 90, 'net' => 10800, 'vat' => 2484, 'gross' => 13284],
        ['name' => 'Wdrożenie', 'net' => 700, 'vat' => 161, 'gross' => 861],
    ], ['contractor_id' => $this->kokot->id]);

    $input = $this->service->defaults('2026-11');

    expect($input['sub_hours'])->toBe(120.0)
        ->and($input['sub_rate'])->toBe(90.0)
        ->and($input['sub_extra'])->toBe(700.0);
});

it('prognozuje dochód, dochód narastająco i odchylenie od średniej', function () {
    $paliwo = Category::where('name', 'Paliwo')->value('id');

    // Poprzednie miesiące: dochód 10 000 i 14 000, średnia 12 000.
    forecastDocument('revenue', '2026-09-10', [['name' => 'Usługa', 'net' => 10000, 'vat' => 2300, 'gross' => 12300]], ['contractor_id' => $this->kruk->id]);
    forecastDocument('revenue', '2026-10-10', [['name' => 'Usługa', 'net' => 14000, 'vat' => 3220, 'gross' => 17220]], ['contractor_id' => $this->kruk->id]);

    // Bieżący miesiąc: paliwo (koszt 75% z netto + połowa VAT), faktura Kokot z KSeF ma być zastąpiona modelem.
    forecastDocument('cost', '2026-11-03', [['name' => 'Olej napędowy', 'net' => 200, 'vat' => 46, 'gross' => 246, 'category_id' => $paliwo]]);
    forecastDocument('cost', '2026-11-04', [['name' => 'Usługi IT', 'qty' => 100, 'unit_net' => 80, 'net' => 8000, 'vat' => 1840, 'gross' => 9840]], ['contractor_id' => $this->kokot->id]);
    forecastDocument('cost', '2026-11-06', [['name' => 'Olej napędowy', 'net' => 100, 'vat' => 23, 'gross' => 123, 'category_id' => $paliwo]], ['included' => null]);

    $defaults = $this->service->defaults('2026-11');
    expect($defaults['sub_hours'])->toBe(100.0)->and($defaults['sub_rate'])->toBe(80.0);

    $result = $this->service->calculate('2026-11', [
        'kruk_base' => 20000,
        'kruk_additions' => 2000,
        'kruk_vat_rate' => '23',
        'other_income' => [['name' => 'Promocja', 'net' => 500]],
        'sub_hours' => 110,
        'sub_rate' => 80,
        'include_pending' => false,
    ]);

    // Przychód 22 500, koszty 167,25 (paliwo) + 8 800 (Kokot) = 8 967,25.
    expect($result['revenue'])->toBe(22500.0)
        ->and($result['kruk']['gross'])->toBe(27060.0)
        ->and($result['document_costs'])->toBe(167.25)
        ->and($result['subcontractor']['net'])->toBe(8800.0)
        ->and($result['subcontractor']['gross'])->toBe(10824.0)
        ->and($result['costs'])->toBe(8967.25)
        ->and($result['income'])->toBe(13532.75)
        ->and($result['previous']['average'])->toBe(12000.0)
        ->and($result['cumulative_income'])->toBe(37532.75)
        ->and($result['average_income'])->toBe(12510.92)
        ->and($result['deviation'])->toBe(1532.75)
        ->and($result['deviation_percent'])->toBe(12.8)
        ->and($result['pending_count'])->toBe(1);

    $withPending = $this->service->calculate('2026-11', ['include_pending' => true] + $defaults);
    expect($withPending['document_costs'])->toBe(250.88);
});

it('renderuje stronę prognozy i zapisuje wpisane wartości', function () {
    $this->actingAs(User::factory()->create());

    $this->get(IncomeForecastPage::getUrl())->assertOk()->assertSee('Prognozowany dochód');

    Livewire::test(IncomeForecastPage::class, ['period' => '2026-11'])
        ->assertSet('data.sub_rate', '0,00')
        ->set('data.sub_hours', 10)
        ->set('data.sub_rate', '1 000,50')
        ->assertSee('12'."\u{00A0}".'306,15');

    $saved = IncomeForecast::where('period', '2026-11')->first()->data;
    expect($saved['sub_hours'])->toEqual(10)->and($saved['sub_rate'])->toEqual(1000.5);
});

it('podpowiada podstawę Kruka z poprzedniego miesiąca, gdy faktury za miesiąc nie ma lub jest poniżej 5 tys. zł', function () {
    forecastDocument('revenue', '2026-10-05', [
        ['name' => 'Wynagrodzenie za październik', 'net' => 19000, 'vat' => 4370, 'gross' => 23370],
        ['name' => 'Premia', 'net' => 1000, 'vat' => 230, 'gross' => 1230],
    ], ['contractor_id' => $this->kruk->id]);

    // Brak faktury w listopadzie: podstawa z faktury październikowej, bez dodatków.
    expect($this->service->defaults('2026-11'))
        ->kruk_base->toBe(19000.0)
        ->kruk_additions->toBe(0.0);

    // Faktura do 5 000 zł nie liczy się jako wystawiona.
    forecastDocument('revenue', '2026-11-05', [['name' => 'Wynagrodzenie', 'net' => 4000, 'vat' => 920, 'gross' => 4920]], ['contractor_id' => $this->kruk->id]);
    expect($this->service->defaults('2026-11')['kruk_base'])->toBe(19000.0);

    // Bez faktury w poprzednim miesiącu: podstawa z modelu zapisanego dla poprzedniego miesiąca.
    $this->service->save('2026-11', ['kruk_base' => '21 500,00']);
    expect($this->service->defaults('2026-12')['kruk_base'])->toBe(21500.0);
});
