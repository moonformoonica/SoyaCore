<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use App\Http\Resources\MenuResource;
use App\Models\Kategori;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    /**
     * GET /api/menu — PUBLIK (kontrak v1, konsumen: SoyaScan).
     * Hanya menu aktif, dikelompokkan per kategori.
     */
    public function katalog(): JsonResponse
    {
        $kategori = Kategori::with([
            'menu' => fn ($q) => $q->where('is_active', true)->orderBy('nama')->orderBy('ukuran'),
        ])->orderBy('nama')->get();

        return response()->json([
            'kategori' => $kategori
                ->filter(fn ($k) => $k->menu->isNotEmpty())
                ->values()
                ->map(fn ($k) => [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'menu' => $k->menu->map(fn ($m) => [
                        'id' => $m->id,
                        'nama' => $m->nama,
                        'rasa' => $m->rasa,
                        'ukuran' => $m->ukuran,
                        'harga' => $m->harga,
                    ])->values(),
                ]),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Menu::with('kategori');

        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->query('kategori_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return MenuResource::collection($query->orderBy('nama')->orderBy('rasa')->get());
    }

    public function store(StoreMenuRequest $request): MenuResource
    {
        $menu = Menu::create($request->validated());

        return new MenuResource($menu->load('kategori'));
    }

    public function show(Menu $menu): MenuResource
    {
        return new MenuResource($menu->load('kategori'));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): MenuResource
    {
        $menu->update($request->validated());

        return new MenuResource($menu->load('kategori'));
    }

    public function destroy(Menu $menu): JsonResponse
    {
        // Menu yang sudah pernah dipakai transaksi tidak dihapus permanen
        // (menghindari FK constraint & menjaga histori) — dinonaktifkan saja.
        if ($menu->detailTransaksi()->exists()) {
            $menu->update(['is_active' => false]);

            return response()->json([
                'message' => 'Menu pernah dipakai transaksi, jadi dinonaktifkan (bukan dihapus permanen).',
            ]);
        }

        $menu->delete();

        return response()->json(['message' => 'Menu berhasil dihapus.']);
    }
}
