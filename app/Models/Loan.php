<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = ['name', 'installment', 'from_period', 'to_period', 'due_day'];

    protected function casts(): array
    {
        return [
            'installment' => 'decimal:2',
        ];
    }

    public function scopeActiveIn(Builder $query, string $period): Builder
    {
        return $query->where('from_period', '<=', $period)
            ->where(fn (Builder $q) => $q->whereNull('to_period')->orWhere('to_period', '>=', $period));
    }
}
