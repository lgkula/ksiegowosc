@php
    $m = fn ($v) => \App\Support\Money::format($v);
    $signed = fn ($v) => $v === null ? '—' : ($v > 0 ? '+' : '').\App\Support\Money::format($v);
    $sub = $f['subcontractor'];
@endphp

<x-filament::section heading="Prognoza">
    <dl class="ks-kv">
        <dt>Kruk: podstawa</dt><dd>{{ $m($f['kruk']['base']) }}</dd>
        <dt>Kruk: dodatki</dt><dd>{{ $m($f['kruk']['additions']) }}</dd>
        <dt>Kruk: faktura brutto</dt><dd>{{ $m($f['kruk']['gross']) }}</dd>
        <dt>Inne przychody</dt><dd>{{ $m($f['other_income']) }}</dd>
        <dt class="ks-strong">Przychód</dt><dd class="ks-strong">{{ $m($f['revenue']) }}</dd>
        <dt>Koszty z dokumentów</dt><dd>{{ $m($f['document_costs']) }}</dd>
        <dt>Koszt: {{ $sub['name'] }}</dt><dd>{{ $m($sub['pit_cost']) }}</dd>
        <dt class="ks-strong">Koszty razem</dt><dd class="ks-strong">{{ $m($f['costs']) }}</dd>
    </dl>

    <div style="margin-top: 1.25rem" class="ks-muted">Prognozowany dochód</div>
    <div class="ks-big {{ $f['income'] < 0 ? 'ks-negative' : 'ks-positive' }}">{{ $m($f['income']) }}</div>
    @if ($f['deviation'] !== null)
        <div class="{{ $f['deviation'] < 0 ? 'ks-negative' : 'ks-positive' }}">
            {{ $signed($f['deviation']) }}@if ($f['deviation_percent'] !== null) ({{ $f['deviation_percent'] > 0 ? '+' : '' }}{{ number_format($f['deviation_percent'], 1, ',', '') }}%)@endif
            względem dotychczasowego średniego dochodu
        </div>
    @endif

    <dl class="ks-kv" style="margin-top: 1.25rem">
        <dt>Dochód narastająco od stycznia (z prognozą)</dt><dd class="ks-strong">{{ $m($f['cumulative_income']) }}</dd>
        <dt>Dochód narastająco do poprzedniego miesiąca</dt><dd>{{ $m($f['previous']['income']) }}</dd>
        <dt>Średni dochód w roku (z prognozą, {{ $f['months'] }} mies.)</dt><dd class="ks-strong">{{ $m($f['average_income']) }}</dd>
        <dt>Dotychczasowy średni dochód ({{ $f['previous']['months_with_data'] }} mies.)</dt><dd>{{ $f['previous']['average'] === null ? 'brak danych' : $m($f['previous']['average']) }}</dd>
        <dt>Zmiana średniej po prognozie</dt><dd class="{{ ($f['average_change'] ?? 0) < 0 ? 'ks-negative' : '' }}">{{ $signed($f['average_change']) }}</dd>
        <dt>Odchylenie dochodu od dotychczasowej średniej</dt><dd class="{{ ($f['deviation'] ?? 0) < 0 ? 'ks-negative' : '' }}">{{ $signed($f['deviation']) }}</dd>
    </dl>
</x-filament::section>
