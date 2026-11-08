<?php

namespace App\Filament\Resources\DocumentItems;

use App\Enums\Direction;
use App\Filament\Resources\DocumentItems\Pages\ListDocumentItems;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\PeriodFilter;
use App\Models\Category;
use App\Models\DocumentItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Widok szczegółowy: wszystkie pozycje faktur z możliwością zmiany kategorii w tabeli. */
class DocumentItemResource extends Resource
{
    protected static ?string $model = DocumentItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Dokumenty';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'pozycja';

    protected static ?string $pluralModelLabel = 'pozycje faktur';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['document.contractor.group'])
                ->join('documents', 'documents.id', '=', 'document_items.document_id')
                ->select('document_items.*'))
            ->defaultSort('documents.issue_date', 'desc')
            // Nazwa pozycji wyświetlana jest jako druga linia wiersza na całą szerokość (CSS: .ks-items-table).
            ->extraAttributes(['class' => 'ks-items-table'])
            ->columns([
                TextColumn::make('document.issue_date')->label('Data')->date('d.m.Y')->sortable(query: fn (Builder $q, string $direction) => $q->orderBy('documents.issue_date', $direction)),
                TextColumn::make('document.period')->label('Miesiąc')->toggleable(),
                TextColumn::make('document.number')->label('Numer')->searchable()->toggleable(),
                TextColumn::make('document.contractor.short_name')
                    ->label('Kontrahent')
                    ->tooltip(fn (DocumentItem $record) => $record->document->contractor?->group?->name)
                    ->width('16rem')
                    ->searchable(),
                TextColumn::make('qty')->label('Ilość')->numeric(fn ($state) => fmod((float) $state, 1) == 0 ? 0 : 2)->alignEnd()->toggleable(),
                TextColumn::make('net')->label('Netto')->money('PLN')->alignEnd()->sortable()->summarize(Sum::make()->label('')->money('PLN')),
                TextColumn::make('vat')->label('VAT')->money('PLN')->alignEnd()->summarize(Sum::make()->label('')->money('PLN')),
                TextColumn::make('gross')->label('Brutto')->money('PLN')->alignEnd()->sortable()->summarize(Sum::make()->label('')->money('PLN')),
                SelectColumn::make('category_id')
                    ->label('Kategoria')
                    ->options(fn () => Category::orderBy('sort')->pluck('name', 'id'))
                    ->placeholder('bez kategorii')
                    ->afterStateUpdated(fn (DocumentItem $record) => $record->update(['category_source' => 'manual'])),
                TextColumn::make('category_source')
                    ->label('Ustawiona')
                    ->formatStateUsing(fn (string $state) => $state === 'manual' ? 'ręcznie' : 'auto')
                    ->badge()
                    ->color(fn (string $state) => $state === 'manual' ? 'info' : 'gray')
                    ->toggleable(),
                TextColumn::make('name')->label('Pozycja')->searchable()->tooltip(fn (DocumentItem $record) => $record->name),
            ])
            ->filters([
                PeriodFilter::make('document'),
                SelectFilter::make('direction')
                    ->label('Rodzaj')
                    ->options(Direction::class)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->where('documents.direction', $data['value']) : $query),
                SelectFilter::make('category_id')->label('Kategoria')->relationship('category', 'name')->multiple(),
                TernaryFilter::make('uncategorized')
                    ->label('Bez kategorii')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('category_id'),
                        false: fn (Builder $q) => $q->whereNotNull('category_id'),
                    ),
                TernaryFilter::make('included')
                    ->label('Ujęcie dokumentu')
                    ->placeholder('wszystkie')
                    ->trueLabel('ujęte')
                    ->falseLabel('pominięte')
                    ->queries(
                        true: fn (Builder $q) => $q->where('documents.included', true),
                        false: fn (Builder $q) => $q->where('documents.included', false),
                    ),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('openDocument')
                    ->label('Dokument')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (DocumentItem $record) => DocumentResource::getUrl('edit', ['record' => $record->document_id])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentItems::route('/'),
        ];
    }
}
