<?php

namespace App\Filament\Resources\Contractors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dane kontrahenta')
                    ->columns(2)
                    ->schema([
                        TextInput::make('full_name')->label('Pełna nazwa')->required()->columnSpanFull(),
                        TextInput::make('nip')->label('NIP')->maxLength(20)->unique(ignoreRecord: true),
                        TextInput::make('address')->label('Adres'),
                    ]),
                Section::make('Nazwy skrócone i rozliczenie')
                    ->columns(2)
                    ->schema([
                        TextInput::make('short_name')->label('Nazwa skrócona')->helperText('Puste = wygenerowana z pełnej nazwy.'),
                        Select::make('group_id')
                            ->label('Nazwa grupowa')
                            ->relationship('group', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->label('Nazwa grupowa')->required()]),
                        Select::make('default_category_id')
                            ->label('Domyślna kategoria')
                            ->relationship('defaultCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Używana, gdy żadna reguła nie pasuje.'),
                        Toggle::make('own_settlement_section')
                            ->label('Osobna sekcja w rozliczeniu miesiąca')
                            ->helperText('Np. faktura od Joanny Kokot.')
                            ->inline(false),
                    ]),
            ]);
    }
}
