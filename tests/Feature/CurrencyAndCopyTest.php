<?php

use App\Filament\Pages\MonthlySettlementPage;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\MonthlySettlement;
use App\Models\User;
use App\Services\DocumentProcessor;
use App\Services\Nbp\NbpException;
use App\Services\Nbp\NbpRates;
use App\Support\Money;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TaxParameterSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([TaxParameterSeeder::class, CategorySeeder::class]);
    $this->actingAs(User::factory()->create());
    $this->travelTo('2026-11-07 12:00');

    // Odpowiedź NBP: kursy z zakresu dat; ostatni to kurs z dnia roboczego przed wystawieniem.
    Http::fake([
        'api.nbp.pl/api/exchangerates/rates/a/USD/*' => Http::response(['code' => 'USD', 'rates' => [
            ['no' => '213/A/NBP/2026', 'effectiveDate' => '2026-11-03', 'mid' => 3.6500],
            ['no' => '214/A/NBP/2026', 'effectiveDate' => '2026-11-04', 'mid' => 3.7000],
        ]]),
        'api.nbp.pl/api/exchangerates/rates/a/EUR/*' => Http::response(['code' => 'EUR', 'rates' => [
            ['no' => '190/A/NBP/2026', 'effectiveDate' => '2026-10-02', 'mid' => 4.2500],
        ]]),
        'api.nbp.pl/*' => Http::response('404 NotFound - Not Found - Brak danych', 404),
    ]);
});

it('parsuje i formatuje kwoty z odstępem tysięcy', function () {
    expect(Money::parse('1 234,56'))->toBe(1234.56)
        ->and(Money::parse("12\u{00A0}000"))->toBe(12000.0)
        ->and(Money::parse('99.5'))->toBe(99.5)
        ->and(Money::parse('abc'))->toBeNull()
        ->and(Money::input(1234.5))->toBe('1 234,50')
        ->and(Money::input('3.7123', 4))->toBe('3,7123');
});

it('pobiera kurs średni NBP z dnia roboczego przed datą wystawienia', function () {
    $rate = app(NbpRates::class)->rateBefore('USD', '2026-11-05');

    expect($rate)->toBe(['rate' => 3.7, 'date' => '2026-11-04', 'table' => '214/A/NBP/2026']);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/rates/a/USD/2026-10-21/2026-11-04/'));
});

it('zgłasza brak kursu NBP', function () {
    app(NbpRates::class)->rateBefore('CHF', '2026-11-05');
})->throws(NbpException::class);

it('przelicza ręczną fakturę w USD na PLN kursem NBP', function () {
    $anthropic = Contractor::create(['full_name' => 'Anthropic, PBC']);

    Livewire::test(CreateDocument::class)
        ->fillForm([
            'direction' => 'cost',
            'kind' => 'invoice',
            'number' => 'ANT-1',
            'contractor_id' => $anthropic->id,
            'issue_date' => '2026-11-05',
            'period' => '2026-11',
            'included' => '1',
        ])
        ->fillForm(['currency' => 'USD'])
        ->assertFormSet(['exchange_rate' => '3,7000', 'exchange_rate_table' => '214/A/NBP/2026'])
        ->fillForm([
            'items' => [
                ['name' => 'Claude Max', 'qty' => 1, 'vat_rate' => 'np', 'net_currency' => '1 100,00', 'vat_currency' => '0', 'gross_currency' => '1 100,00', 'category_source' => 'auto'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $document = Document::where('number', 'ANT-1')->with('items')->firstOrFail();

    expect($document->currency)->toBe('USD')
        ->and((float) $document->exchange_rate)->toBe(3.7)
        ->and($document->exchange_rate_date->format('Y-m-d'))->toBe('2026-11-04')
        ->and((float) $document->gross_currency)->toBe(1100.0)
        ->and((float) $document->net)->toBe(4070.0)
        ->and((float) $document->gross)->toBe(4070.0)
        ->and((float) $document->items->first()->net)->toBe(4070.0);

    // Edycja otwiera się z kwotami w walucie.
    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->assertFormSet(['currency' => 'USD', 'exchange_rate' => '3,7000'])
        ->assertOk();
});

it('kopiuje koszty ręczne do kolejnego miesiąca z nowym kursem', function () {
    $oirp = Document::create(['direction' => 'cost', 'source' => 'manual', 'number' => 'OIRP/10', 'issue_date' => '2026-10-31', 'period' => '2026-10', 'included' => true]);
    $oirp->items()->create(['name' => 'Składka OIRP', 'net' => 160, 'vat_rate' => 'zw', 'vat' => 0, 'gross' => 160]);

    $openai = Document::create(['direction' => 'cost', 'source' => 'manual', 'issue_date' => '2026-10-05', 'period' => '2026-10', 'included' => true,
        'currency' => 'EUR', 'exchange_rate' => 4.3, 'exchange_rate_date' => '2026-10-03']);
    $openai->items()->create(['name' => 'ChatGPT Plus', 'vat_rate' => 'np', 'net_currency' => 20, 'vat_currency' => 0, 'gross_currency' => 20]);
    app(DocumentProcessor::class)->afterSave($openai);
    expect((float) $openai->fresh()->gross)->toBe(86.0);

    $ksef = Document::create(['direction' => 'cost', 'source' => 'ksef', 'issue_date' => '2026-10-10', 'period' => '2026-10', 'included' => true]);

    Livewire::test(ListDocuments::class)
        ->mountAction('copyCosts')
        ->assertActionDataSet(['from' => '2026-10', 'to' => '2026-11'])
        ->assertActionDataSet(fn (array $data) => collect($data['documents'])->sort()->values()->all() === [$oirp->id, $openai->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $copies = Document::where('period', '2026-11')->with('items')->orderBy('issue_date')->get();

    expect($copies)->toHaveCount(2)
        ->and($copies->pluck('copied_from_id')->all())->toBe([$openai->id, $oirp->id])
        ->and($copies[1]->issue_date->format('Y-m-d'))->toBe('2026-11-30')
        ->and($copies[1]->number)->toBeNull()
        ->and((float) $copies[1]->gross)->toBe(160.0)
        ->and($copies[0]->issue_date->format('Y-m-d'))->toBe('2026-11-05')
        // Nowy kurs dla nowej daty: EUR z fałszywej odpowiedzi NBP to 4,25.
        ->and((float) $copies[0]->exchange_rate)->toBe(4.25)
        ->and((float) $copies[0]->gross_currency)->toBe(20.0)
        ->and((float) $copies[0]->gross)->toBe(85.0);

    // Ponowne kopiowanie pomija dokumenty już skopiowane.
    $again = app(DocumentProcessor::class)->copyToPeriod(Document::whereKey([$oirp->id, $ksef->id])->with(['items', 'contractor'])->get(), '2026-11');
    expect($again['copied'])->toBeEmpty()
        ->and($again['skipped'])->toHaveCount(2)
        ->and(Document::where('period', '2026-11')->count())->toBe(2);
});

it('kopiuje zaznaczone dokumenty akcją masową', function () {
    $doc = Document::create(['direction' => 'cost', 'source' => 'manual', 'issue_date' => '2026-09-15', 'period' => '2026-09', 'included' => true]);
    $doc->items()->create(['name' => 'Hosting', 'net' => 50, 'vat' => 11.5, 'gross' => 61.5]);

    Livewire::test(ListDocuments::class)
        ->callTableBulkAction('copyToPeriod', [$doc], data: ['period' => '2026-12'])
        ->assertHasNoTableBulkActionErrors();

    $copy = Document::where('copied_from_id', $doc->id)->firstOrFail();
    expect($copy->period)->toBe('2026-12')
        ->and($copy->issue_date->format('Y-m-d'))->toBe('2026-12-15')
        ->and((float) $copy->gross)->toBe(61.5);
});

it('zapisuje kwoty wpisane z odstępem tysięcy w danych miesiąca', function () {
    Livewire::test(MonthlySettlementPage::class, ['period' => '2026-11'])
        ->callAction('editMonth', data: ['other_income' => '12 500,50', 'account_balance' => '', 'extra_reserve' => '1 000'])
        ->assertHasNoActionErrors();

    $settlement = MonthlySettlement::forPeriod('2026-11');
    expect((float) $settlement->other_income)->toBe(12500.5)
        ->and($settlement->account_balance)->toBeNull()
        ->and((float) $settlement->extra_reserve)->toBe(1000.0);
});
