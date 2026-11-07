<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Eksport do CSV (średnik, UTF-8 z BOM) - otwiera się poprawnie w polskim Excelu. */
class CsvExporter
{
    public static function documents(Builder $query): StreamedResponse
    {
        $filename = 'dokumenty-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Data', 'Miesiąc', 'Numer', 'Numer KSeF', 'Rodzaj', 'Źródło', 'Kontrahent', 'Grupa', 'NIP', 'W rozliczeniu',
                'Pozycja', 'Kategoria', 'Ilość', 'Stawka VAT', 'Netto', 'VAT', 'Brutto'], ';');

            $query->with(['contractor.group', 'items.category'])->chunk(200, function ($documents) use ($out) {
                foreach ($documents as $document) {
                    /** @var Document $document */
                    foreach ($document->items as $item) {
                        fputcsv($out, [
                            $document->issue_date?->format('Y-m-d'),
                            $document->period,
                            $document->number,
                            $document->ksef_number,
                            $document->direction->getLabel(),
                            $document->source->getLabel(),
                            $document->contractor?->displayName(),
                            $document->contractor?->group?->name,
                            $document->contractor?->nip,
                            match ($document->included) {
                                true => 'tak', false => 'nie', default => 'do decyzji'
                            },
                            $item->name,
                            $item->category?->name,
                            self::number($item->qty, 3),
                            $item->vat_rate,
                            self::number($item->net),
                            self::number($item->vat),
                            self::number($item->gross),
                        ], ';');
                    }
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function number(mixed $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', '');
    }
}
