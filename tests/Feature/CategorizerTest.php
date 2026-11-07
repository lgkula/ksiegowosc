<?php

use App\Models\Category;
use App\Models\Contractor;
use App\Models\ContractorGroup;
use App\Models\Document;
use App\Services\Categorizer;
use Database\Seeders\CategorySeeder;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
});

function categoryId(string $name): int
{
    return Category::where('name', $name)->value('id');
}

function invoiceFrom(Contractor $contractor, array $items): Document
{
    $document = Document::create([
        'direction' => 'cost', 'source' => 'ksef', 'number' => 'X/1', 'issue_date' => '2026-11-03',
        'contractor_id' => $contractor->id,
    ]);
    foreach ($items as $i => $name) {
        $document->items()->create(['position' => $i + 1, 'name' => $name, 'net' => 10, 'vat' => 2.3, 'gross' => 12.3]);
    }

    return $document->fresh('items');
}

it('przypisuje grupę i nazwę skróconą nowemu kontrahentowi', function () {
    $contractor = Contractor::create(['nip' => '774-00-01-454', 'full_name' => 'ORLEN Spółka Akcyjna']);

    expect($contractor->nip)->toBe('7740001454')
        ->and($contractor->group->name)->toBe('Orlen')
        ->and($contractor->short_name)->toBe('ORLEN');
});

it('kategoryzuje po nazwie towaru przed nazwą sprzedawcy', function () {
    $orlen = Contractor::create(['full_name' => 'ORLEN S.A.']);
    $document = invoiceFrom($orlen, ['Olej napędowy ON EFECTA', 'Kawa duża', 'Płyn do spryskiwaczy zimowy 5L', 'Bilet parkingowy']);

    app(Categorizer::class)->categorizeDocument($document);

    expect($document->items->pluck('category_id')->all())->toBe([
        categoryId('Paliwo'),
        categoryId('Gastronomia'),
        categoryId('Samochód – eksploatacja'),
        categoryId('Paliwo'), // reguła sprzedawcy
    ]);
});

it('używa domyślnej kategorii kontrahenta i nie nadpisuje ręcznych zmian', function () {
    $kokot = Contractor::create(['full_name' => 'Joanna Kokot', 'default_category_id' => categoryId('Usługi obce')]);
    $document = invoiceFrom($kokot, ['Usługa za październik', 'Druga pozycja']);
    $document->items[1]->update(['category_id' => categoryId('Inne koszty'), 'category_source' => 'manual']);

    app(Categorizer::class)->categorizeDocument($document->fresh('items'));

    expect($document->fresh('items')->items->pluck('category_id')->all())->toBe([
        categoryId('Usługi obce'),
        categoryId('Inne koszty'),
    ]);
});

it('nie przypisuje BP do firm z "bp" w środku nazwy', function () {
    $contractor = Contractor::create(['full_name' => 'Hurtownia BPH Sp. z o.o.']);

    expect($contractor->group_id)->toBeNull()
        ->and(ContractorGroup::guessFor('BP Europa SE Oddział w Polsce')?->name)->toBe('BP');
});
