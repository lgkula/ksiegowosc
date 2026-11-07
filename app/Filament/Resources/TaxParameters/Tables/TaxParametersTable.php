<?php

namespace App\Filament\Resources\TaxParameters\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxParametersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('year')->label('Rok')->sortable(),
                TextColumn::make('pit_threshold')->label('Próg PIT')->money('PLN'),
                TextColumn::make('zus_base')->label('Podstawa ZUS')->money('PLN'),
                TextColumn::make('health_rate')->label('Stawka zdrowotnej')->suffix('%'),
            ])
            ->recordActions([
                ReplicateAction::make()
                    ->label('Kopiuj na kolejny rok')
                    ->beforeReplicaSaved(function ($replica) {
                        $replica->year = $replica->year + 1;
                    }),
                EditAction::make(),
            ]);
    }
}
