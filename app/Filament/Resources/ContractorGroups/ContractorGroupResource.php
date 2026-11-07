<?php

namespace App\Filament\Resources\ContractorGroups;

use App\Filament\Resources\ContractorGroups\Pages\ManageContractorGroups;
use App\Models\Contractor;
use App\Models\ContractorGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ContractorGroupResource extends Resource
{
    protected static ?string $model = ContractorGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Słowniki';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'grupa kontrahentów';

    protected static ?string $pluralModelLabel = 'grupy kontrahentów';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nazwa grupowa')->required()->unique(ignoreRecord: true),
                TextInput::make('pattern')
                    ->label('Fragmenty nazwy')
                    ->helperText('Rozdzielone średnikiem, np. "orlen;pkn orlen". Nowi kontrahenci z takim fragmentem w nazwie trafią do grupy automatycznie.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nazwa grupowa')->searchable()->sortable(),
                TextColumn::make('pattern')->label('Fragmenty nazwy'),
                TextColumn::make('contractors_count')->label('Kontrahenci')->counts('contractors'),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label('Przypisz pasujących')
                    ->icon(Heroicon::OutlinedLink)
                    ->requiresConfirmation()
                    ->modalDescription('Przypisze do tej grupy kontrahentów bez grupy, których nazwa zawiera któryś z fragmentów.')
                    ->action(function (ContractorGroup $record) {
                        $count = 0;
                        Contractor::whereNull('group_id')->each(function (Contractor $contractor) use ($record, &$count) {
                            if ($record->matches($contractor->full_name)) {
                                $contractor->update(['group_id' => $record->id]);
                                $count++;
                            }
                        });
                        Notification::make()->title("Przypisano kontrahentów: {$count}")->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContractorGroups::route('/'),
        ];
    }
}
