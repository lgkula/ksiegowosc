<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\DocumentKind;
use App\Enums\DocumentSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'direction', 'source', 'kind', 'ksef_number', 'number', 'issue_date', 'sale_date', 'period',
        'contractor_id', 'net', 'vat', 'gross', 'currency', 'net_currency', 'vat_currency', 'gross_currency',
        'exchange_rate', 'exchange_rate_date', 'exchange_rate_table', 'included', 'xml', 'notes', 'copied_from_id',
    ];

    protected $hidden = ['xml'];

    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'source' => DocumentSource::class,
            'kind' => DocumentKind::class,
            'issue_date' => 'date',
            'sale_date' => 'date',
            'net' => 'decimal:2',
            'vat' => 'decimal:2',
            'gross' => 'decimal:2',
            'net_currency' => 'decimal:2',
            'vat_currency' => 'decimal:2',
            'gross_currency' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'exchange_rate_date' => 'date',
            'included' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Document $document) {
            if (! $document->period && $document->issue_date) {
                $document->period = $document->issue_date->format('Y-m');
            }
        });
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class)->orderBy('position');
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    /** Dokument ręczny w walucie obcej: pozycje wpisane w walucie i przeliczane na PLN kursem NBP. */
    public function isForeignCurrency(): bool
    {
        return $this->source !== DocumentSource::Ksef && filled($this->currency) && $this->currency !== 'PLN';
    }

    public function scopeIncluded(Builder $query): Builder
    {
        return $query->where('included', true);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('included');
    }

    /**
     * Przelicza sumy dokumentu na podstawie pozycji. W dokumencie walutowym najpierw
     * przelicza pozycje na PLN: kwota w walucie × kurs, zaokrąglona do grosza.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        if ($this->isForeignCurrency()) {
            $rate = (float) $this->exchange_rate;
            foreach ($items as $item) {
                foreach (['net', 'vat', 'gross'] as $field) {
                    $item->{$field} = round((float) $item->{"{$field}_currency"} * $rate, 2);
                }
                $item->unit_net = (float) $item->qty > 0 ? round((float) $item->net / (float) $item->qty, 4) : null;
                $item->saveQuietly();
            }
            $this->net_currency = round((float) $items->sum('net_currency'), 2);
            $this->vat_currency = round((float) $items->sum('vat_currency'), 2);
            $this->gross_currency = round((float) $items->sum('gross_currency'), 2);
        } elseif ($this->source !== DocumentSource::Ksef) {
            $this->currency = 'PLN';
            $this->net_currency = $this->vat_currency = $this->gross_currency = null;
            $this->exchange_rate = $this->exchange_rate_date = $this->exchange_rate_table = null;
            $items->filter(fn (DocumentItem $item) => $item->net_currency !== null)
                ->each(fn (DocumentItem $item) => $item->forceFill(['net_currency' => null, 'vat_currency' => null, 'gross_currency' => null])->saveQuietly());
        }

        $this->net = round((float) $items->sum('net'), 2);
        $this->vat = round((float) $items->sum('vat'), 2);
        $this->gross = round((float) $items->sum('gross'), 2);
        $this->saveQuietly();
    }
}
