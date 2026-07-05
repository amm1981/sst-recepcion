<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class MedicalDocument extends Model
{
    use SoftDeletes, Auditable;

    public const STATUS_PENDING = 'PENDIENTE';
    public const STATUS_RECEIVED = 'RECEPCIONADO';
    public const STATUS_REGISTERED = 'REGISTRADO';
    public const STATUS_REJECTED = 'RECHAZADO';

    protected $fillable = [
        'medical_document_type_id',
        'worker_id',
        'delivery_relation_id',
        'delivery_relation_detail',
        'deliverer_name',
        'deliverer_document',
        'contact_number',
        'observation',
        'status',
        'created_by',
        'status_changed_by',
        'status_changed_at',
        'offline_uuid',
    ];

    protected function casts(): array
    {
        return ['status_changed_at' => 'datetime'];
    }

    public function type()
    {
        return $this->belongsTo(MedicalDocumentType::class, 'medical_document_type_id');
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }

    public function deliveryRelation()
    {
        return $this->belongsTo(DeliveryRelation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusChangedBy()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function files()
    {
        return $this->hasMany(MedicalDocumentFile::class);
    }

    public function history()
    {
        return $this->hasMany(MedicalDocumentStatusHistory::class)->latest();
    }
}
