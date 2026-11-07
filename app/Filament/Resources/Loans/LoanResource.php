<?php

namespace App\Filament\Resources\Loans;

use App\Filament\Resources\Loans\Pages\ManageLoans;
use App\Models\Loan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Ustawienia';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'kredyt';

    protected static ?string $pluralModelLabel = 'kredyty i stałe płatności';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $period = ['regex:/^\d{4}-(0[1-9]|1[0-2])$/'];

        return $schema
            ->components([
                TextInput::make('name')->label('Nazwa')->required(),
                TextInput::make('installment')->label('Rata miesięczna')->numeric()->suffix('zł')->required(),
                TextInput::make('from_period')->label('Od miesiąca')->placeholder('2026-01')->rules($period)->required(),
                TextInput::make('to_period')->label('Do miesiąca (włącznie)')->placeholder('puste = bezterminowo')->rules($period),
                TextInput::make('due_day')->label('Dzień płatności')->numeric()->minValue(1)->maxValue(31),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nazwa')->searchable(),
                TextColumn::make('installment')->label('Rata')->money('PLN')->sortable(),
                TextColumn::make('from_period')->label('Od'),
                TextColumn::make('to_period')->label('Do')->placeholder('bezterminowo'),
                TextColumn::make('due_day')->label('Dzień płatności'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLoans::route('/'),
        ];
    }
}
