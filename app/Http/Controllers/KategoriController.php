<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\StoreKategoriRequest;
use App\Http\Requests\UpdateKategoriRequest;
use App\Http\Resources\KategoriResource;
use App\Models\Kategori;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KategoriController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return KategoriResource::collection(Kategori::orderBy('nama')->get());
    }

    public function store(StoreKategoriRequest $request): KategoriResource
    {
        return new KategoriResource(Kategori::create($request->validated()));
    }

    public function show(Kategori $kategori): KategoriResource
    {
        return new KategoriResource($kategori->load('menu'));
    }

    public function update(UpdateKategoriRequest $request, Kategori $kategori): KategoriResource
    {
        $kategori->update($request->validated());

        return new KategoriResource($kategori);
    }

    public function destroy(Kategori $kategori): JsonResponse
    {
        if ($kategori->menu()->exists()) {
            throw new ApiException(
                'kategori_masih_dipakai',
                'Kategori tidak bisa dihapus karena masih memiliki menu terkait. Pindahkan atau hapus menu-nya dulu.',
                409,
            );
        }

        $kategori->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
