<?php

namespace App\Services\Forecast;

use App\Enums\Direction;
use App\Enums\Treatment;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\IncomeForecast;
use App\Models\MonthlySettlement;
use App\Services\Settlement\ItemAmounts;
use App\Services\Settlement\Line;
use App\Services\Settlement\PeriodTotals;
use App\Services\Settlement\SettlementService;
use App\Support\Period;
use Illuminate\Support\Collection;

/**
 * Model dochodu na miesiąc: koszty z dokumentów (KSeF i ręczne), przychody wpisywane ręcznie
 * (Kruk: wynagrodzenie podstawowe i dodatki, inne przychody) oraz faktura podwykonawcy
 * liczona z liczby godzin i stawki.
 */
class IncomeForecastService
{
    public function __construct(private SettlementService $settlement) {}

    /** Dane wejściowe modelu: zapisane dla miesiąca albo zaczytane z dokumentów. */
    public function input(string $period): array
    {
        $saved = IncomeForecast::where('period', $period)->value('data');

        return $saved ? $this->normalize($saved + $this->defaults($period)) : $this->defaults($period);
    }

    public function save(string $period, array $input): void
    {
        IncomeForecast::updateOrCreate(['period' => $period], ['data' => $this->normalize($input)]);
    }

    public function forget(string $period): void
    {
        IncomeForecast::where('period', $period)->delete();
    }

    /** Wartości startowe na podstawie dokumentów miesiąca. */
    public function defaults(string $period): array
    {
        $kruk = $this->krukInvoice($period);
        $sub = $this->subcontractorDefaults($period);

        // Bez faktury dla Kruka za miesiąc (powyżej progu) podstawa przechodzi z poprzedniego miesiąca.
        if (! $this->isKrukInvoiced($kruk)) {
            $kruk['base'] = $this->previousKrukBase($period);
            $kruk['additions'] = 0.0;
        }

        return [
            'kruk_base' => $kruk['base'],
            'kruk_additions' => $kruk['additions'],
            'kruk_vat_rate' => $kruk['vat_rate'],
            'other_income' => $this->otherIncomeDefaults($period),
            'sub_hours' => $sub['hours'],
            'sub_rate' => $sub['rate'],
            'sub_extra' => $sub['extra'],
            'sub_pit_rate' => $sub['pit_rate'],
            'include_pending' => false,
        ];
    }

    /** Pełne wyliczenie modelu dla miesiąca. */
    public function calculate(string $period, array $input): array
    {
        $input = $this->normalize($input);
        $vatRate = (float) $input['kruk_vat_rate'];

        $krukNet = round($input['kruk_base'] + $input['kruk_additions'], 2);
        $krukVat = round($krukNet * $vatRate / 100, 2);
        $otherIncome = round(array_sum(array_column($input['other_income'], 'net')), 2);
        $revenue = round($krukNet + $otherIncome, 2);

        $sub = $this->subcontractorInvoice($input['sub_hours'], $input['sub_rate'], $input['sub_extra'], $input['sub_pit_rate']);
        $costRows = $this->costRows($period, $input['include_pending']);
        $documentCosts = round(array_sum(array_column($costRows, 'pit_cost')), 2);
        $costs = round($documentCosts + $sub['pit_cost'], 2);
        $income = round($revenue - $costs, 2);

        $previous = $this->previousMonths($period);
        $cumulative = round($previous['income'] + $income, 2);
        $months = $previous['months_with_data'] + 1;
        $average = round($cumulative / $months, 2);
        $previousAverage = $previous['average'];
        $deviation = $previousAverage === null ? null : round($income - $previousAverage, 2);

        return [
            'period' => $period,
            'kruk' => [
                'base' => $input['kruk_base'],
                'additions' => $input['kruk_additions'],
                'net' => $krukNet,
                'vat_rate' => $vatRate,
                'vat' => $krukVat,
                'gross' => round($krukNet + $krukVat, 2),
                'document' => $this->krukInvoice($period)['document'],
            ],
            'other_income' => $otherIncome,
            'revenue' => $revenue,
            'subcontractor' => $sub + [
                'name' => config('forecast.subcontractor.name'),
                'nip' => config('forecast.subcontractor.nip'),
                'documents' => $this->subcontractorDocuments($period)->pluck('number')->filter()->values()->all(),
            ],
            'cost_rows' => $costRows,
            'document_costs' => $documentCosts,
            'pending_count' => $this->pendingCount($period),
            'costs' => $costs,
            'income' => $income,
            'previous' => $previous,
            'cumulative_income' => $cumulative,
            'months' => $months,
            'average_income' => $average,
            'average_change' => $previousAverage === null ? null : round($average - $previousAverage, 2),
            'deviation' => $deviation,
            'deviation_percent' => $deviation === null || $previousAverage == 0 ? null : round($deviation / abs($previousAverage) * 100, 1),
        ];
    }

    /**
     * Faktura podwykonawcy: netto = godziny × stawka + dodatkowe wynagrodzenie, VAT wg stawki z konfiguracji.
     * Obciążenia wystawcy faktury = VAT + PIT (% od netto). Koszt PIT u nas wg kategorii kontrahenta.
     */
    public function subcontractorInvoice(float $hours, float $rate, float $extra = 0, ?float $pitRate = null): array
    {
        $vatRate = (float) config('forecast.subcontractor.vat_rate', 23);
        $pitRate ??= (float) config('forecast.subcontractor.pit_rate', 12);
        $base = round($hours * $rate, 2);
        $net = round($base + $extra, 2);
        $vat = round($net * $vatRate / 100, 2);
        $pit = round($net * $pitRate / 100, 2);
        $category = $this->subcontractor()?->defaultCategory;
        $amounts = ItemAmounts::forCost($net, $vat, (float) ($category?->pit_percent ?? 100), (float) ($category?->vat_percent ?? 100));

        return [
            'hours' => $hours,
            'rate' => $rate,
            'base' => $base,
            'extra' => round($extra, 2),
            'net' => $net,
            'vat_rate' => $vatRate,
            'vat' => $vat,
            'gross' => round($net + $vat, 2),
            'pit_rate' => $pitRate,
            'pit' => $pit,
            'burden' => round($vat + $pit, 2),
            'pit_cost' => $amounts->pitCost,
            'vat_deductible' => $amounts->vatDeductible,
        ];
    }

    private function normalize(array $input): array
    {
        $number = fn ($value) => $this->parseNumber($value);

        $other = collect($input['other_income'] ?? [])
            ->map(fn ($row) => ['name' => trim((string) ($row['name'] ?? '')), 'net' => $number($row['net'] ?? 0)])
            ->filter(fn ($row) => $row['name'] !== '' || $row['net'] != 0)
            ->values()
            ->all();

        return [
            'kruk_base' => $number($input['kruk_base'] ?? 0),
            'kruk_additions' => $number($input['kruk_additions'] ?? 0),
            'kruk_vat_rate' => is_numeric($input['kruk_vat_rate'] ?? null) ? (string) $input['kruk_vat_rate'] : (string) config('forecast.kruk.vat_rate', 23),
            'other_income' => $other,
            'sub_hours' => $number($input['sub_hours'] ?? 0),
            'sub_rate' => $number($input['sub_rate'] ?? 0),
            'sub_extra' => $number($input['sub_extra'] ?? 0),
            'sub_pit_rate' => filled($input['sub_pit_rate'] ?? null) ? $number($input['sub_pit_rate']) : (float) config('forecast.subcontractor.pit_rate', 12),
            'include_pending' => (bool) ($input['include_pending'] ?? false),
        ];
    }

    /** Faktura dla Kruka z miesiąca rozbita na wynagrodzenie podstawowe i dodatki. */
    private function krukInvoice(string $period): array
    {
        $documents = Document::query()
            ->where('direction', Direction::Revenue)
            ->where('period', $period)
            ->where(fn ($q) => $q->where('included', true)->orWhereNull('included'))
            ->whereIn('contractor_id', $this->krukContractorIds())
            ->with('items')
            ->orderBy('issue_date')
            ->get();

        $base = 0.0;
        $additions = 0.0;
        $vatRate = null;
        $pattern = config('forecast.kruk.additions_pattern');

        foreach ($documents as $document) {
            if ($document->items->isEmpty()) {
                $base += (float) $document->net;

                continue;
            }

            $hasNamedAdditions = $document->items->contains(fn ($item) => preg_match($pattern, $item->name));

            foreach ($document->items->values() as $index => $item) {
                $isAddition = $hasNamedAdditions ? (bool) preg_match($pattern, $item->name) : $index > 0;
                $isAddition ? $additions += (float) $item->net : $base += (float) $item->net;
                $vatRate ??= is_numeric($item->vat_rate) ? $item->vat_rate : null;
            }
        }

        return [
            'base' => round($base, 2),
            'additions' => round($additions, 2),
            'vat_rate' => (string) ($vatRate ?? config('forecast.kruk.vat_rate', 23)),
            'document' => $documents->pluck('number')->filter()->implode(', ') ?: null,
        ];
    }

    private function isKrukInvoiced(array $kruk): bool
    {
        return $kruk['base'] + $kruk['additions'] > (float) config('forecast.kruk.invoiced_threshold', 5000);
    }

    /** Podstawa z poprzedniego miesiąca: z faktury, a gdy jej brak - z modelu zapisanego dla tego miesiąca. */
    private function previousKrukBase(string $period): float
    {
        $previous = Period::previous($period);
        $invoice = $this->krukInvoice($previous);

        if ($this->isKrukInvoiced($invoice)) {
            return $invoice['base'];
        }

        $saved = IncomeForecast::where('period', $previous)->value('data') ?? [];

        return $this->parseNumber($saved['kruk_base'] ?? 0);
    }

    /** Liczba z pola formularza: dopuszcza spacje tysięcy i przecinek dziesiętny. */
    public function parseNumber(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $value));
        }

        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private function krukContractorIds(): array
    {
        $nip = Contractor::normalizeNip(config('forecast.kruk.nip'));
        $pattern = config('forecast.kruk.name_pattern');

        return Contractor::all(['id', 'nip', 'full_name', 'short_name'])
            ->filter(fn (Contractor $c) => ($nip && $c->nip === $nip)
                || preg_match($pattern, $c->full_name)
                || preg_match($pattern, (string) $c->short_name))
            ->pluck('id')
            ->all();
    }

    /** Przychody miesiąca spoza faktur dla Kruka: inne faktury sprzedażowe i „Inne przychody” z danych miesiąca. */
    private function otherIncomeDefaults(string $period): array
    {
        $rows = Document::included()
            ->where('direction', Direction::Revenue)
            ->where('period', $period)
            ->whereNotIn('contractor_id', $this->krukContractorIds())
            ->with('contractor')
            ->get()
            ->map(fn (Document $d) => [
                'name' => trim(($d->contractor?->displayName() ?? 'Faktura').' '.($d->number ?? '')),
                'net' => (float) $d->net,
            ])
            ->all();

        $settlement = MonthlySettlement::where('period', $period)->first();
        if ($settlement && (float) $settlement->other_income != 0) {
            $rows[] = ['name' => $settlement->other_income_note ?: 'Inne przychody', 'net' => (float) $settlement->other_income];
        }

        return $rows;
    }

    private function subcontractor(): ?Contractor
    {
        $nip = Contractor::normalizeNip(config('forecast.subcontractor.nip'));

        return $nip ? Contractor::with('defaultCategory')->where('nip', $nip)->first() : null;
    }

    private function subcontractorDocuments(string $period): Collection
    {
        $contractor = $this->subcontractor();

        return $contractor
            ? Document::where('contractor_id', $contractor->id)
                ->where('period', $period)
                ->where(fn ($q) => $q->where('included', true)->orWhereNull('included'))
                ->with('items')
                ->get()
            : collect();
    }

    /**
     * Wartości startowe faktury podwykonawcy. Gdy faktura za miesiąc już jest: godziny i stawka z pierwszej pozycji,
     * pozostałe pozycje jako dodatkowe wynagrodzenie. Bez faktury: stawka wpisana w modelu poprzedniego miesiąca,
     * a gdy jej brak - z ostatniej faktury.
     */
    private function subcontractorDefaults(string $period): array
    {
        $previous = IncomeForecast::where('period', Period::previous($period))->value('data') ?? [];
        $pitRate = is_numeric($previous['sub_pit_rate'] ?? null) ? (float) $previous['sub_pit_rate'] : (float) config('forecast.subcontractor.pit_rate', 12);
        $items = $this->subcontractorDocuments($period)->flatMap->items->values();

        if ($items->isNotEmpty()) {
            $first = $items->first();

            return [
                'hours' => round((float) $first->qty, 2),
                'rate' => round($this->unitRate($first), 2),
                'extra' => round((float) $items->slice(1)->sum('net'), 2),
                'pit_rate' => $pitRate,
            ];
        }

        $rate = is_numeric($previous['sub_rate'] ?? null) && (float) $previous['sub_rate'] > 0 ? (float) $previous['sub_rate'] : null;

        if ($rate === null && $contractor = $this->subcontractor()) {
            $item = Document::where('contractor_id', $contractor->id)->where('period', '<', $period)
                ->with('items')->orderByDesc('issue_date')->first()?->items->first();
            $rate = $item ? $this->unitRate($item) : null;
        }

        return ['hours' => 0.0, 'rate' => round($rate ?? 0, 2), 'extra' => 0.0, 'pit_rate' => $pitRate];
    }

    private function unitRate($item): float
    {
        return (float) ($item->unit_net ?? ($item->qty > 0 ? $item->net / $item->qty : $item->net));
    }

    /** Pozycje kosztowe miesiąca bez faktur podwykonawcy (te zastępuje model). */
    private function costRows(string $period, bool $includePending): array
    {
        $excluded = $this->subcontractor()?->id;

        return Document::query()
            ->where('direction', Direction::Cost)
            ->where('period', $period)
            ->where(fn ($q) => $includePending ? $q->where('included', true)->orWhereNull('included') : $q->where('included', true))
            ->when($excluded, fn ($q) => $q->where(fn ($q) => $q->whereNull('contractor_id')->orWhere('contractor_id', '!=', $excluded)))
            ->with(['contractor', 'items.category'])
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (Document $document) => $document->items->isEmpty()
                ? [Line::make($document, null)]
                : $document->items->map(fn ($item) => Line::make($document, $item)))
            ->map(fn (Line $line) => [
                'document_id' => $line->document->id,
                'date' => $line->document->issue_date?->format('Y-m-d'),
                'number' => $line->document->number,
                'source' => $line->document->source->getLabel(),
                'pending' => $line->document->included === null,
                'contractor' => $line->contractorName(),
                'name' => $line->name(),
                'category' => $line->category?->name,
                'treatment' => $line->treatment->value,
                'treatment_label' => $line->treatment->getLabel(),
                'net' => $line->amounts->net,
                'gross' => $line->gross,
                'pit_cost' => $line->treatment === Treatment::Cost ? $line->amounts->pitCost : 0.0,
            ])
            ->values()
            ->all();
    }

    private function pendingCount(string $period): int
    {
        return Document::pending()->where('direction', Direction::Cost)->where('period', $period)->count();
    }

    /** Dochód rzeczywisty miesięcy od stycznia do miesiąca poprzedzającego. */
    private function previousMonths(string $period): array
    {
        $periods = array_slice(Period::yearToDate($period), 0, -1);
        $totals = $periods ? $this->settlement->totals($periods) : [];
        $active = array_filter($totals, fn (PeriodTotals $t) => $t->revenue != 0 || $t->costs != 0);
        $income = round(array_sum(array_map(fn (PeriodTotals $t) => $t->income(), $totals)), 2);

        return [
            'months' => array_map(fn (PeriodTotals $t) => ['period' => $t->period, 'label' => Period::label($t->period), 'income' => $t->income(), 'has_data' => isset($active[$t->period])], array_values($totals)),
            'income' => $income,
            'months_with_data' => count($active),
            'average' => count($active) ? round($income / count($active), 2) : null,
        ];
    }
}
