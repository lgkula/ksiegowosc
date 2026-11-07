<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Direction: string implements HasLabel
{
    case Cost = 'cost';
    case Revenue = 'revenue';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cost => 'Koszt',
            self::Revenue => 'Przychód',
        };
    }
}
