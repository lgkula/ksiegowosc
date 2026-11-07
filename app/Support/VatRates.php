<?php

namespace App\Support;

final class VatRates
{
    public static function options(): array
    {
        return [
            '23' => '23%',
            '8' => '8%',
            '5' => '5%',
            '0' => '0%',
            'zw' => 'zw.',
            'np' => 'np.',
        ];
    }
}
