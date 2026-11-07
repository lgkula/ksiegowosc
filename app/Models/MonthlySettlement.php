<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlySettlement extends Model
{
    protected $fillable = ['period', 'other_income', 'other_income_note', 'account_balance', 'extra_reserve', 'closed_at', 'snapshot', 'notes'];

    protected function casts(): array
    {
        return [
            'other_income' => 'decimal:2',
            'account_balance' => 'decimal:2',
            'extra_reserve' => 'decimal:2',
            'closed_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public static function forPeriod(string $period): self
    {
        return static::firstOrNew(['period' => $period]);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
