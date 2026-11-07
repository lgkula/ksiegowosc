<?php

namespace App\Filament\Resources\HealthMinimums\Pages;

use App\Filament\Resources\HealthMinimums\HealthMinimumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHealthMinimums extends ManageRecords
{
    protected static string $resource = HealthMinimumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
