<?php

namespace App\Filament\Resources\CategoryRules;

use App\Enums\RuleField;
use App\Enums\RuleMatch;
use App\Filament\Resources\CategoryRules\Pages\ManageCategoryRules;
use App\Models\CategoryRule;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CategoryRuleResource extends Resource
{
    protected static ?string $model = CategoryRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Słowniki';

    protected static ?int $navigationSort = 21;

    protected static ?string $modelLabel = 'reguła kategorii';

    protected static ?string $pluralModelLabel = 'reguły kategorii';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('field')->label('Sprawdzane pole')->options(RuleField::class)->default(RuleField::ItemName)->required(),
                Select::make('match')->label('Dopasowanie')->options(RuleMatch::class)->default(RuleMatch::Contains)->required(),
                TextInput::make('pattern')
                    ->label('Wzorzec')
                    ->required()
                    ->helperText('Wielkość liter nie ma znaczenia. Dla wyrażenia regularnego np. \b(pb ?95|on)\b')
                    ->columnSpanFull(),
                Select::make('category_id')->label('Kategoria')->relationship('category', 'name')->searchable()->preload()->required(),
                TextInput::make('priority')->label('Priorytet')->numeric()->default(0)->helperText('Wyższy sprawdzany wcześniej.'),
                Toggle::make('active')->label('Aktywna')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'desc')
            ->columns([
                TextColumn::make('field')->label('Pole')->badge(),
                TextColumn::make('match')->label('Dopasowanie'),
                TextColumn::make('pattern')->label('Wzorzec')->searchable()->wrap(),
                TextColumn::make('category.name')->label('Kategoria')->sortable(),
                TextColumn::make('priority')->label('Priorytet')->sortable(),
                ToggleColumn::make('active')->label('Aktywna'),
            ])
            ->filters([
                SelectFilter::make('field')->label('Pole')->options(RuleField::class),
                SelectFilter::make('category_id')->label('Kategoria')->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategoryRules::route('/'),
        ];
    }
}
