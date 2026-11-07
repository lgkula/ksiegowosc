<?php

namespace App\Services\Ksef;

use App\Enums\Direction;
use App\Enums\DocumentKind;
use App\Enums\DocumentSource;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\KsefSyncLog;
use App\Services\DocumentProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class KsefImporter
{
    public const PURCHASES = 'Subject2';

    public const SALES = 'Subject1';

    public function __construct(
        private FaXmlParser $parser,
        private DocumentProcessor $processor,
        private ?KsefClient $client = null,
    ) {}

    /** Klient tworzony przy pierwszym użyciu, żeby zapis z XML działał bez konfiguracji KSeF. */
    private function client(): KsefClient
    {
        return $this->client ??= KsefClient::fromSettings();
    }

    /**
     * Pobiera faktury z zakresu dat, miesiąc po miesiącu. Faktury już zapisane (po numerze KSeF) są pomijane.
     *
     * @return array{found:int, imported:int, errors:string[]}
     */
    public function import(CarbonImmutable $from, CarbonImmutable $to, string $subjectType = self::PURCHASES): array
    {
        @set_time_limit(0);

        $result = ['found' => 0, 'imported' => 0, 'errors' => []];

        try {
            for ($start = $from; $start <= $to; $start = $start->addMonthNoOverflow()->startOfMonth()) {
                $end = $start->endOfMonth()->min($to);
                $this->importRange($start, $end, $subjectType, $result);
            }
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
        }

        KsefSyncLog::create([
            'date_from' => $from,
            'date_to' => $to,
            'subject_type' => $subjectType,
            'found' => $result['found'],
            'imported' => $result['imported'],
            'status' => $result['errors'] ? 'error' : 'ok',
            'error' => $result['errors'] ? implode("\n", array_slice($result['errors'], 0, 20)) : null,
        ]);

        return $result;
    }

    private function importRange(CarbonImmutable $from, CarbonImmutable $to, string $subjectType, array &$result): void
    {
        $offset = 0;

        do {
            $page = $this->client()->queryMetadata($subjectType, $from, $to, $offset);
            $invoices = $page['invoices'] ?? [];
            $result['found'] += count($invoices);

            $known = Document::whereIn('ksef_number', array_column($invoices, 'ksefNumber'))->pluck('ksef_number')->all();

            foreach ($invoices as $meta) {
                if (in_array($meta['ksefNumber'], $known, true)) {
                    continue;
                }
                try {
                    $this->importInvoice($meta, $subjectType);
                    $result['imported']++;
                } catch (Throwable $e) {
                    $result['errors'][] = $meta['ksefNumber'].': '.$e->getMessage();
                }
            }

            $offset += count($invoices);
        } while (($page['hasMore'] ?? false) && ! ($page['isTruncated'] ?? false) && $invoices !== []);

        if ($page['isTruncated'] ?? false) {
            $result['errors'][] = "Zakres {$from->toDateString()} – {$to->toDateString()} ma więcej niż 10 000 faktur; zawęź zakres.";
        }
    }

    public function importInvoice(array $meta, string $subjectType): Document
    {
        $xml = $this->client()->downloadInvoice($meta['ksefNumber']);

        return $this->storeInvoice($xml, $meta['ksefNumber'], $subjectType);
    }

    /** Zapisuje fakturę z XML (używane też w testach i przy imporcie z pliku). */
    public function storeInvoice(string $xml, string $ksefNumber, string $subjectType): Document
    {
        $data = $this->parser->parse($xml);
        $isPurchase = $subjectType === self::PURCHASES;
        $party = $isPurchase ? $data['seller'] : $data['buyer'];

        return DB::transaction(function () use ($data, $xml, $ksefNumber, $isPurchase, $party) {
            $document = Document::create([
                'direction' => $isPurchase ? Direction::Cost : Direction::Revenue,
                'source' => DocumentSource::Ksef,
                'kind' => DocumentKind::Invoice,
                'ksef_number' => $ksefNumber,
                'number' => $data['number'],
                'issue_date' => $data['issue_date'],
                'sale_date' => $data['sale_date'],
                'contractor_id' => $this->contractorFor($party)?->id,
                'net' => $data['net'],
                'vat' => $data['vat'],
                'gross' => $data['gross'],
                'currency' => $data['currency'],
                // Zakupy czekają na decyzję, sprzedaż od razu trafia do przychodów.
                'included' => $isPurchase ? null : true,
                'xml' => $xml,
                'notes' => str_starts_with($data['type'], 'KOR') ? 'Faktura korygująca' : null,
            ]);

            foreach ($data['items'] as $item) {
                $document->items()->create($item + ['category_source' => 'auto']);
            }

            $this->processor->afterSave($document);

            return $document;
        });
    }

    private function contractorFor(array $party): ?Contractor
    {
        $nip = Contractor::normalizeNip($party['nip'] ?? null);
        $name = $party['name'] ?: ($nip ? 'NIP '.$nip : null);

        if (! $name) {
            return null;
        }

        if ($nip && ($existing = Contractor::where('nip', $nip)->first())) {
            return $existing;
        }

        return Contractor::create(['nip' => $nip, 'full_name' => $name, 'address' => $party['address'] ?? null]);
    }
}
