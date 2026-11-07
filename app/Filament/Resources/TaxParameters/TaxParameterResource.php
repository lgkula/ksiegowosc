<?php

namespace App\Filament\Resources\TaxParameters;

use App\Filament\Resources\TaxParameters\Pages\CreateTaxParameter;
use App\Filament\Resources\TaxParameters\Pages\EditTaxParameter;
use App\Filament\Resources\TaxParameters\Pages\ListTaxParameters;
use App\Filament\Resources\TaxParameters\Schemas\TaxParameterForm;
use App\Filament\Resources\TaxParameters\Tables\TaxParametersTable;
use App\Models\TaxParameter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TaxParameterResource extends Resource
{
    protected static ?string $model = TaxParameter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Ustawienia';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'parametry roku';

    protected static ?string $pluralModelLabel = 'parametry podatkowe';

    protected static ?string $recordTitleAttribute = 'year';

    public static function form(Schema $schema): Schema
    {
        return TaxParameterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxParametersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxParameters::route('/'),
            'create' => CreateTaxParameter::route('/create'),
            'edit' => EditTaxParameter::route('/{record}/edit'),
        ];
    }
}
