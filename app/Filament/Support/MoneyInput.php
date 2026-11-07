<?php

namespace App\Filament\Support;

use App\Support\Money;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Pole kwoty: tekst z maską (odstęp tysięcy, przecinek dziesiętny), bez strzałek góra/dół.
 * W formularzu stan to tekst, np. "1 234,50"; do modelu trafia liczba.
 */
final class MoneyInput
{
    public static function make(string $name, int $decimals = 2): TextInput
    {
        return TextInput::make($name)
            ->mask(RawJs::make("\$money(\$input, ',', ' ', {$decimals})"))
            ->inputMode('decimal')
            ->extraInputAttributes(['class' => 'ks-amount'])
            ->formatStateUsing(fn ($state) => Money::input($state, $decimals))
            ->dehydrateStateUsing(fn ($state) => Money::parse($state, $decimals))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($decimals) {
                if (filled($value) && Money::parse($value, $decimals) === null) {
                    $fail('Wpisz poprawną kwotę, np. 1 234,50.');
                }
            });
    }
}
