<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerSyncLog extends Model
{
    protected $fillable = [
        'started_at',
        'finished_at',
        'status',
        'total_received',
        'created_count',
        'updated_count',
        'inactive_count',
        'warning_count',
        'error_count',
        'error_message',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total_received' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'inactive_count' => 'integer',
            'warning_count' => 'integer',
            'error_count' => 'integer',
            'details' => 'array',
        ];
    }
}
