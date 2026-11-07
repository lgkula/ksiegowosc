<?php

namespace App\Services\Ksef;

use App\Models\DocumentItem;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Parsuje e-fakturę w strukturze FA(2)/FA(3) do prostej tablicy.
 * Ścieżki są niezależne od wersji przestrzeni nazw (local-name()).
 */
class FaXmlParser
{
    /** Pola sum faktury dla stawek: [netto, VAT]. */
    private const RATE_FIELDS = [
        '23' => ['P_13_1', 'P_14_1'], '22' => ['P_13_1', 'P_14_1'],
        '8' => ['P_13_2', 'P_14_2'], '7' => ['P_13_2', 'P_14_2'],
        '5' => ['P_13_3', 'P_14_3'],
    ];

    private DOMXPath $xpath;

    public function parse(string $xml): array
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            throw new KsefException('Nieprawidłowy XML faktury.');
        }
        $this->xpath = new DOMXPath($dom);

        $fa = $this->node('/*[local-name()="Faktura"]/*[local-name()="Fa"]')
            ?? throw new KsefException('XML nie zawiera elementu Fa.');

        $type = $this->text('*[local-name()="RodzajFaktury"]', $fa) ?? 'VAT';
        $isCorrection = str_starts_with($type, 'KOR');

        $items = [];
        foreach ($this->nodes('*[local-name()="FaWiersz"]', $fa) as $i => $row) {
            $items[] = $this->parseItem($row, $i + 1, $isCorrection);
        }

        $totals = [
            'net' => 0.0,
            'vat' => 0.0,
            'gross' => (float) ($this->text('*[local-name()="P_15"]', $fa) ?? 0),
        ];
        foreach (array_unique(array_merge(...array_values(self::RATE_FIELDS))) as $field) {
            $value = (float) ($this->text('*[local-name()="'.$field.'"]', $fa) ?? 0);
            str_starts_with($field, 'P_13') ? $totals['net'] += $value : $totals['vat'] += $value;
        }
        foreach (['P_13_4', 'P_13_5', 'P_13_6_1', 'P_13_6_2', 'P_13_6_3', 'P_13_7', 'P_13_8', 'P_13_9', 'P_13_10', 'P_13_11'] as $field) {
            $totals['net'] += (float) ($this->text('*[local-name()="'.$field.'"]', $fa) ?? 0);
        }

        if ($items === []) {
            $items[] = [
                'position' => 1,
                'name' => $isCorrection ? 'Korekta faktury' : 'Faktura (bez pozycji)',
                'qty' => 1, 'unit' => null, 'unit_net' => null,
                'vat_rate' => $totals['net'] > 0 ? (string) round($totals['vat'] / $totals['net'] * 100) : 'np',
                'net' => round($totals['net'], 2), 'vat' => round($totals['vat'], 2), 'gross' => round($totals['gross'], 2),
            ];
        } else {
            $items = $this->reconcileVat($items, $fa);
        }

        return [
            'type' => $type,
            'number' => $this->text('*[local-name()="P_2"]', $fa),
            'issue_date' => $this->text('*[local-name()="P_1"]', $fa),
            'sale_date' => $this->text('*[local-name()="P_6"]', $fa),
            'currency' => $this->text('*[local-name()="KodWaluty"]', $fa) ?? 'PLN',
            'seller' => $this->party('Podmiot1'),
            'buyer' => $this->party('Podmiot2'),
            'net' => round($totals['net'], 2),
            'vat' => round($totals['vat'], 2),
            'gross' => round($totals['gross'], 2),
            'items' => $items,
        ];
    }

    private function parseItem(DOMElement $row, int $position, bool $isCorrection): array
    {
        $rate = $this->normalizeRate($this->text('*[local-name()="P_12"]', $row));
        $qty = (float) ($this->text('*[local-name()="P_8B"]', $row) ?? 1);
        $net = $this->text('*[local-name()="P_11"]', $row);
        $gross = $this->text('*[local-name()="P_11A"]', $row);
        $vat = $this->text('*[local-name()="P_11Vat"]', $row);

        if ($net !== null) {
            $net = (float) $net;
            $vat = $vat !== null ? (float) $vat : round($net * DocumentItem::rateFraction($rate), 2);
            $amounts = ['net' => round($net, 2), 'vat' => round($vat, 2), 'gross' => round($net + $vat, 2)];
        } elseif ($gross !== null) {
            $amounts = DocumentItem::splitGross((float) $gross, $rate);
        } else {
            $unitNet = (float) ($this->text('*[local-name()="P_9A"]', $row) ?? 0);
            $net = round($unitNet * $qty, 2);
            $amounts = ['net' => $net, 'vat' => round($net * DocumentItem::rateFraction($rate), 2)];
            $amounts['gross'] = round($amounts['net'] + $amounts['vat'], 2);
        }

        // W korekcie wiersze "stan przed" odejmujemy, "stan po" dodajemy.
        if ($isCorrection && $this->text('*[local-name()="StanPrzed"]', $row) === '1') {
            $amounts = array_map(fn ($v) => -$v, $amounts);
        }

        return [
            'position' => $position,
            'name' => mb_substr($this->text('*[local-name()="P_7"]', $row) ?? 'Pozycja '.$position, 0, 255),
            'qty' => $qty,
            'unit' => $this->text('*[local-name()="P_8A"]', $row),
            'unit_net' => ($p = $this->text('*[local-name()="P_9A"]', $row)) !== null ? (float) $p : null,
            'vat_rate' => $rate,
        ] + $amounts;
    }

    /** Wyrównuje VAT pozycji do sum faktury (różnice groszowe przy liczeniu VAT od sumy). */
    private function reconcileVat(array $items, DOMElement $fa): array
    {
        foreach (array_unique(array_column(self::RATE_FIELDS, 1)) as $vatField) {
            $headerVat = $this->text('*[local-name()="'.$vatField.'"]', $fa);
            if ($headerVat === null) {
                continue;
            }
            $rates = array_keys(array_filter(self::RATE_FIELDS, fn ($f) => $f[1] === $vatField));
            $keys = array_keys(array_filter($items, fn ($i) => in_array($i['vat_rate'], array_map('strval', $rates), true)));
            if ($keys === []) {
                continue;
            }
            $diff = round((float) $headerVat - array_sum(array_map(fn ($k) => $items[$k]['vat'], $keys)), 2);
            if ($diff != 0 && abs($diff) <= 0.05 * count($keys)) {
                $largest = collect($keys)->sortByDesc(fn ($k) => abs($items[$k]['net']))->first();
                $items[$largest]['vat'] = round($items[$largest]['vat'] + $diff, 2);
                $items[$largest]['gross'] = round($items[$largest]['net'] + $items[$largest]['vat'], 2);
            }
        }

        return $items;
    }

    private function party(string $name): array
    {
        $node = $this->node('/*[local-name()="Faktura"]/*[local-name()="'.$name.'"]');
        if (! $node) {
            return ['nip' => null, 'name' => null, 'address' => null];
        }

        $address = array_filter([
            $this->text('*[local-name()="Adres"]/*[local-name()="AdresL1"]', $node),
            $this->text('*[local-name()="Adres"]/*[local-name()="AdresL2"]', $node),
        ]);

        return [
            'nip' => $this->text('*[local-name()="DaneIdentyfikacyjne"]/*[local-name()="NIP"]', $node),
            'name' => $this->text('*[local-name()="DaneIdentyfikacyjne"]/*[local-name()="Nazwa"]', $node),
            'address' => $address ? implode(', ', $address) : null,
        ];
    }

    private function normalizeRate(?string $rate): string
    {
        $rate = strtolower(trim((string) $rate));

        return match (true) {
            $rate === '' => 'np',
            str_starts_with($rate, 'zw') => 'zw',
            str_starts_with($rate, 'np') || str_starts_with($rate, 'oo') => 'np',
            str_starts_with($rate, '0') => '0',
            default => (string) (int) $rate,
        };
    }

    private function node(string $query, ?DOMElement $context = null): ?DOMElement
    {
        $node = $this->xpath->query($query, $context)->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /** @return DOMElement[] */
    private function nodes(string $query, ?DOMElement $context = null): array
    {
        return array_values(array_filter(iterator_to_array($this->xpath->query($query, $context)), fn ($n) => $n instanceof DOMElement));
    }

    private function text(string $query, ?DOMElement $context = null): ?string
    {
        $value = $this->node($query, $context)?->textContent;

        return $value === null ? null : trim($value);
    }
}
