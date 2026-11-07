<?php

namespace App\Filament\Support;

use App\Support\Period;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/** Filtr zakresu: cały rok albo pojedynczy miesiąc. */
class PeriodFilter
{
    /** @param  string|null  $relationship  relacja z kolumną period, np. "document" dla pozycji */
    public static function make(?string $relationship = null): Filter
    {
        $column = 'period';

        $years = range((int) now()->format('Y') + 1, 2023);

        return Filter::make('period_range')
            ->schema([
                Select::make('year')
                    ->label('Rok')
                    ->options(array_combine($years, $years))
                    ->default(now()->format('Y')),
                Select::make('month')
                    ->label('Miesiąc')
                    ->options(array_map('ucfirst', Period::MONTHS))
                    ->placeholder('cały rok'),
            ])
            ->columns(2)
            ->query(function (Builder $query, array $data) use ($column, $relationship) {
                if (blank($data['year'] ?? null)) {
                    return $query;
                }

                $apply = fn (Builder $q) => filled($data['month'] ?? null)
                    ? $q->where($column, Period::make((int) $data['year'], (int) $data['month']))
                    : $q->where($column, 'like', $data['year'].'-%');

                return $relationship ? $query->whereHas($relationship, $apply) : $apply($query);
            })
            ->indicateUsing(function (array $data) {
                if (blank($data['year'] ?? null)) {
                    return null;
                }

                return filled($data['month'] ?? null)
                    ? 'Okres: '.Period::label(Period::make((int) $data['year'], (int) $data['month']))
                    : 'Rok: '.$data['year'];
            });
    }
}
