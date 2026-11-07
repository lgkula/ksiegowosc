<?php

namespace App\Models;

use App\Enums\Direction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualTemplate extends Model
{
    protected $fillable = ['name', 'direction', 'contractor_id', 'item_name', 'category_id', 'vat_rate', 'default_unit'];

    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
