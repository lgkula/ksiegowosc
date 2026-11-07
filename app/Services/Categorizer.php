<?php

namespace App\Services;

use App\Enums\RuleField;
use App\Models\CategoryRule;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\DocumentItem;
use Illuminate\Support\Collection;

/**
 * Przypisuje kategorie pozycjom dokumentów.
 * Kolejność: reguła na nazwę towaru, reguła na NIP, reguła na nazwę kontrahenta, domyślna kategoria kontrahenta.
 * Pozycje z kategorią ustawioną ręcznie nie są zmieniane.
 */
class Categorizer
{
    private ?Collection $rules = null;

    public function categorizeDocument(Document $document): int
    {
        $document->loadMissing('contractor.group', 'items');
        $changed = 0;

        foreach ($document->items as $item) {
            if ($this->categorizeItem($item, $document->contractor)) {
                $changed++;
            }
        }

        return $changed;
    }

    public function categorizeItem(DocumentItem $item, ?Contractor $contractor): bool
    {
        if ($item->category_source === 'manual') {
            return false;
        }

        $categoryId = $this->resolve($item->name, $contractor);

        if ($categoryId === null || $categoryId === $item->category_id) {
            return false;
        }

        $item->category_id = $categoryId;
        $item->category_source = 'auto';
        $item->save();

        return true;
    }

    public function resolve(?string $itemName, ?Contractor $contractor): ?int
    {
        $candidates = [
            RuleField::ItemName->value => [$itemName],
            RuleField::ContractorNip->value => [$contractor?->nip],
            RuleField::ContractorName->value => array_filter([
                $contractor?->full_name,
                $contractor?->short_name,
                $contractor?->group?->name,
            ]),
        ];

        foreach ($candidates as $field => $values) {
            foreach ($this->rules()->get($field, collect()) as $rule) {
                foreach ($values as $value) {
                    if ($rule->matches($value)) {
                        return $rule->category_id;
                    }
                }
            }
        }

        return $contractor?->default_category_id;
    }

    /** Ponownie kategoryzuje dokumenty z podanych miesięcy. */
    public function recategorizePeriods(array $periods): int
    {
        $changed = 0;

        Document::whereIn('period', $periods)->with('contractor.group', 'items')->each(
            function (Document $document) use (&$changed) {
                $changed += $this->categorizeDocument($document);
            }
        );

        return $changed;
    }

    private function rules(): Collection
    {
        return $this->rules ??= CategoryRule::where('active', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CategoryRule $rule) => $rule->field->value);
    }
}
