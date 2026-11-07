<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentKind: string implements HasLabel
{
    case Invoice = 'invoice';
    case Transfer = 'transfer';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => 'Faktura',
            self::Transfer => 'Przelew',
            self::Other => 'Inny dokument',
        };
    }
}
