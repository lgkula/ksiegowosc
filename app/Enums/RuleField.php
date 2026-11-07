<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RuleField: string implements HasLabel
{
    case ItemName = 'item_name';
    case ContractorNip = 'contractor_nip';
    case ContractorName = 'contractor_name';

    public function getLabel(): string
    {
        return match ($this) {
            self::ItemName => 'Nazwa towaru',
            self::ContractorNip => 'NIP kontrahenta',
            self::ContractorName => 'Nazwa kontrahenta',
        };
    }
}
