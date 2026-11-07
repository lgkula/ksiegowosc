@php
    $m = fn ($v) => \App\Support\Money::format($v);
    $signed = fn ($v) => $v === null ? '—' : ($v > 0 ? '+' : '').\App\Support\Money::format($v);
    $documentUrl = fn ($id) => \App\Filament\Resources\Documents\DocumentResource::getUrl('edit', ['record' => $id]);
    $monthUrl = fn ($period) => \App\Filament\Pages\MonthlySettlementPage::getUrl(['period' => $period]);
    $sub = $f['subcontractor'];
@endphp

<x-filament-panels::page>
    @include('filament.pages.partials.styles')

    <div class="ks-grid">
        @if ($f['pending_count'] > 0 && ! ($this->data['include_pending'] ?? false))
            <div class="ks-alert">
                {{ $f['pending_count'] }} dokument(y) kosztowe z tego miesiąca czekają na decyzję i nie są wliczone w koszty.
            </div>
        @endif

        <div class="ks-forecast-form">
            {{ $this->form }}
        </div>
        <p class="ks-muted">Zmiany zapisują się automatycznie dla wybranego miesiąca.
            @if ($f['kruk']['document'])
                Faktura dla Kruka: {{ $f['kruk']['document'] }}.
            @else
                Brak faktury dla Kruka w tym miesiącu.
            @endif
        </p>

        <x-filament::section heading="Koszty z dokumentów" :description="'KSeF i dokumenty ręczne, bez faktur: '.$sub['name'].'. Koszt PIT razem: '.$m($f['document_costs'])">
            <div class="ks-scroll">
                <table class="ks-table">
                    <thead>
                        <tr><th>Data</th><th>Dokument</th><th>Źródło</th><th>Kontrahent</th><th>Pozycja</th><th>Kategoria</th><th class="num">Netto</th><th class="num">Brutto</th><th class="num">Koszt PIT</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($f['cost_rows'] as $row)
                            <tr @class(['ks-muted' => $row['treatment'] !== 'cost'])>
                                <td>{{ $row['date'] }}</td>
                                <td><a href="{{ $documentUrl($row['document_id']) }}">{{ $row['number'] ?: '—' }}</a></td>
                                <td>{{ $row['source'] }}@if ($row['pending'])<div class="ks-muted">do decyzji</div>@endif</td>
                                <td>{{ $row['contractor'] }}</td>
                                <td>
                                    {{ $row['name'] }}
                                    @if ($row['treatment'] !== 'cost')
                                        <div class="ks-muted">{{ $row['treatment_label'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $row['category'] ?? 'bez kategorii' }}</td>
                                <td class="num">{{ $m($row['net']) }}</td>
                                <td class="num">{{ $m($row['gross']) }}</td>
                                <td class="num">{{ $m($row['pit_cost']) }}</td>
                            </tr>
                        @empty
                            <tr class="ks-muted"><td colspan="9">Brak dokumentów kosztowych w tym miesiącu.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Razem</td>
                            <td class="num">{{ $m(array_sum(array_column($f['cost_rows'], 'net'))) }}</td>
                            <td class="num">{{ $m(array_sum(array_column($f['cost_rows'], 'gross'))) }}</td>
                            <td class="num">{{ $m($f['document_costs']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Dochód w roku" description="Miesiące poprzednie według rozliczeń, bieżący według prognozy.">
            <div class="ks-scroll">
                <table class="ks-table">
                    <thead><tr><th>Miesiąc</th><th class="num">Dochód</th><th class="num">Odchylenie od średniej</th></tr></thead>
                    <tbody>
                        @foreach ($f['previous']['months'] as $row)
                            <tr @class(['ks-muted' => ! $row['has_data']])>
                                <td><a href="{{ $monthUrl($row['period']) }}">{{ ucfirst($row['label']) }}</a></td>
                                <td class="num">{{ $m($row['income']) }}</td>
                                <td class="num">{{ $row['has_data'] && $f['previous']['average'] !== null ? $signed(round($row['income'] - $f['previous']['average'], 2)) : '—' }}</td>
                            </tr>
                        @endforeach
                        <tr class="ks-group">
                            <td>{{ ucfirst(\App\Support\Period::label($f['period'])) }} (prognoza)</td>
                            <td class="num">{{ $m($f['income']) }}</td>
                            <td class="num">{{ $signed($f['deviation']) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr><td>Narastająco / średnio</td><td class="num">{{ $m($f['cumulative_income']) }}</td><td class="num">{{ $m($f['average_income']) }} / mies.</td></tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
