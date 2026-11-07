<?php

use App\Filament\Pages\KsefSettingsPage;
use App\Filament\Pages\MonthlySettlementPage;
use App\Filament\Pages\YearSummaryPage;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\CategoryRules\CategoryRuleResource;
use App\Filament\Resources\ContractorGroups\ContractorGroupResource;
use App\Filament\Resources\Contractors\ContractorResource;
use App\Filament\Resources\DocumentItems\DocumentItemResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\HealthMinimums\HealthMinimumResource;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\ManualTemplates\ManualTemplateResource;
use App\Filament\Resources\TaxParameters\TaxParameterResource;
use App\Models\Category;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\ManualTemplate;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TaxParameterSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([TaxParameterSeeder::class, CategorySeeder::class]);
    $this->actingAs(User::factory()->create());

    $kokot = Contractor::create(['full_name' => 'Joanna Kokot', 'own_settlement_section' => true]);
    $doc = Document::create(['direction' => 'cost', 'source' => 'ksef', 'number' => 'FV/1', 'issue_date' => now(), 'contractor_id' => $kokot->id, 'included' => true]);
    $doc->items()->create(['name' => 'Usługa', 'net' => 1000, 'vat' => 230, 'gross' => 1230]);
    Document::create(['direction' => 'cost', 'source' => 'ksef', 'number' => 'FV/2', 'issue_date' => now(), 'included' => null])
        ->items()->create(['name' => 'Olej napędowy', 'net' => 100, 'vat' => 23, 'gross' => 123]);
});

it('renderuje wszystkie strony panelu', function (string $url) {
    $this->get($url)->assertOk();
})->with([
    'pulpit' => fn () => '/',
    'dokumenty' => fn () => DocumentResource::getUrl('index'),
    'nowy dokument' => fn () => DocumentResource::getUrl('create'),
    'edycja dokumentu' => fn () => DocumentResource::getUrl('edit', ['record' => Document::first()]),
    'pozycje' => fn () => DocumentItemResource::getUrl('index'),
    'rozliczenie' => fn () => MonthlySettlementPage::getUrl(),
    'zestawienie' => fn () => YearSummaryPage::getUrl(),
    'kontrahenci' => fn () => ContractorResource::getUrl('index'),
    'grupy' => fn () => ContractorGroupResource::getUrl('index'),
    'kategorie' => fn () => CategoryResource::getUrl('index'),
    'reguły' => fn () => CategoryRuleResource::getUrl('index'),
    'szablony' => fn () => ManualTemplateResource::getUrl('index'),
    'kredyty' => fn () => LoanResource::getUrl('index'),
    'parametry' => fn () => TaxParameterResource::getUrl('index'),
    'ksef' => fn () => KsefSettingsPage::getUrl(),
    'minimalna zdrowotna' => fn () => HealthMinimumResource::getUrl('index'),
]);

it('zapisuje ustawienia KSeF z zaszyfrowanym tokenem', function () {
    Livewire::test(KsefSettingsPage::class)
        ->fillForm(['ksef_environment' => 'test', 'ksef_nip' => '8990001122', 'ksef_token' => 'SEKRET'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('ksef_token'))->toBe('SEKRET')
        ->and(Setting::where('key', 'ksef_token')->value('value'))->not->toBe('SEKRET');

    // Puste pole tokenu nie kasuje zapisanego tokenu.
    Livewire::test(KsefSettingsPage::class)
        ->fillForm(['ksef_environment' => 'prod', 'ksef_nip' => '8990001122', 'ksef_token' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('ksef_token'))->toBe('SEKRET')
        ->and(Setting::get('ksef_environment'))->toBe('prod');
});

it('pokazuje sekcję Joanny Kokot w rozliczeniu miesiąca', function () {
    $this->get(MonthlySettlementPage::getUrl())
        ->assertSee('Koszty: Joanna Kokot')
        ->assertSee('Pozostałe dokumenty kosztowe')
        ->assertSee('czekają na decyzję');
});

it('dodaje fakturę paliwową z szablonu', function () {
    $template = ManualTemplate::where('name', 'Paliwo – Moya')->firstOrFail();

    Livewire::test(ListDocuments::class)
        ->callAction('fromTemplate', data: [
            'template_id' => $template->id,
            'issue_date' => '2026-11-05',
            'number' => 'MOYA/123',
            'qty' => 40,
            'gross' => 246,
            'period' => '2026-11',
        ])
        ->assertHasNoActionErrors();

    $document = Document::where('number', 'MOYA/123')->with('items')->firstOrFail();

    expect((float) $document->net)->toBe(200.0)
        ->and((float) $document->vat)->toBe(46.0)
        ->and($document->items->first()->category_id)->toBe(Category::where('name', 'Paliwo')->value('id'))
        ->and($document->contractor->group->name)->toBe('Moya');
});

it('tworzy dokument ręcznie i kategoryzuje pozycję automatycznie', function () {
    $contractor = Contractor::create(['full_name' => 'Hotel Pod Lipami']);

    Livewire::test(CreateDocument::class)
        ->fillForm([
            'direction' => 'cost',
            'kind' => 'invoice',
            'number' => 'H/7',
            'contractor_id' => $contractor->id,
            'issue_date' => '2026-10-10',
            'period' => '2026-10',
            'included' => '1',
            'items' => [
                ['name' => 'Nocleg 2 doby', 'qty' => 2, 'vat_rate' => '8', 'net' => 400, 'vat' => 32, 'gross' => 432, 'category_source' => 'auto'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $document = Document::where('number', 'H/7')->with('items')->firstOrFail();

    expect((float) $document->gross)->toBe(432.0)
        ->and($document->included)->toBeTrue()
        ->and($document->items->first()->category_id)->toBe(Category::where('name', 'Noclegi')->value('id'));
});

it('oznacza dokumenty do decyzji akcją masową', function () {
    $pending = Document::pending()->get();

    Livewire::test(ListDocuments::class)
        ->callTableBulkAction('include', $pending);

    expect(Document::pending()->count())->toBe(0);
});
