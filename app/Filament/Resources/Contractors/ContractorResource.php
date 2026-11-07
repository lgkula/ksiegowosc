<?php

namespace App\Filament\Resources\Contractors;

use App\Filament\Resources\Contractors\Pages\CreateContractor;
use App\Filament\Resources\Contractors\Pages\EditContractor;
use App\Filament\Resources\Contractors\Pages\ListContractors;
use App\Filament\Resources\Contractors\Schemas\ContractorForm;
use App\Filament\Resources\Contractors\Tables\ContractorsTable;
use App\Models\Contractor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ContractorResource extends Resource
{
    protected static ?string $model = Contractor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Słowniki';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'kontrahent';

    protected static ?string $pluralModelLabel = 'kontrahenci';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['full_name', 'short_name', 'nip'];
    }

    public static function form(Schema $schema): Schema
    {
        return ContractorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractors::route('/'),
            'create' => CreateContractor::route('/create'),
            'edit' => EditContractor::route('/{record}/edit'),
        ];
    }
}
