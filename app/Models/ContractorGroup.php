<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractorGroup extends Model
{
    protected $fillable = ['name', 'pattern'];

    public function contractors(): HasMany
    {
        return $this->hasMany(Contractor::class, 'group_id');
    }

    public function matches(string $contractorName): bool
    {
        $name = mb_strtolower($contractorName);

        foreach (explode(';', (string) $this->pattern) as $fragment) {
            $fragment = mb_strtolower(trim($fragment));
            if ($fragment !== '' && str_contains($name, $fragment)) {
                return true;
            }
        }

        return false;
    }

    public static function guessFor(string $contractorName): ?self
    {
        return static::whereNotNull('pattern')->get()->first(fn (self $group) => $group->matches($contractorName));
    }
}
