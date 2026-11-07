<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KsefSyncLog extends Model
{
    protected $fillable = ['date_from', 'date_to', 'subject_type', 'found', 'imported', 'status', 'error'];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }
}
