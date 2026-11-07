<?php

namespace App\Services;

use App\Enums\Direction;
use App\Enums\DocumentKind;
use App\Enums\DocumentSource;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\ManualTemplate;
use App\Services\Nbp\NbpException;
use App\Services\Nbp\NbpRates;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DocumentProcessor
{
    public function __construct(private Categorizer $categorizer, private NbpRates $rates) {}

    /** Wywoływane po każdym zapisie dokumentu: kurs waluty, kategorie i sumy z pozycji. */
    public function afterSave(Document $document): void
    {
        if ($document->isForeignCurrency() && ! (float) $document->exchange_rate) {
            $this->applyExchangeRate($document);
            $document->saveQuietly();
        }

        $this->categorizer->categorizeDocument($document->fresh(['contractor.group', 'items']));

        if ($document->items()->exists()) {
            $document->recalculateTotals();
        }
    }

    /**
     * Tworzy dokument z szablonu (np. faktura za paliwo): kwota brutto, ilość, numer i data.
     *
     * @param  array{template_id:int, issue_date:string, number:?string, qty:?float, gross:float, period?:?string, notes?:?string}  $data
     */
    public function createFromTemplate(array $data): Document
    {
        $template = ManualTemplate::findOrFail($data['template_id']);
        $amounts = DocumentItem::splitGross((float) $data['gross'], $template->vat_rate);
        $qty = (float) ($data['qty'] ?: 1);

        return DB::transaction(function () use ($template, $amounts, $qty, $data) {
            $document = Document::create([
                'direction' => $template->direction ?? Direction::Cost,
                'source' => DocumentSource::Manual,
                'kind' => DocumentKind::Invoice,
                'number' => $data['number'] ?? null,
                'issue_date' => $data['issue_date'],
                'period' => $data['period'] ?? null,
                'contractor_id' => $template->contractor_id,
                'included' => true,
                'notes' => $data['notes'] ?? null,
            ] + $amounts);

            $document->items()->create([
                'position' => 1,
                'name' => $template->item_name,
                'qty' => $qty,
                'unit' => $template->default_unit,
                'unit_net' => $qty > 0 ? round($amounts['net'] / $qty, 4) : null,
                'vat_rate' => $template->vat_rate,
                'category_id' => $template->category_id,
                'category_source' => $template->category_id ? 'manual' : 'auto',
            ] + $amounts);

            $this->afterSave($document);

            return $document;
        });
    }

    /**
     * Ustawia na dokumencie kurs średni NBP z dnia roboczego przed datą wystawienia (bez zapisu).
     *
     * @throws NbpException
     */
    public function applyExchangeRate(Document $document): void
    {
        $rate = $this->rates->rateBefore($document->currency, $document->issue_date);

        $document->exchange_rate = $rate['rate'];
        $document->exchange_rate_date = $rate['date'];
        $document->exchange_rate_table = $rate['table'];
    }

    /**
     * Kopiuje dokumenty ręczne (np. stałe opłaty: OIRP, OpenAI, Anthropic) do innego miesiąca.
     * Daty przesuwają się o tyle miesięcy, ile dzieli miesiące rozliczenia, numer zostaje pusty,
     * a dokument walutowy dostaje kurs NBP dla nowej daty. Dokument już skopiowany do tego
     * miesiąca jest pomijany.
     *
     * @param  iterable<Document>  $documents
     * @return array{copied: list<Document>, skipped: list<string>, warnings: list<string>}
     */
    public function copyToPeriod(iterable $documents, string $targetPeriod): array
    {
        $result = ['copied' => [], 'skipped' => [], 'warnings' => []];

        foreach ($documents as $source) {
            $label = self::label($source);

            $reason = match (true) {
                $source->source === DocumentSource::Ksef => 'dokument z KSeF',
                $source->period === $targetPeriod => 'ten sam miesiąc',
                Document::where('copied_from_id', $source->id)->where('period', $targetPeriod)->exists() => 'już skopiowany',
                default => null,
            };
            if ($reason) {
                $result['skipped'][] = "{$label}: {$reason}";

                continue;
            }

            try {
                $result['copied'][] = $this->copyOne($source, $targetPeriod, $label, $result['warnings']);
            } catch (NbpException $e) {
                $result['skipped'][] = "{$label}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    /** @param list<string> $warnings */
    private function copyOne(Document $source, string $targetPeriod, string $label, array &$warnings): Document
    {
        $months = Period::monthsBetween($source->period, $targetPeriod);
        $shift = fn ($date) => $date ? CarbonImmutable::parse($date)->addMonthsNoOverflow($months) : null;

        $copy = $source->replicate(['ksef_number', 'number', 'xml', 'copied_from_id', 'exchange_rate', 'exchange_rate_date', 'exchange_rate_table']);
        $copy->fill([
            'source' => DocumentSource::Manual,
            'period' => $targetPeriod,
            'issue_date' => $shift($source->issue_date),
            'sale_date' => $shift($source->sale_date),
            'included' => true,
            'copied_from_id' => $source->id,
        ]);

        if ($copy->isForeignCurrency()) {
            try {
                $this->applyExchangeRate($copy);
            } catch (NbpException $e) {
                if (! (float) $source->exchange_rate) {
                    throw $e;
                }
                $copy->fill($source->only(['exchange_rate', 'exchange_rate_date', 'exchange_rate_table']));
                $warnings[] = "{$label}: {$e->getMessage()} Zostawiono kurs z dokumentu źródłowego.";
            }
        }

        return DB::transaction(function () use ($source, $copy) {
            $copy->save();

            foreach ($source->items as $item) {
                $copy->items()->create($item->replicate(['document_id'])->getAttributes());
            }

            $this->afterSave($copy);

            return $copy->refresh();
        });
    }

    /** Krótki opis dokumentu do list wyboru i komunikatów. */
    public static function label(Document $document): string
    {
        $parts = array_filter([$document->contractor?->displayName(), $document->items->first()?->name]);

        return $parts ? implode(' – ', $parts) : ($document->number ?: "dokument #{$document->id}");
    }
}
