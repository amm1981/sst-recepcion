<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalDocumentFile extends Model
{
    protected $fillable = [
        'medical_document_id',
        'file_type',
        'original_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function medicalDocument()
    {
        return $this->belongsTo(MedicalDocument::class);
    }
}
