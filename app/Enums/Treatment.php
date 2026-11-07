<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Treatment: string implements HasLabel
{
    case Cost = 'cost';
    case IncomeDeduction = 'income_deduction';
    case Health = 'health';
    case None = 'none';
    case Revenue = 'revenue';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cost => 'Koszt podatkowy',
            self::IncomeDeduction => 'Odliczenie od dochodu (ZUS społeczne)',
            self::Health => 'Składka zdrowotna',
            self::None => 'Poza rozliczeniem',
            self::Revenue => 'Przychód',
        };
    }
}
