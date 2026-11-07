<?php

namespace App\Filament\Resources\DocumentItems\Pages;

use App\Filament\Resources\DocumentItems\DocumentItemResource;
use Filament\Resources\Pages\ListRecords;

class ListDocumentItems extends ListRecords
{
    protected static string $resource = DocumentItemResource::class;
}
