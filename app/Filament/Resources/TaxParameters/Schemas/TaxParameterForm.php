<?php

namespace App\Filament\Resources\TaxParameters\Schemas;

use App\Filament\Support\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaxParameterForm
{
    public static function configure(Schema $schema): Schema
    {
        $percent = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric()->suffix('%')->required();
        $money = fn (string $name, string $label) => MoneyInput::make($name)->label($label)->suffix('zł')->required();

        return $schema
            ->components([
                TextInput::make('year')->label('Rok')->numeric()->minValue(2020)->maxValue(2100)->required()->unique(ignoreRecord: true),
                Section::make('PIT – skala podatkowa')
                    ->columns(4)
                    ->schema([
                        $percent('pit_rate_1', 'Stawka I'),
                        $percent('pit_rate_2', 'Stawka II'),
                        $money('pit_threshold', 'Próg'),
                        $money('pit_reducing_amount', 'Kwota zmniejszająca (roczna)'),
                    ]),
                Section::make('ZUS społeczny')
                    ->columns(3)
                    ->schema([
                        $money('zus_base', 'Podstawa wymiaru')->helperText('Pełny ZUS: 60% prognozowanego przeciętnego wynagrodzenia.'),
                        $percent('zus_pension_rate', 'Emerytalna'),
                        $percent('zus_disability_rate', 'Rentowa'),
                        $percent('zus_sickness_rate', 'Chorobowa'),
                        $percent('zus_accident_rate', 'Wypadkowa'),
                        $percent('zus_labour_fund_rate', 'Fundusz Pracy i FS'),
                        Toggle::make('zus_sickness_enabled')->label('Opłacam chorobową')->inline(false),
                    ]),
                Section::make('Składka zdrowotna (skala)')
                    ->description('Minimalna podstawa zmienia się w trakcie roku – ustawia się ją w Ustawienia → Minimalna zdrowotna.')
                    ->columns(2)
                    ->schema([
                        $percent('health_rate', 'Stawka'),
                    ]),
            ]);
    }
}
