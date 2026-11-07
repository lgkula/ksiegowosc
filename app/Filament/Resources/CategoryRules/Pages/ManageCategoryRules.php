<?php

namespace App\Filament\Resources\CategoryRules\Pages;

use App\Filament\Resources\CategoryRules\CategoryRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCategoryRules extends ManageRecords
{
    protected static string $resource = CategoryRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
