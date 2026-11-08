<style>
    /* Pola kwot (MoneyInput): tekst z maską, bez strzałek, cyfry wyrównane do prawej. */
    input.ks-amount { text-align: right; font-variant-numeric: tabular-nums; }

    /*
     * Widok pozycji faktur: kolumna "Pozycja" nie zajmuje miejsca w siatce tabeli,
     * tylko jest wyświetlana jako druga linia wiersza na całą jego szerokość.
     * Komórka zostaje w tabeli (zerowa szerokość), żeby liczba kolumn w nagłówku,
     * wierszach i podsumowaniu się zgadzała.
     */
    .ks-items-table .fi-ta-header-cell-name,
    .ks-items-table td.fi-ta-cell-name { width: 0; padding: 0; overflow: hidden; }
    .ks-items-table .fi-ta-header-cell-name { font-size: 0; line-height: 0; }
    .ks-items-table tbody tr.fi-ta-row:not(.fi-ta-summary-row) { position: relative; }
    .ks-items-table tbody tr.fi-ta-row:not(.fi-ta-summary-row) > td { vertical-align: middle; }
    .ks-items-table tbody tr.fi-ta-row:not(.fi-ta-summary-row) > td:not(.fi-ta-cell-name) { padding-bottom: 1.25rem; }
    .ks-items-table td.fi-ta-cell-name > .fi-ta-col {
        position: absolute; left: .75rem; right: .75rem; bottom: .75rem; width: auto;
    }
    .ks-items-table td.fi-ta-cell-name .fi-ta-text { padding: 0 .75rem; }
    .ks-items-table td.fi-ta-cell-name .fi-ta-text-item,
    .ks-items-table td.fi-ta-cell-name .fi-ta-text-item * {
        display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;
    }
</style>
