<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Worker extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'dni',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'management_id',
        'sector_id',
        'is_active',
        'source',
        'hire_date',
        'termination_date',
        'external_updated_at',
        'external_payload',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'external_updated_at' => 'datetime',
            'external_payload' => 'array',
        ];
    }

    public function management()
    {
        return $this->belongsTo(Management::class);
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function medicalDocuments()
    {
        return $this->hasMany(MedicalDocument::class);
    }
}
