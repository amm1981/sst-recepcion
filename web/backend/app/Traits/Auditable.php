<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            $model->audit('CREATED');
        });

        static::updated(function ($model) {
            $model->audit('UPDATED');
        });

        static::deleted(function ($model) {
            $model->audit('DELETED');
        });
    }

    protected function audit($action)
    {
        if (app()->runningInConsole()) {
            return;
        }

        $oldValues = [];
        $newValues = [];

        if ($action === 'UPDATED') {
            $newValues = $this->getChanges();
            foreach ($newValues as $key => $value) {
                $oldValues[$key] = $this->getOriginal($key);
            }
        } elseif ($action === 'CREATED') {
            $newValues = $this->getAttributes();
        } elseif ($action === 'DELETED') {
            $oldValues = $this->getAttributes();
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity' => get_class($this),
            'entity_id' => $this->id,
            'metadata' => [
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
            'ip_address' => Request::ip(),
        ]);
    }
}
