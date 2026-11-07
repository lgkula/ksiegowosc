<?php

namespace App\Filament\Resources\HealthMinimums;

use App\Filament\Resources\HealthMinimums\Pages\ManageHealthMinimums;
use App\Models\HealthMinimum;
use App\Models\TaxParameter;
use App\Support\Money;
use App\Support\Period;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;
use UnitEnum;

class HealthMinimumResource extends Resource
{
    protected static ?string $model = HealthMinimum::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Ustawienia';

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'Minimalna zdrowotna';

    protected static ?string $modelLabel = 'minimalna podstawa zdrowotnej';

    protected static ?string $pluralModelLabel = 'minimalne podstawy składki zdrowotnej';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('valid_from')
                    ->label('Obowiązuje od miesiąca')
                    ->placeholder('2026-02')
                    ->helperText('Obowiązuje do miesiąca poprzedzającego kolejny wpis.')
                    ->rules(['regex:/^\d{4}-(0[1-9]|1[0-2])$/'])
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('base')
                    ->label('Minimalna podstawa')
                    ->numeric()
                    ->suffix('zł')
                    ->helperText('Np. minimalne wynagrodzenie albo jego 75%. Składka minimalna = podstawa × stawka zdrowotnej.')
                    ->required(),
                TextInput::make('note')->label('Opis')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('valid_from', 'desc')
            ->columns([
                TextColumn::make('valid_from')
                    ->label('Od miesiąca')
                    ->formatStateUsing(fn (string $state) => ucfirst(Period::label($state)))
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->label('Do miesiąca')
                    ->state(function (HealthMinimum $record) {
                        $next = HealthMinimum::where('valid_from', '>', $record->valid_from)->orderBy('valid_from')->value('valid_from');

                        return $next ? ucfirst(Period::label(Period::previous($next))) : 'bezterminowo';
                    }),
                TextColumn::make('base')->label('Podstawa')->money('PLN'),
                TextColumn::make('minimum')
                    ->label('Składka minimalna')
                    ->state(function (HealthMinimum $record) {
                        try {
                            $rate = (float) TaxParameter::forYear(Period::year($record->valid_from))->health_rate;
                        } catch (Throwable) {
                            return '—';
                        }

                        return Money::format(round((float) $record->base * $rate / 100, 2));
                    }),
                TextColumn::make('note')->label('Opis')->wrap(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHealthMinimums::route('/'),
        ];
    }
}
