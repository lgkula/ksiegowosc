<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentSource: string implements HasLabel
{
    case Ksef = 'ksef';
    case Manual = 'manual';
    case Issued = 'issued';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ksef => 'KSeF',
            self::Manual => 'Ręczny',
            self::Issued => 'Wystawiony',
        };
    }
}
