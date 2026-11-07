<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeForecast extends Model
{
    protected $fillable = ['period', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
