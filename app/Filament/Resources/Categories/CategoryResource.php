<?php

namespace App\Filament\Resources\Categories;

use App\Enums\Direction;
use App\Enums\Treatment;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Słowniki';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'kategoria';

    protected static ?string $pluralModelLabel = 'kategorie';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nazwa')->required()->maxLength(255),
                TextInput::make('group')->label('Grupa')->helperText('Np. Samochód, Biuro - do grupowania w raportach.'),
                Select::make('direction')->label('Kierunek')->options(Direction::class)->default(Direction::Cost)->required(),
                Select::make('treatment')->label('Sposób ujęcia')->options(Treatment::class)->default(Treatment::Cost)->required(),
                TextInput::make('pit_percent')->label('% w kosztach PIT')->numeric()->minValue(0)->maxValue(100)->default(100)->suffix('%')->required(),
                TextInput::make('vat_percent')->label('% odliczenia VAT')->numeric()->minValue(0)->maxValue(100)->default(100)->suffix('%')->required(),
                TextInput::make('sort')->label('Kolejność')->numeric()->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                TextColumn::make('group')->label('Grupa')->sortable(),
                TextColumn::make('direction')->label('Kierunek')->badge(),
                TextColumn::make('treatment')->label('Sposób ujęcia')->badge(),
                TextColumn::make('pit_percent')->label('% PIT')->numeric(0)->suffix('%'),
                TextColumn::make('vat_percent')->label('% VAT')->numeric(0)->suffix('%'),
                TextColumn::make('rules_count')->label('Reguły')->counts('rules'),
            ])
            ->filters([
                SelectFilter::make('direction')->label('Kierunek')->options(Direction::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
