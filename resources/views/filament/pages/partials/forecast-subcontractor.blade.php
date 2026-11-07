@php
    $m = fn ($v) => \App\Support\Money::format($v);
    $pct = fn ($v) => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',').'%';
    $num = fn ($v) => rtrim(rtrim(number_format($v, 2, ',', "\u{00A0}"), '0'), ',');
    $sub = $f['subcontractor'];
@endphp

<div class="ks-sub-summary">
    <p class="ks-muted">
        {{ $num($sub['hours']) }} h × {{ $m($sub['rate']) }} = {{ $m($sub['base']) }}
        @if ($sub['extra'] != 0) + dodatkowe {{ $m($sub['extra']) }} @endif
    </p>
    <dl class="ks-kv">
        <dt class="ks-strong">Kwota netto</dt><dd class="ks-strong">{{ $m($sub['net']) }}</dd>
        <dt class="ks-strong">Kwota brutto</dt><dd class="ks-strong">{{ $m($sub['gross']) }}</dd>
        <dt class="ks-strong">Obciążenia (VAT + PIT)</dt><dd class="ks-strong">{{ $m($sub['burden']) }}</dd>
        <dt>w tym VAT {{ $pct($sub['vat_rate']) }}</dt><dd>{{ $m($sub['vat']) }}</dd>
        <dt>w tym PIT {{ $pct($sub['pit_rate']) }} od netto</dt><dd>{{ $m($sub['pit']) }}</dd>
    </dl>
    @if ($sub['documents'])
        <p class="ks-muted" style="margin-top: .75rem">Faktura w dokumentach: {{ implode(', ', $sub['documents']) }} – w kosztach zastąpiona wyliczeniem.</p>
    @endif
</div>
