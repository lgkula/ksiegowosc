<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RuleMatch: string implements HasLabel
{
    case Equals = 'equals';
    case Contains = 'contains';
    case Regex = 'regex';

    public function getLabel(): string
    {
        return match ($this) {
            self::Equals => 'Równa się',
            self::Contains => 'Zawiera',
            self::Regex => 'Wyrażenie regularne',
        };
    }
}
