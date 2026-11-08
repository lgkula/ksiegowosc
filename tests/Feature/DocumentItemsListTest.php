<?php

use App\Filament\Resources\DocumentItems\Pages\ListDocumentItems;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Livewire\Livewire;

it('renderuje listę pozycji faktur z nazwą pozycji w osobnej linii', function () {
    $this->seed(CategorySeeder::class);
    $this->actingAs(User::factory()->create());

    $doc = Document::create(['direction' => 'cost', 'source' => 'manual', 'number' => 'FV/1', 'issue_date' => '2026-10-10', 'period' => '2026-10', 'included' => true]);
    $item = $doc->items()->create(['name' => 'Bardzo długa nazwa pozycji faktury', 'net' => 100, 'vat' => 23, 'gross' => 123]);

    $fuel = $doc->items()->create(['name' => 'Paliwo', 'qty' => 38.56, 'net' => 277.78, 'vat' => 22.22, 'gross' => 300]);

    Livewire::test(ListDocumentItems::class)
        ->assertCanSeeTableRecords([$item, $fuel])
        ->assertTableColumnFormattedStateSet('qty', '1', $item)
        ->assertTableColumnFormattedStateSet('qty', '38,56', $fuel)
        ->assertSee('Bardzo długa nazwa pozycji faktury')
        ->assertSee('FV/1');
});
