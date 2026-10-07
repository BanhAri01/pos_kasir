<?php

namespace App\Modules\Outlet\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Ganti outlet yang sedang dipakai (untuk usaha dengan beberapa cabang). */
class OutletSwitchController extends Controller
{
    public function __invoke(Request $request, CurrentOutlet $outlet): RedirectResponse
    {
        $request->validate(['outlet_id' => ['required', 'integer']]);

        if (! $outlet->switch($request->user(), $request->integer('outlet_id'))) {
            return back()->with('error', 'Anda tidak bertugas di outlet itu.');
        }

        return back()->with('success', "Sekarang memakai outlet {$outlet->get($request->user())->name}.");
    }
}
