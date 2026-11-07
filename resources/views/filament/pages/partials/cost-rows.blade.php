@php
    $m = fn ($v) => \App\Support\Money::format($v);
    $documentUrl = fn ($id) => \App\Filament\Resources\Documents\DocumentResource::getUrl('edit', ['record' => $id]);
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',').'%';
@endphp

<div class="ks-scroll">
    <table class="ks-table">
        <thead>
            <tr>
                <th>Data</th><th>Dokument</th><th>Kontrahent</th><th>Pozycja</th>
                <th class="num">Netto</th><th class="num">VAT</th><th class="num">Brutto</th>
                <th class="num">% PIT</th><th class="num">Koszt PIT</th><th class="num">% VAT</th><th class="num">VAT odliczony</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                @if ($showGroupHeader)
                    <tr class="ks-group">
                        <td colspan="4">{{ $group['name'] }}</td>
                        <td class="num">{{ $m($group['net']) }}</td><td class="num">{{ $m($group['vat']) }}</td><td class="num">{{ $m($group['gross']) }}</td>
                        <td></td><td class="num">{{ $m($group['pit_cost']) }}</td><td></td><td class="num">{{ $m($group['vat_deductible']) }}</td>
                    </tr>
                @endif
                @foreach ($group['rows'] as $row)
                    <tr @class(['ks-muted' => $row['treatment'] === 'none'])>
                        <td>{{ $row['date'] }}</td>
                        <td><a href="{{ $documentUrl($row['document_id']) }}">{{ $row['number'] ?: '—' }}</a></td>
                        <td>{{ $row['contractor'] }}</td>
                        <td>
                            {{ $row['name'] }}
                            @if ($row['treatment'] !== 'cost')
                                <div class="ks-muted">{{ $row['treatment_label'] }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $m($row['net']) }}</td>
                        <td class="num">{{ $m($row['vat']) }}</td>
                        <td class="num">{{ $m($row['gross']) }}</td>
                        <td class="num">{{ $pct($row['pit_percent']) }}</td>
                        <td class="num">{{ $m($row['pit_cost']) }}</td>
                        <td class="num">{{ $pct($row['vat_percent']) }}</td>
                        <td class="num">{{ $m($row['vat_deductible']) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr class="ks-muted"><td colspan="11">Brak dokumentów.</td></tr>
            @endforelse
        </tbody>
        @if (count($groups) > 0)
            <tfoot>
                <tr>
                    <td colspan="4">Razem</td>
                    <td class="num">{{ $m(array_sum(array_column($groups, 'net'))) }}</td>
                    <td class="num">{{ $m(array_sum(array_column($groups, 'vat'))) }}</td>
                    <td class="num">{{ $m(array_sum(array_column($groups, 'gross'))) }}</td>
                    <td></td>
                    <td class="num">{{ $m(array_sum(array_column($groups, 'pit_cost'))) }}</td>
                    <td></td>
                    <td class="num">{{ $m(array_sum(array_column($groups, 'vat_deductible'))) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
