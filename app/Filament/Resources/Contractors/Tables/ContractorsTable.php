<?php

namespace App\Filament\Resources\Contractors\Tables;

use App\Models\ContractorGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ContractorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('full_name')
            ->columns([
                TextColumn::make('short_name')->label('Nazwa skrócona')->searchable()->sortable(),
                TextColumn::make('group.name')->label('Grupa')->badge()->sortable()->placeholder('—'),
                TextColumn::make('full_name')->label('Pełna nazwa')->searchable()->sortable()->wrap(),
                TextColumn::make('nip')->label('NIP')->searchable(),
                TextColumn::make('defaultCategory.name')->label('Domyślna kategoria')->toggleable(),
                IconColumn::make('own_settlement_section')->label('Osobna sekcja')->boolean()->toggleable(),
                TextColumn::make('documents_count')->label('Dokumenty')->counts('documents')->sortable(),
            ])
            ->filters([
                SelectFilter::make('group_id')->label('Grupa')->relationship('group', 'name'),
                TernaryFilter::make('group_id_null')
                    ->label('Bez grupy')
                    ->queries(
                        true: fn ($query) => $query->whereNull('group_id'),
                        false: fn ($query) => $query->whereNotNull('group_id'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setGroup')
                        ->label('Ustaw grupę')
                        ->icon(Heroicon::OutlinedRectangleGroup)
                        ->schema([
                            Select::make('group_id')->label('Grupa')->options(ContractorGroup::orderBy('name')->pluck('name', 'id'))->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['group_id' => $data['group_id']]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
