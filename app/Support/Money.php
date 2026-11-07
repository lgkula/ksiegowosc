<?php

namespace App\Support;

final class Money
{
    public static function format(float|int|string|null $amount, bool $currency = true, string $symbol = 'zł'): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $formatted = number_format((float) $amount, 2, ',', "\u{00A0}");

        return $currency ? $formatted."\u{00A0}".$symbol : $formatted;
    }

    /** Kwota do pola formularza z maską: odstęp tysięcy i przecinek dziesiętny, np. 1 234,50. */
    public static function input(float|int|string|null $amount, int $decimals = 2): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $value = is_string($amount) ? self::parse($amount, $decimals) : (float) $amount;

        return $value === null ? null : number_format($value, $decimals, ',', ' ');
    }

    /** Liczba z pola formularza: dopuszcza spacje tysięcy oraz przecinek albo kropkę dziesiętną. */
    public static function parse(mixed $value, int $decimals = 2): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $value));
        }

        return is_numeric($value) ? round((float) $value, $decimals) : null;
    }
}
