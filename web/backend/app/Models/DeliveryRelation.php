<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRelation extends Model
{
    protected $fillable = ['name', 'code', 'requires_detail', 'is_active'];

    protected function casts(): array
    {
        return [
            'requires_detail' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
