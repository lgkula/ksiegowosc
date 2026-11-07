<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\Treatment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'direction', 'treatment', 'pit_percent', 'vat_percent', 'group', 'sort'];

    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'treatment' => Treatment::class,
            'pit_percent' => 'decimal:2',
            'vat_percent' => 'decimal:2',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }
}
