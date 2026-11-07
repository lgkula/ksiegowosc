<style>
    .ks-grid { display: grid; gap: 1.5rem; }
    .ks-cols-2 { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(22rem, 1fr)); }
    .ks-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .ks-table th, .ks-table td { padding: .45rem .6rem; border-bottom: 1px solid var(--gray-200); text-align: left; vertical-align: top; }
    .ks-table th { font-weight: 600; color: var(--gray-500); font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; }
    .ks-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .ks-table tfoot td, .ks-table tr.ks-total td { font-weight: 600; border-top: 2px solid var(--gray-300); }
    .ks-table tr.ks-group td { background: var(--gray-50); font-weight: 600; }
    .ks-table tr.ks-muted td { color: var(--gray-400); }
    .ks-muted { color: var(--gray-500); font-size: .8rem; }
    .ks-kv { display: grid; grid-template-columns: 1fr auto; gap: .35rem 1rem; font-size: .9rem; }
    .ks-kv dt { color: var(--gray-500); }
    .ks-kv dd { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ks-kv .ks-strong { font-weight: 700; font-size: 1rem; color: var(--gray-950); }
    .ks-big { font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .ks-positive { color: var(--success-600); }
    .ks-negative { color: var(--danger-600); }
    .ks-alert { padding: .6rem .9rem; border-radius: .5rem; background: var(--warning-50); color: var(--warning-700); font-size: .875rem; }
    .ks-scroll { overflow-x: auto; }
    .dark .ks-table th, .dark .ks-table td { border-color: rgb(255 255 255 / .08); }
    .dark .ks-table tr.ks-group td { background: rgb(255 255 255 / .04); }
    .dark .ks-kv .ks-strong { color: #fff; }
    .dark .ks-alert { background: rgb(234 179 8 / .12); color: var(--warning-400); }
    .ks-stack { display: grid; gap: 1.5rem; align-content: start; }
    .ks-forecast-form input[type=number] { -moz-appearance: textfield; appearance: textfield; }
    .ks-forecast-form input[type=number]::-webkit-inner-spin-button,
    .ks-forecast-form input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
