@php
    $m = fn ($v) => \App\Support\Money::format($v);
    $monthUrl = fn ($period) => \App\Filament\Pages\MonthlySettlementPage::getUrl(['period' => $period]);
@endphp

<x-filament-panels::page>
    @include('filament.pages.partials.styles')

    <div class="ks-grid">
        <x-filament::section>
            <div class="ks-scroll">
                <table class="ks-table">
                    <thead>
                        <tr><th>Miesiąc</th><th class="num">Przychody</th><th class="num">Wydatki</th><th class="num">Dochód</th><th class="num">Dochód narastająco</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($y['rows'] as $row)
                            <tr @class(['ks-muted' => ! $row['has_data']])>
                                <td><a href="{{ $monthUrl($row['period']) }}">{{ ucfirst($row['label']) }}</a></td>
                                <td class="num">{{ $m($row['revenue']) }}</td>
                                <td class="num">{{ $m($row['costs']) }}</td>
                                <td class="num {{ $row['income'] < 0 ? 'ks-negative' : '' }}">{{ $m($row['income']) }}</td>
                                <td class="num">{{ $m($row['cumulative_income']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Razem {{ $y['year'] }}</td>
                            <td class="num">{{ $m($y['summary']['revenue']) }}</td>
                            <td class="num">{{ $m($y['summary']['costs']) }}</td>
                            <td class="num">{{ $m($y['summary']['income']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        <div class="ks-cols-2">
            <x-filament::section heading="Przychody w roku">
                <div class="ks-big">{{ $m($y['summary']['revenue']) }}</div>
            </x-filament::section>
            <x-filament::section heading="Wydatki w roku">
                <div class="ks-big">{{ $m($y['summary']['costs']) }}</div>
            </x-filament::section>
            <x-filament::section heading="Średni dochód miesięczny" :description="'Z '.$y['summary']['months_with_data'].' mies. z danymi'">
                <div class="ks-big {{ $y['summary']['average_income'] < 0 ? 'ks-negative' : 'ks-positive' }}">{{ $m($y['summary']['average_income']) }}</div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
