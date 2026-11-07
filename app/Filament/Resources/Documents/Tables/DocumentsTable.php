<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\Direction;
use App\Enums\DocumentSource;
use App\Filament\Support\PeriodFilter;
use App\Models\Category;
use App\Models\Document;
use App\Services\Categorizer;
use App\Services\DocumentProcessor;
use App\Support\Money;
use App\Support\Period;
use Filament\Forms\Components\TextInput;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['contractor.group', 'items.category']))
            ->columns([
                TextColumn::make('issue_date')->label('Data')->date('d.m.Y')->sortable(),
                TextColumn::make('period')->label('Miesiąc')->sortable()->toggleable(),
                TextColumn::make('number')->label('Numer')->searchable()->toggleable(),
                TextColumn::make('contractor.short_name')
                    ->label('Kontrahent')
                    ->description(fn (Document $record) => $record->contractor?->group?->name)
                    ->searchable(['short_name', 'full_name', 'nip'])
                    ->placeholder('—'),
                TextColumn::make('categories')
                    ->label('Kategorie')
                    ->state(fn (Document $record) => $record->items->map(fn ($i) => $i->category?->name ?? 'bez kategorii')->unique()->values()->all())
                    ->badge()
                    ->color(fn (string $state) => $state === 'bez kategorii' ? 'danger' : 'gray'),
                TextColumn::make('direction')->label('Rodzaj')->badge()->color(fn (Direction $state) => $state === Direction::Revenue ? 'success' : 'info'),
                TextColumn::make('source')->label('Źródło')->badge()->color('gray')->toggleable(),
                TextColumn::make('net')->label('Netto')->money('PLN')->sortable()->summarize(Sum::make()->label('')->money('PLN')),
                TextColumn::make('vat')->label('VAT')->money('PLN')->summarize(Sum::make()->label('')->money('PLN')),
                TextColumn::make('gross')
                    ->label('Brutto')
                    ->money('PLN')
                    ->description(fn (Document $record) => $record->isForeignCurrency()
                        ? Money::format($record->gross_currency, symbol: $record->currency).' × '.Money::input($record->exchange_rate, 4)
                        : null)
                    ->sortable()
                    ->summarize(Sum::make()->label('')->money('PLN')),
                IconColumn::make('included')
                    ->label('W rozliczeniu')
                    ->state(fn (Document $record) => match ($record->included) {
                        true => 'yes', false => 'no', default => 'pending'
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'yes' => Heroicon::OutlinedCheckCircle,
                        'no' => Heroicon::OutlinedMinusCircle,
                        default => Heroicon::OutlinedQuestionMarkCircle,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'yes' => 'success', 'no' => 'gray', default => 'warning'
                    }),
            ])
            ->filters([
                PeriodFilter::make(),
                SelectFilter::make('direction')->label('Rodzaj')->options(Direction::class),
                SelectFilter::make('source')->label('Źródło')->options(DocumentSource::class),
                TernaryFilter::make('included')
                    ->label('Ujęcie')
                    ->placeholder('wszystkie')
                    ->trueLabel('ujęte')
                    ->falseLabel('pominięte'),
                SelectFilter::make('category')
                    ->label('Kategoria')
                    ->options(fn () => Category::orderBy('sort')->pluck('name', 'id'))
                    ->multiple()
                    ->query(fn (Builder $query, array $data) => filled($data['values'] ?? null)
                        ? $query->whereHas('items', fn (Builder $q) => $q->whereIn('category_id', $data['values']))
                        : $query),
                SelectFilter::make('group')
                    ->label('Grupa kontrahenta')
                    ->relationship('contractor.group', 'name')
                    ->multiple(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('include')
                        ->label('Ujmij w rozliczeniu')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->action(fn (Collection $records) => self::setIncluded($records, true))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('exclude')
                        ->label('Pomiń w rozliczeniu')
                        ->icon(Heroicon::OutlinedMinusCircle)
                        ->action(fn (Collection $records) => self::setIncluded($records, false))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('setCategory')
                        ->label('Zmień kategorię pozycji')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            Select::make('category_id')->label('Kategoria')->options(Category::orderBy('sort')->pluck('name', 'id'))->searchable()->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->items()->update(['category_id' => $data['category_id'], 'category_source' => 'manual']);
                            }
                            Notification::make()->title('Zmieniono kategorię')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('recategorize')
                        ->label('Przelicz kategorie automatycznie')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->requiresConfirmation()
                        ->modalDescription('Ręcznie ustawione kategorie pozostaną bez zmian.')
                        ->action(function (Collection $records) {
                            $categorizer = app(Categorizer::class);
                            $changed = $records->sum(fn (Document $document) => $categorizer->categorizeDocument($document));
                            Notification::make()->title("Zmienione pozycje: {$changed}")->success()->send();
                        }),
                    BulkAction::make('copyToPeriod')
                        ->label('Kopiuj do miesiąca')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->modalDescription('Kopiuje zaznaczone dokumenty wpisane ręcznie (dokumenty z KSeF są pomijane). Daty przesuwają się do wskazanego miesiąca, numer zostaje pusty, a dokument w walucie dostaje kurs NBP dla nowej daty.')
                        ->schema([
                            TextInput::make('period')
                                ->label('Do miesiąca')
                                ->placeholder('RRRR-MM')
                                ->default(fn () => Period::current())
                                ->rules(['regex:/^\d{4}-(0[1-9]|1[0-2])$/'])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->load(['contractor', 'items']);
                            self::notifyCopyResult(app(DocumentProcessor::class)->copyToPeriod($records, $data['period']), $data['period']);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @param  array{copied: list<Document>, skipped: list<string>, warnings: list<string>}  $result */
    public static function notifyCopyResult(array $result, string $period): void
    {
        $lines = [
            ...array_map(fn (string $s) => "Pominięto: {$s}", $result['skipped']),
            ...$result['warnings'],
        ];

        $notification = Notification::make()
            ->title('Skopiowano dokumentów do '.Period::label($period).': '.count($result['copied']))
            ->body($lines ? implode("\n", $lines) : 'Uzupełnij numery faktur i sprawdź kwoty w skopiowanych dokumentach.');

        ($lines ? $notification->warning()->persistent() : $notification->success())->send();
    }

    private static function setIncluded(Collection $records, bool $included): void
    {
        Document::whereKey($records->modelKeys())->update(['included' => $included]);
        Notification::make()->title(($included ? 'Ujęto' : 'Pominięto').' dokumentów: '.$records->count())->success()->send();
    }
}
