<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Modules\Inventory\Services\RestockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestockController extends Controller
{
    public function __invoke(Request $request, RestockService $restock, CurrentOutlet $current, TenantContext $context): Response
    {
        $outletId = $current->id($request->user());

        return Inertia::render('Stock/Restock', [
            'groups' => $restock->shoppingList($outletId),
            'business' => $context->get()->name,
            'outlet' => Outlet::query()->whereKey($outletId)->value('name'),
        ]);
    }
}
