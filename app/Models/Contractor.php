<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contractor extends Model
{
    protected $fillable = ['nip', 'full_name', 'short_name', 'group_id', 'default_category_id', 'own_settlement_section', 'address'];

    protected function casts(): array
    {
        return [
            'own_settlement_section' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Contractor $contractor) {
            $contractor->nip = static::normalizeNip($contractor->nip);
        });

        static::creating(function (Contractor $contractor) {
            $contractor->short_name ??= static::suggestShortName($contractor->full_name);
            $contractor->group_id ??= ContractorGroup::guessFor($contractor->full_name)?->id;
        });
    }

    /** Skraca pełną nazwę firmy: usuwa formę prawną i obcina do kilku słów. */
    public static function suggestShortName(string $fullName): string
    {
        $name = preg_replace(
            '/\b(sp(ółka|\.)?\s*z\s*o\.?\s*o\.?|spółka akcyjna|s\.?\s*a\.?|sp\.?\s*k\.?|sp\.?\s*j\.?|spółka jawna|spółka komandytowa)(?=\s|$|,)/iu',
            '',
            $fullName
        );
        $name = trim(preg_replace('/\s+/', ' ', trim((string) $name, " ,.-\"'")));
        $words = explode(' ', $name);

        return mb_substr(implode(' ', array_slice($words, 0, 3)), 0, 60) ?: $fullName;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ContractorGroup::class, 'group_id');
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->full_name;
    }

    public function groupName(): string
    {
        return $this->group?->name ?? $this->displayName();
    }

    public static function normalizeNip(?string $nip): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $nip);

        return $digits === '' ? null : $digits;
    }
}
