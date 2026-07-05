<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name,email')->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        
        if ($request->filled('entity')) {
            $query->where('entity', 'like', '%' . $request->entity . '%');
        }

        return response()->json($query->paginate(20));
    }
}
