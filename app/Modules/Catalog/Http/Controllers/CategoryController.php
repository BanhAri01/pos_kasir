<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategori dibuat sesederhana mungkin: satu halaman, tambah / ganti nama / hapus.
 */
class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAllowed($request);

        return Inertia::render('Products/Categories', [
            'categories' => Category::query()
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAllowed($request);
        $data = $this->validated($request);

        $category = Category::create([
            'name' => $data['name'],
            'sort_order' => (int) Category::max('sort_order') + 1,
        ]);

        return back()->with('success', "Kategori {$category->name} sudah ditambahkan.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->ensureAllowed($request);
        $category->update($this->validated($request));

        return back()->with('success', 'Nama kategori sudah diganti.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->ensureAllowed($request);

        // Barang di kategori ini tidak ikut terhapus, hanya menjadi "tanpa kategori".
        $category->products()->update(['category_id' => null]);
        $category->delete();

        return back()->with('success', "Kategori {$category->name} sudah dihapus. Barangnya tetap ada.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [
            'name.required' => 'Nama kategori belum diisi.',
            'name.max' => 'Nama kategori terlalu panjang.',
        ]);
    }

    private function ensureAllowed(Request $request): void
    {
        abort_unless($request->user()->can(Permission::ManageProducts->value), 403);
    }
}
