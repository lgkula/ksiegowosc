<?php

namespace App\Filament\Resources\TaxParameters\Pages;

use App\Filament\Resources\TaxParameters\TaxParameterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxParameters extends ListRecords
{
    protected static string $resource = TaxParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
