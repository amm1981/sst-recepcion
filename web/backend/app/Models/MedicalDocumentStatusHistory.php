<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalDocumentStatusHistory extends Model
{
    protected $table = 'medical_document_status_history';

    protected $fillable = [
        'medical_document_id',
        'from_status',
        'to_status',
        'observation',
        'changed_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
