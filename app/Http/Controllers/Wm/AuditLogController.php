<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->input('action');
        $action = is_string($action) ? trim($action) : '';

        return Inertia::render('wm/audit/Index', [
            'logs' => AuditLog::query()
                ->when($action !== '', fn ($query) => $query->where('action', 'ilike', "%{$action}%"))
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['action' => $action],
        ]);
    }
}
