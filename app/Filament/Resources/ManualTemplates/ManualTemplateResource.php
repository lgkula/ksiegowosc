<?php

namespace App\Filament\Resources\ManualTemplates;

use App\Enums\Direction;
use App\Filament\Resources\ManualTemplates\Pages\ManageManualTemplates;
use App\Models\ManualTemplate;
use App\Support\VatRates;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ManualTemplateResource extends Resource
{
    protected static ?string $model = ManualTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Słowniki';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'szablon dokumentu';

    protected static ?string $pluralModelLabel = 'szablony dokumentów';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nazwa szablonu')->required()->placeholder('Paliwo – Moya'),
                Select::make('direction')->label('Kierunek')->options(Direction::class)->default(Direction::Cost)->required(),
                Select::make('contractor_id')->label('Kontrahent')->relationship('contractor', 'full_name')->searchable()->preload(),
                TextInput::make('item_name')->label('Nazwa pozycji')->required()->placeholder('Olej napędowy ON'),
                Select::make('category_id')->label('Kategoria')->relationship('category', 'name')->searchable()->preload(),
                Select::make('vat_rate')->label('Stawka VAT')->options(VatRates::options())->default('23')->required(),
                TextInput::make('default_unit')->label('Jednostka')->placeholder('l'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                TextColumn::make('contractor.full_name')->label('Kontrahent'),
                TextColumn::make('item_name')->label('Pozycja'),
                TextColumn::make('category.name')->label('Kategoria'),
                TextColumn::make('vat_rate')->label('VAT'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageManualTemplates::route('/'),
        ];
    }
}
