<?php

namespace App\Services\Settlement;

use App\Enums\Treatment;
use App\Models\Document;
use App\Models\HealthMinimum;
use App\Models\Loan;
use App\Models\MonthlySettlement;
use App\Models\TaxParameter;
use App\Support\Period;
use Illuminate\Support\Collection;

class SettlementService
{
    public const PIT_DUE_DAY = 20;

    public const ZUS_DUE_DAY = 20;

    public const VAT_DUE_DAY = 25;

    /** @return Collection<int, Line> */
    public function lines(array $periods): Collection
    {
        return Document::included()
            ->whereIn('period', $periods)
            ->with(['contractor.group', 'items.category'])
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (Document $document) => $document->items->isEmpty()
                ? [Line::make($document, null)]
                : $document->items->map(fn ($item) => Line::make($document, $item)));
    }

    /** @return array<string, PeriodTotals> */
    public function totals(array $periods): array
    {
        $result = [];
        foreach ($periods as $period) {
            $result[$period] = new PeriodTotals($period);
        }

        foreach ($this->lines($periods) as $line) {
            $totals = $result[$line->period()];
            match ($line->treatment) {
                Treatment::Revenue => $this->addRevenue($totals, $line),
                Treatment::Cost => $this->addCost($totals, $line),
                Treatment::IncomeDeduction => $totals->incomeDeductions += $line->gross,
                Treatment::Health => $totals->healthPaid += $line->gross,
                Treatment::None => null,
            };
        }

        $otherIncome = MonthlySettlement::whereIn('period', $periods)->pluck('other_income', 'period');
        foreach ($otherIncome as $period => $amount) {
            $result[$period]->revenue += (float) $amount;
        }

        foreach ($result as $totals) {
            foreach (['revenue', 'costs', 'incomeDeductions', 'healthPaid', 'vatOutput', 'vatInput'] as $field) {
                $totals->{$field} = round($totals->{$field}, 2);
            }
        }

        return $result;
    }

    /** Pełne rozliczenie miesiąca. Zamknięty miesiąc zwraca zapisany snapshot. */
    public function monthly(string $period, bool $fresh = false): array
    {
        $settlement = MonthlySettlement::forPeriod($period);

        if (! $fresh && $settlement->isClosed() && $settlement->snapshot) {
            return ['closed' => true, 'closed_at' => $settlement->closed_at->format('Y-m-d H:i')] + $settlement->snapshot;
        }

        $calculator = new TaxCalculator(TaxParameter::forYear(Period::year($period)));
        $lines = $this->lines([$period]);

        $ytd = Period::yearToDate($period);
        $previous = Period::previous($period);
        $totalsByPeriod = $this->totals(array_values(array_unique([...$ytd, $previous])));
        $totals = $totalsByPeriod[$period];

        $pit = $this->pit($calculator, $ytd, $totalsByPeriod);
        $zus = $calculator->socialContributions();
        $health = $calculator->health(
            $totalsByPeriod[$previous]->healthBaseIncome(),
            (float) HealthMinimum::forPeriod($period)->base,
        );
        $vat = $this->vat($period, $totals);

        $loans = Loan::activeIn($period)->orderBy('name')->get()
            ->map(fn (Loan $loan) => ['name' => $loan->name, 'amount' => (float) $loan->installment, 'due_day' => $loan->due_day])
            ->all();

        $obligations = [
            ['name' => 'Składki ZUS społeczne', 'amount' => $zus['total'], 'due' => Period::dueDate($period, self::ZUS_DUE_DAY)->format('Y-m-d')],
            ['name' => 'Składka zdrowotna', 'amount' => $health['amount'], 'due' => Period::dueDate($period, self::ZUS_DUE_DAY)->format('Y-m-d')],
            ['name' => 'Zaliczka PIT', 'amount' => (float) $pit['advance'], 'due' => Period::dueDate($period, self::PIT_DUE_DAY)->format('Y-m-d')],
            ['name' => 'VAT', 'amount' => (float) $vat['to_pay'], 'due' => Period::dueDate($period, self::VAT_DUE_DAY)->format('Y-m-d')],
        ];
        foreach ($loans as $loan) {
            $obligations[] = ['name' => 'Rata: '.$loan['name'], 'amount' => $loan['amount'], 'due' => null];
        }
        if ((float) $settlement->extra_reserve > 0) {
            $obligations[] = ['name' => 'Dodatkowa rezerwa', 'amount' => (float) $settlement->extra_reserve, 'due' => null];
        }

        $toKeep = round(array_sum(array_column($obligations, 'amount')), 2);
        $balance = $settlement->account_balance !== null ? (float) $settlement->account_balance : null;

        $cumulativeIncome = round(array_sum(array_map(fn (PeriodTotals $t) => $t->income(), array_intersect_key($totalsByPeriod, array_flip($ytd)))), 2);

        return [
            'period' => $period,
            'closed' => false,
            'revenue_lines' => $lines->where('treatment', Treatment::Revenue)->map(fn (Line $l) => $this->lineRow($l))->values()->all(),
            'other_income' => (float) $settlement->other_income,
            'other_income_note' => $settlement->other_income_note,
            'dedicated_cost_groups' => $this->groupCosts($lines->filter(fn (Line $l) => $this->isDedicated($l)), fn (Line $l) => $l->contractorName()),
            'cost_groups' => $this->groupCosts($lines->filter(fn (Line $l) => $l->document->direction->value === 'cost' && ! $this->isDedicated($l)), fn (Line $l) => $this->groupLabel($l)),
            'totals' => $totals->toArray() + ['income' => $totals->income()],
            'cumulative_income' => $cumulativeIncome,
            'pit' => $pit,
            'zus' => $zus,
            'health' => $health,
            'vat' => $vat,
            'cash' => [
                'balance' => $balance,
                'obligations' => $obligations,
                'to_keep' => $toKeep,
                'to_transfer' => $balance === null ? null : round(max(0, $balance - $toKeep), 2),
                'shortfall' => $balance === null ? null : round(max(0, $toKeep - $balance), 2),
            ],
            'uncategorized' => $lines->filter(fn (Line $l) => $l->isUncategorized() && $l->item !== null)->count(),
            'pending_documents' => Document::pending()->where('period', $period)->count(),
        ];
    }

    /** Zamyka miesiąc, zapisując wyliczenia na stałe. */
    public function close(string $period): MonthlySettlement
    {
        $settlement = MonthlySettlement::forPeriod($period);
        $settlement->snapshot = $this->monthly($period, fresh: true);
        $settlement->closed_at = now();
        $settlement->save();

        return $settlement;
    }

    public function reopen(string $period): void
    {
        MonthlySettlement::where('period', $period)->update(['closed_at' => null, 'snapshot' => null]);
    }

    /** Zestawienie roczne: przychody, wydatki, dochód, dochód narastający dla każdego miesiąca. */
    public function yearly(int $year): array
    {
        $rows = [];
        $cumulative = 0;

        foreach ($this->totals(Period::ofYear($year)) as $period => $totals) {
            $cumulative = round($cumulative + $totals->income(), 2);
            $rows[] = [
                'period' => $period,
                'label' => Period::label($period),
                'revenue' => $totals->revenue,
                'costs' => $totals->costs,
                'income' => $totals->income(),
                'cumulative_income' => $cumulative,
                'has_data' => $totals->revenue != 0 || $totals->costs != 0,
            ];
        }

        $active = array_filter($rows, fn ($row) => $row['has_data']);
        $revenue = round(array_sum(array_column($rows, 'revenue')), 2);
        $costs = round(array_sum(array_column($rows, 'costs')), 2);

        return [
            'year' => $year,
            'rows' => $rows,
            'summary' => [
                'revenue' => $revenue,
                'costs' => $costs,
                'income' => round($revenue - $costs, 2),
                'months_with_data' => count($active),
                'average_income' => count($active) ? round(($revenue - $costs) / count($active), 2) : 0,
            ],
        ];
    }

    private function pit(TaxCalculator $calculator, array $ytd, array $totalsByPeriod): array
    {
        $cumulativeIncome = 0;
        $cumulativeDeductions = 0;
        $advances = 0;
        $result = null;

        foreach ($ytd as $period) {
            $cumulativeIncome += $totalsByPeriod[$period]->income();
            $cumulativeDeductions += $totalsByPeriod[$period]->incomeDeductions;
            $result = $calculator->pitAdvance($cumulativeIncome, $cumulativeDeductions, $advances);
            $advances += $result['advance'];
        }

        return $result + [
            'cumulative_income' => round($cumulativeIncome, 2),
            'cumulative_deductions' => round($cumulativeDeductions, 2),
        ];
    }

    /** VAT za miesiąc z uwzględnieniem nadwyżki przeniesionej z poprzednich miesięcy. */
    private function vat(string $period, PeriodTotals $totals): array
    {
        $first = Document::included()->min('period') ?? $period;
        $carry = 0.0;

        if ($first < $period) {
            $periods = [];
            for ($p = $first; $p < $period; $p = Period::next($p)) {
                $periods[] = $p;
            }
            foreach ($this->totals($periods) as $t) {
                $carry = max(0, $carry + $t->vatInput - $t->vatOutput);
            }
        }

        $balance = round($totals->vatOutput - $totals->vatInput - $carry, 2);

        return [
            'output' => $totals->vatOutput,
            'input' => $totals->vatInput,
            'carried_in' => round($carry, 2),
            'to_pay' => (int) max(0, round($balance)),
            'carried_out' => round(max(0, -$balance), 2),
        ];
    }

    private function addRevenue(PeriodTotals $totals, Line $line): void
    {
        $totals->revenue += $line->amounts->net;
        $totals->vatOutput += $line->amounts->vat;
    }

    private function addCost(PeriodTotals $totals, Line $line): void
    {
        $totals->costs += $line->amounts->pitCost;
        $totals->vatInput += $line->amounts->vatDeductible;
    }

    private function isDedicated(Line $line): bool
    {
        return $line->document->direction->value === 'cost' && (bool) $line->document->contractor?->own_settlement_section;
    }

    private function groupLabel(Line $line): string
    {
        return $line->category?->name ?? 'Bez kategorii';
    }

    private function groupCosts(Collection $lines, callable $groupBy): array
    {
        return $lines->groupBy($groupBy)
            ->map(function (Collection $group, string $name) {
                $rows = $group->map(fn (Line $l) => $this->lineRow($l))->values()->all();

                return [
                    'name' => $name,
                    'rows' => $rows,
                    'net' => round(array_sum(array_column($rows, 'net')), 2),
                    'vat' => round(array_sum(array_column($rows, 'vat')), 2),
                    'gross' => round(array_sum(array_column($rows, 'gross')), 2),
                    'pit_cost' => round(array_sum(array_column($rows, 'pit_cost')), 2),
                    'vat_deductible' => round(array_sum(array_column($rows, 'vat_deductible')), 2),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function lineRow(Line $line): array
    {
        return [
            'document_id' => $line->document->id,
            'number' => $line->document->number,
            'date' => $line->document->issue_date?->format('Y-m-d'),
            'contractor' => $line->contractorName(),
            'contractor_group' => $line->document->contractor?->groupName(),
            'name' => $line->name(),
            'category' => $line->category?->name,
            'treatment' => $line->treatment->value,
            'treatment_label' => $line->treatment->getLabel(),
            'pit_percent' => $line->treatment === Treatment::Cost ? (float) ($line->category?->pit_percent ?? 100) : null,
            'vat_percent' => $line->treatment === Treatment::Cost ? (float) ($line->category?->vat_percent ?? 100) : null,
            'net' => $line->amounts->net,
            'vat' => $line->amounts->vat,
            'gross' => $line->gross,
            'pit_cost' => $line->amounts->pitCost,
            'vat_deductible' => $line->amounts->vatDeductible,
        ];
    }
}
