<?php

namespace App\Filament\Resources\ManualTemplates\Pages;

use App\Filament\Resources\ManualTemplates\ManualTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageManualTemplates extends ManageRecords
{
    protected static string $resource = ManualTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
