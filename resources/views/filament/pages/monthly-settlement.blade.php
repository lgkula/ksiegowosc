@php
    use App\Support\Money;
    $m = fn ($v) => Money::format($v);
    $documentUrl = fn ($id) => \App\Filament\Resources\Documents\DocumentResource::getUrl('edit', ['record' => $id]);
    $revenueTotal = array_sum(array_column($s['revenue_lines'], 'net'));
@endphp

<x-filament-panels::page>
    @include('filament.pages.partials.styles')

    <div class="ks-grid">
        @if ($s['closed'])
            <div class="ks-alert">Miesiąc zamknięty {{ $s['closed_at'] ?? '' }}. Widoczne są zapisane wyliczenia.</div>
        @endif
        @if (($s['pending_documents'] ?? 0) > 0)
            <div class="ks-alert">
                {{ $s['pending_documents'] }} dokument(y) z tego miesiąca czekają na decyzję o ujęciu w kosztach.
                <a href="{{ \App\Filament\Resources\Documents\DocumentResource::getUrl('index', ['activeTab' => 'pending']) }}" style="text-decoration: underline">Przejdź do listy</a>
            </div>
        @endif
        @if (($s['uncategorized'] ?? 0) > 0)
            <div class="ks-alert">{{ $s['uncategorized'] }} pozycji bez kategorii - policzone jako koszt w 100% (PIT i VAT).</div>
        @endif

        {{-- Przychody --}}
        <x-filament::section heading="Przychody" :description="'Razem: '.$m($s['totals']['revenue'])">
            <div class="ks-scroll">
                <table class="ks-table">
                    <thead>
                        <tr><th>Data</th><th>Dokument</th><th>Kontrahent</th><th>Pozycja</th><th class="num">Netto</th><th class="num">VAT</th><th class="num">Brutto</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($s['revenue_lines'] as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td><a href="{{ $documentUrl($row['document_id']) }}">{{ $row['number'] ?: '—' }}</a></td>
                                <td>{{ $row['contractor'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="num">{{ $m($row['net']) }}</td>
                                <td class="num">{{ $m($row['vat']) }}</td>
                                <td class="num">{{ $m($row['gross']) }}</td>
                            </tr>
                        @empty
                            <tr class="ks-muted"><td colspan="7">Brak faktur przychodowych.</td></tr>
                        @endforelse
                        <tr>
                            <td colspan="4">Inne przychody @if ($s['other_income_note'])<span class="ks-muted">({{ $s['other_income_note'] }})</span>@endif</td>
                            <td class="num">{{ $m($s['other_income']) }}</td><td></td><td></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4">Przychód razem</td><td class="num">{{ $m($s['totals']['revenue']) }}</td><td class="num">{{ $m($s['totals']['vatOutput']) }}</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- Koszty z osobną sekcją (np. Joanna Kokot) --}}
        @foreach ($s['dedicated_cost_groups'] as $group)
            <x-filament::section :heading="'Koszty: '.$group['name']" :description="'Koszt PIT: '.$m($group['pit_cost'])">
                @include('filament.pages.partials.cost-rows', ['groups' => [$group], 'showGroupHeader' => false])
            </x-filament::section>
        @endforeach

        {{-- Pozostałe koszty --}}
        <x-filament::section heading="Pozostałe dokumenty kosztowe" :description="'Koszty podatkowe miesiąca razem: '.$m($s['totals']['costs'])">
            @include('filament.pages.partials.cost-rows', ['groups' => $s['cost_groups'], 'showGroupHeader' => true])
        </x-filament::section>

        <div class="ks-cols-2">
            {{-- Dochód --}}
            <x-filament::section heading="Dochód">
                <dl class="ks-kv">
                    <dt>Przychód</dt><dd>{{ $m($s['totals']['revenue']) }}</dd>
                    <dt>Koszty uzyskania przychodu</dt><dd>{{ $m($s['totals']['costs']) }}</dd>
                    <dt class="ks-strong">Dochód miesiąca</dt><dd class="ks-strong {{ $s['totals']['income'] < 0 ? 'ks-negative' : '' }}">{{ $m($s['totals']['income']) }}</dd>
                    <dt>Składki społeczne zapłacone w miesiącu</dt><dd>{{ $m($s['totals']['incomeDeductions']) }}</dd>
                    <dt>Dochód narastająco od stycznia</dt><dd>{{ $m($s['cumulative_income']) }}</dd>
                    <dt>Składki społeczne narastająco</dt><dd>{{ $m($s['pit']['cumulative_deductions']) }}</dd>
                    <dt>Podstawa opodatkowania narastająco</dt><dd>{{ $m($s['pit']['base']) }}</dd>
                </dl>
            </x-filament::section>

            {{-- Podatki i ZUS --}}
            <x-filament::section heading="Podatki i składki">
                <dl class="ks-kv">
                    <dt>Podatek narastająco (skala{{ $s['pit']['above_threshold'] ? ', powyżej progu' : '' }})</dt><dd>{{ $m($s['pit']['cumulative_tax']) }}</dd>
                    <dt>Zaliczki za poprzednie miesiące</dt><dd>{{ $m($s['pit']['previous_advances']) }}</dd>
                    <dt class="ks-strong">Zaliczka PIT do zapłaty</dt><dd class="ks-strong">{{ $m($s['pit']['advance']) }}</dd>

                    @foreach ($s['zus']['parts'] as $name => $amount)
                        <dt>ZUS {{ str_replace('_', ' ', $name) }}</dt><dd>{{ $m($amount) }}</dd>
                    @endforeach
                    <dt class="ks-strong">ZUS społeczny razem</dt><dd class="ks-strong">{{ $m($s['zus']['total']) }}</dd>

                    <dt>Zdrowotna: dochód poprzedniego miesiąca</dt><dd>{{ $m($s['health']['base_income']) }}</dd>
                    <dt>Zdrowotna minimalna w tym miesiącu @isset($s['health']['minimum_base'])<span class="ks-muted">(od {{ $m($s['health']['minimum_base']) }})</span>@endisset</dt><dd>{{ $m($s['health']['minimum']) }}</dd>
                    <dt class="ks-strong">Składka zdrowotna {{ $s['health']['is_minimum'] ? '(minimalna)' : '' }}</dt><dd class="ks-strong">{{ $m($s['health']['amount']) }}</dd>

                    <dt>VAT należny</dt><dd>{{ $m($s['vat']['output']) }}</dd>
                    <dt>VAT naliczony do odliczenia</dt><dd>{{ $m($s['vat']['input']) }}</dd>
                    @if ($s['vat']['carried_in'] > 0)
                        <dt>Nadwyżka z poprzednich miesięcy</dt><dd>{{ $m($s['vat']['carried_in']) }}</dd>
                    @endif
                    <dt class="ks-strong">VAT do zapłaty</dt><dd class="ks-strong">{{ $m($s['vat']['to_pay']) }}</dd>
                    @if ($s['vat']['carried_out'] > 0)
                        <dt>Nadwyżka do przeniesienia</dt><dd>{{ $m($s['vat']['carried_out']) }}</dd>
                    @endif
                </dl>
            </x-filament::section>
        </div>

        {{-- Konto firmowe --}}
        <x-filament::section heading="Konto firmowe" description="Ile zostawić na zobowiązania, a ile można przelać na konto prywatne.">
            <div class="ks-cols-2">
                <table class="ks-table">
                    <thead><tr><th>Zobowiązanie</th><th>Termin</th><th class="num">Kwota</th></tr></thead>
                    <tbody>
                        @foreach ($s['cash']['obligations'] as $row)
                            <tr><td>{{ $row['name'] }}</td><td>{{ $row['due'] ? \Carbon\Carbon::parse($row['due'])->format('d.m.Y') : '—' }}</td><td class="num">{{ $m($row['amount']) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="2">Zostawić na koncie</td><td class="num">{{ $m($s['cash']['to_keep']) }}</td></tr></tfoot>
                </table>
                <div>
                    <dl class="ks-kv">
                        <dt>Saldo konta firmowego</dt><dd>{{ $s['cash']['balance'] === null ? 'nie podano' : $m($s['cash']['balance']) }}</dd>
                        <dt>Zostawić na koncie</dt><dd>{{ $m($s['cash']['to_keep']) }}</dd>
                    </dl>
                    @if ($s['cash']['balance'] === null)
                        <p class="ks-muted" style="margin-top: 1rem">Wpisz saldo w „Dane miesiąca”, żeby wyliczyć kwotę do przelania.</p>
                    @else
                        <div style="margin-top: 1rem" class="ks-muted">Można przelać na konto prywatne</div>
                        <div class="ks-big ks-positive">{{ $m($s['cash']['to_transfer']) }}</div>
                        @if ($s['cash']['shortfall'] > 0)
                            <div class="ks-negative" style="margin-top: .5rem">Brakuje na zobowiązania: {{ $m($s['cash']['shortfall']) }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
