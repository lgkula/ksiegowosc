<?php

namespace App\Filament\Resources\ContractorGroups\Pages;

use App\Filament\Resources\ContractorGroups\ContractorGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContractorGroups extends ManageRecords
{
    protected static string $resource = ContractorGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
