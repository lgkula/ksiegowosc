<?php

namespace App\Filament\Resources\TaxParameters\Pages;

use App\Filament\Resources\TaxParameters\TaxParameterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaxParameter extends EditRecord
{
    protected static string $resource = TaxParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
