<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Pomocnik dla okresów rozliczeniowych w formacie RRRR-MM. */
final class Period
{
    public const MONTHS = [
        1 => 'styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec',
        'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień',
    ];

    public static function current(): string
    {
        return now()->format('Y-m');
    }

    public static function make(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    public static function year(string $period): int
    {
        return (int) substr($period, 0, 4);
    }

    public static function month(string $period): int
    {
        return (int) substr($period, 5, 2);
    }

    public static function previous(string $period): string
    {
        return self::start($period)->subMonth()->format('Y-m');
    }

    public static function next(string $period): string
    {
        return self::start($period)->addMonth()->format('Y-m');
    }

    /** Liczba miesięcy od $from do $to (ujemna, gdy $to jest wcześniej). */
    public static function monthsBetween(string $from, string $to): int
    {
        return (self::year($to) - self::year($from)) * 12 + self::month($to) - self::month($from);
    }

    public static function start(string $period): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $period);
    }

    /** Wszystkie miesiące roku. */
    public static function ofYear(int $year): array
    {
        return array_map(fn (int $m) => self::make($year, $m), range(1, 12));
    }

    /** Miesiące od stycznia do podanego miesiąca włącznie. */
    public static function yearToDate(string $period): array
    {
        return array_map(fn (int $m) => self::make(self::year($period), $m), range(1, self::month($period)));
    }

    public static function label(string $period): string
    {
        return self::MONTHS[self::month($period)].' '.self::year($period);
    }

    /** Termin płatności w miesiącu następującym po okresie, np. 20. dzień. */
    public static function dueDate(string $period, int $day): CarbonImmutable
    {
        return self::start(self::next($period))->setDay($day);
    }
}
