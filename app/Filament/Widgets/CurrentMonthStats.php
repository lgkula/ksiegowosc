<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MonthlySettlementPage;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Services\Settlement\SettlementService;
use App\Support\Money;
use App\Support\Period;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CurrentMonthStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Bieżący miesiąc';

    protected function getStats(): array
    {
        $period = Period::current();
        $s = app(SettlementService::class)->monthly($period);
        $url = MonthlySettlementPage::getUrl(['period' => $period]);
        $pending = Document::pending()->count();

        return [
            Stat::make('Przychód ('.Period::label($period).')', Money::format($s['totals']['revenue']))->url($url),
            Stat::make('Koszty', Money::format($s['totals']['costs']))->url($url),
            Stat::make('Dochód', Money::format($s['totals']['income']))
                ->description('Narastająco: '.Money::format($s['cumulative_income']))
                ->color($s['totals']['income'] < 0 ? 'danger' : 'success')
                ->url($url),
            Stat::make('Podatki i ZUS', Money::format($s['pit']['advance'] + $s['zus']['total'] + $s['health']['amount'] + $s['vat']['to_pay']))
                ->description('PIT + ZUS + zdrowotna + VAT')
                ->url($url),
            Stat::make('Do decyzji', (string) $pending)
                ->description('dokumenty z KSeF bez decyzji')
                ->color($pending > 0 ? 'warning' : 'gray')
                ->url(DocumentResource::getUrl('index', ['activeTab' => 'pending'])),
        ];
    }
}
