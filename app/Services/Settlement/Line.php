<?php

namespace App\Services\Settlement;

use App\Enums\Direction;
use App\Enums\Treatment;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentItem;

/** Jedna pozycja dokumentu ujęta w rozliczeniu, z kwotami po zastosowaniu kategorii. */
final readonly class Line
{
    public function __construct(
        public Document $document,
        public ?DocumentItem $item,
        public ?Category $category,
        public Treatment $treatment,
        public ItemAmounts $amounts,
        public float $gross,
    ) {}

    public static function make(Document $document, ?DocumentItem $item): self
    {
        $category = $item?->category;
        $net = (float) ($item?->net ?? $document->net);
        $vat = (float) ($item?->vat ?? $document->vat);
        $gross = (float) ($item?->gross ?? $document->gross);

        if ($document->direction === Direction::Revenue) {
            $treatment = $category?->treatment === Treatment::None ? Treatment::None : Treatment::Revenue;

            return new self($document, $item, $category, $treatment, new ItemAmounts($net, $vat, 0, 0), $gross);
        }

        $treatment = $category?->treatment ?? Treatment::Cost;
        $amounts = $treatment === Treatment::Cost
            ? ItemAmounts::forCost($net, $vat, (float) ($category?->pit_percent ?? 100), (float) ($category?->vat_percent ?? 100))
            : new ItemAmounts($net, $vat, 0, 0);

        return new self($document, $item, $category, $treatment, $amounts, $gross);
    }

    public function period(): string
    {
        return $this->document->period;
    }

    public function name(): string
    {
        return $this->item?->name ?? ($this->document->notes ?: 'Dokument '.$this->document->number);
    }

    public function contractorName(): string
    {
        return $this->document->contractor?->displayName() ?? '—';
    }

    public function isUncategorized(): bool
    {
        return $this->category === null;
    }
}
