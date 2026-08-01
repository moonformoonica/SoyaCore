<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use App\Http\Resources\MenuResource;
use App\Models\Kategori;
use App\Models\Menu;
use App\Support\GolonganUkuran;
use App\Support\OpsiMinuman;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    /**
     * Katalog publik SoyaScan.
     */
    public function katalog(): JsonResponse
    {
        $kategori = Kategori::with([
            'menu' => fn ($q) => $q->where('is_active', true)->orderBy('nama'),
        ])->orderBy('nama')->get();

        return response()->json([
            'kategori' => $kategori
                ->filter(fn ($k) => $k->menu->isNotEmpty())
                ->values()
                ->map(fn ($k) => [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'menu' => $this->urutkanUkuran($k->menu)->map(fn ($m) => [
                        'id' => $m->id,
                        'nama' => $m->nama,
                        'rasa' => $m->rasa,
                        'ukuran' => $m->ukuran,
                        'harga' => $m->harga,
                        // Frontend cukup membaca golongan + flag; ia tidak perlu
                        // tahu ukuran mana termasuk apa, dan tidak boleh
                        // menebaknya dari string.
                        'golongan_ukuran' => GolonganUkuran::dari($m->ukuran),
                        'bisa_pilih_sugar' => OpsiMinuman::bisaPilihSugar($m->ukuran),
                        'bisa_pilih_ice' => OpsiMinuman::bisaPilihIce($m->ukuran),
                        // Pemanis menu ini — dipakai frontend sebagai judul
                        // kelompok pilihan di atas tombol Normal/Less/No/Extra.
                        // Beda per menu: Gula Kelapa untuk sebagian besar,
                        // Special Madu Lemon untuk Honey Lemon, dst.
                        'pemanis' => OpsiMinuman::keteranganPemanis($m->rasa),
                    ])->values(),
                ]),
            'meta' => [
                // Daftar opsi dikirim dari sini supaya frontend merender
                // tombol/dropdown-nya dari satu sumber, tidak menyalin label
                // yang lalu lepas sinkron dengan validasi backend.
                // Label opsi hanya berisi aksinya (Normal/Less/No/Extra).
                // Nama pemanisnya ada di `menu[].pemanis` karena beda per menu —
                // lihat catatan di OpsiMinuman::SUGAR.
                'opsi_sugar' => OpsiMinuman::daftarSugar(),
                'opsi_ice' => OpsiMinuman::daftarIce(),
                'pemanis_bawaan' => OpsiMinuman::JENIS_GULA,
                'golongan_ukuran' => GolonganUkuran::semua(),
            ],
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

        $menu = $this->urutkanUkuran($query->orderBy('nama')->orderBy('rasa')->get());

        // Dipakai halaman Edit Menu untuk memisahkan kolom cup dan botol.
        // Difilter di PHP karena golongan diturunkan dari GolonganUkuran, bukan
        // dari kolom database — kalau ditulis sebagai WHERE, aturannya jadi ada
        // di dua tempat.
        if ($request->filled('golongan')) {
            $golongan = (string) $request->query('golongan');
            $menu = $menu->filter(fn (Menu $m) => GolonganUkuran::dari($m->ukuran) === $golongan)->values();
        }

        return MenuResource::collection($menu);
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

    /**
     * Mengurutkan ukuran menurut besar gelas/kemasan (Hot → Reguler → Large →
     * 250ml → 500ml → 1000ml), bukan alfabetis.
     *
     * `orderBy('ukuran')` di SQL menghasilkan `1000ml, 250ml, 500ml, Hot,
     * Large, Reguler` — urutan yang tidak berarti apa pun buat manager yang
     * sedang membandingkan harga antar ukuran. Diurutkan di PHP karena
     * urutannya didefinisikan di {@see GolonganUkuran}, dan menyalinnya ke
     * ekspresi CASE per query berarti menjaga urutan yang sama di beberapa
     * tempat sekaligus.
     *
     * `sortBy` stabil, jadi urutan `nama`/`rasa` dari SQL tetap terjaga sebagai
     * pengurutan sekunder.
     *
     * @param  Collection<int, Menu>  $menu
     * @return Collection<int, Menu>
     */
    private function urutkanUkuran(Collection $menu): Collection
    {
        return $menu->sortBy(fn (Menu $m) => GolonganUkuran::urutan($m->ukuran))->values();
    }
}
