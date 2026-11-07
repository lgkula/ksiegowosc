<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentItem extends Model
{
    protected $fillable = [
        'document_id', 'position', 'name', 'qty', 'unit', 'unit_net', 'net', 'vat_rate', 'vat', 'gross',
        'net_currency', 'vat_currency', 'gross_currency', 'category_id', 'category_source',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_net' => 'decimal:4',
            'net' => 'decimal:2',
            'vat' => 'decimal:2',
            'gross' => 'decimal:2',
            'net_currency' => 'decimal:2',
            'vat_currency' => 'decimal:2',
            'gross_currency' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Stawka VAT jako ułamek, np. "23" -> 0.23, "zw" -> 0. */
    public static function rateFraction(?string $rate): float
    {
        return is_numeric($rate) ? ((float) $rate) / 100 : 0.0;
    }

    /** Rozbija kwotę brutto na netto i VAT dla danej stawki. */
    public static function splitGross(float $gross, ?string $rate): array
    {
        $net = round($gross / (1 + self::rateFraction($rate)), 2);

        return ['net' => $net, 'vat' => round($gross - $net, 2), 'gross' => round($gross, 2)];
    }
}
