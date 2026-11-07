<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Services\DocumentProcessor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadXml')
                ->label('Pobierz XML')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn () => filled($this->record->xml))
                ->action(fn () => response()->streamDownload(
                    fn () => print ($this->record->xml),
                    ($this->record->ksef_number ?: 'faktura').'.xml',
                    ['Content-Type' => 'application/xml']
                )),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(DocumentProcessor::class)->afterSave($this->record);
    }
}
