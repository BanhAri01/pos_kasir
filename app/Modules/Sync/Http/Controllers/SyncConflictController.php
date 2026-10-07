<?php

namespace App\Modules\Sync\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Modules\Sync\Models\SyncConflict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Perlu Dicek": hal yang muncul setelah kasir offline tersinkron (stok minus, data bermasalah). */
class SyncConflictController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);
        $timezone = $context->get()->timezone;

        $conflicts = SyncConflict::query()->unresolved()->with('outlet:id,name')->latest('id')->limit(100)->get()
            ->map(fn (SyncConflict $c) => [
                'id' => $c->id,
                'type' => $c->type,
                'message' => $c->message,
                'outlet' => $c->outlet?->name,
                'product_id' => $c->payload['product_id'] ?? null,
                'at' => $c->created_at->timezone($timezone)->locale('id')->translatedFormat('j M Y, H:i'),
            ]);

        return Inertia::render('Sync/Conflicts', ['conflicts' => $conflicts]);
    }

    public function resolve(Request $request, SyncConflict $conflict): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);
        $conflict->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id]);

        return back()->with('success', 'Sudah ditandai selesai dicek.');
    }
}
