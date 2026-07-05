<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = ['user_id', 'mobile_device_id', 'direction', 'entity', 'status', 'payload', 'message'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
