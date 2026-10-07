<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Services\ProductQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian barang (JSON) untuk form stok masuk/keluar, kirim stok, dan belanja.
 * Bisa cari nama, kode barang, atau scan barcode.
 */
class ProductSearchController extends Controller
{
    public function __invoke(Request $request, CurrentOutlet $outlet): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can(Permission::ManageProducts->value) || $user->can(Permission::ManageStock->value) || $user->can(Permission::UsePos->value),
            403
        );

        $outletId = $request->integer('outlet_id') ?: $outlet->id($user);
        abort_unless($outlet->accessible($user)->contains('id', $outletId), 403);

        $products = ProductQuery::search(ProductQuery::forOutlet($outletId), $request->string('q')->toString())
            ->when($request->boolean('stock_only'), fn ($q) => $q->where('track_stock', true)->whereNotIn('type', ['service', 'package', 'membership']))
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['data' => ProductResource::collection($products)->resolve()]);
    }
}
