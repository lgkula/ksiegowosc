<?php

namespace App\Models;

use App\Enums\RuleField;
use App\Enums\RuleMatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryRule extends Model
{
    protected $fillable = ['field', 'match', 'pattern', 'category_id', 'priority', 'active'];

    protected function casts(): array
    {
        return [
            'field' => RuleField::class,
            'match' => RuleMatch::class,
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function matches(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $value = mb_strtolower(trim($value));
        $pattern = mb_strtolower(trim($this->pattern));

        return match ($this->match) {
            RuleMatch::Equals => $value === $pattern,
            RuleMatch::Contains => str_contains($value, $pattern),
            RuleMatch::Regex => @preg_match('~'.str_replace('~', '\~', $this->pattern).'~iu', $value) === 1,
        };
    }
}
