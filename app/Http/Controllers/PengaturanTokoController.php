<?php

namespace App\Http\Controllers;

use App\Http\Requests\QrMenuRequest;
use App\Http\Requests\UpdatePengaturanTokoRequest;
use App\Http\Requests\UploadQrisRequest;
use App\Models\PengaturanToko;
use App\Support\QrMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PengaturanTokoController extends Controller
{
    /** Folder pada disk `public`, dilayani lewat `php artisan storage:link`. */
    private const FOLDER_QRIS = 'qris';

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload(PengaturanToko::current())]);
    }

    /**
     * Info toko yang boleh dilihat pelanggan, dipakai layar pembayaran
     * SoyaScan. Publik tanpa auth.
     *
     * KENAPA PERLU ADA. `POST /api/order` menyertakan `qris_url` hanya pada
     * saat pesanan dibuat, dan SoyaScan menyimpannya di localStorage. Akibatnya
     * pesanan yang dibuat SEBELUM manager mengunggah QRIS akan menampilkan
     * "Kode QRIS belum tersedia" selamanya, bahkan setelah QRIS-nya diunggah:
     * pelanggan yang sedang duduk menunggu tidak punya jalan keluar selain
     * memesan ulang. Dengan endpoint ini layar pembayaran bisa menanyakan
     * QRIS yang berlaku sekarang, bukan mengandalkan salinan yang mungkin
     * sudah basi.
     *
     * Isinya sengaja cuma yang memang sudah publik: QRIS statis merchant itu
     * ditempel di konter dan ditunjukkan ke setiap pelanggan, dan nama toko
     * tercetak di nota. Nomor telepon, alamat, dan jejak siapa yang terakhir
     * mengubah pengaturan TIDAK ikut, itu urusan internal.
     */
    public function publik(): JsonResponse
    {
        $toko = PengaturanToko::current();

        return response()->json([
            'data' => [
                'nama_toko' => $toko->nama_toko,
                'qris_url' => $toko->qrisUrl(),
            ],
        ]);
    }

    public function update(UpdatePengaturanTokoRequest $request): JsonResponse
    {
        $toko = $this->tokoTersimpan();

        $toko->fill($request->validated());
        $toko->updated_by = $request->user()->id;
        $toko->save();

        return response()->json(['data' => $this->payload($toko)]);
    }

    /**
     * Unggah/ganti gambar QRIS statis merchant.
     *
     * Backend tidak memvalidasi, membaca, atau memproses pembayaran apa pun dari
     * gambar ini, ia betul-betul hanya gambar yang ditampilkan ke pelanggan.
     */
    public function uploadQris(UploadQrisRequest $request): JsonResponse
    {
        $toko = $this->tokoTersimpan();

        $lama = $toko->qris_gambar;

        $toko->qris_gambar = $request->file('qris')->store(self::FOLDER_QRIS, 'public');
        $toko->updated_by = $request->user()->id;
        $toko->save();

        // Berkas lama dihapus SETELAH yang baru tersimpan: kalau urutannya
        // dibalik lalu penyimpanan gagal, QRIS toko hilang dan pelanggan tidak
        // bisa bayar sama sekali.
        $this->hapusBerkas($lama);

        return response()->json(['data' => $this->payload($toko)]);
    }

    public function hapusQris(): JsonResponse
    {
        $toko = $this->tokoTersimpan();

        $this->hapusBerkas($toko->qris_gambar);

        $toko->qris_gambar = null;
        $toko->save();

        return response()->json(['data' => $this->payload($toko)]);
    }

    /**
     * QR untuk ditempel di meja. Mengembalikan BERKAS gambar, bukan JSON berisi
     * base64, supaya manager bisa langsung menyimpan atau mencetaknya dari
     * browser tanpa alat bantu.
     */
    public function qrMenu(QrMenuRequest $request): Response
    {
        $format = $request->formatGambar();

        [$isi, $mime] = $format === 'png'
            ? [QrMenu::png($request->ukuran()), 'image/png']
            : [QrMenu::svg($request->ukuran()), 'image/svg+xml'];

        return response($isi, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="qr-menu-soyascan.'.$format.'"',
        ]);
    }

    /**
     * Baris pengaturan yang benar-benar tersimpan (pola singleton: 0 atau 1
     * baris). Instance baru dipakai kalau belum ada, supaya `updated_by` tetap
     * jujur mencatat siapa yang pertama kali menyimpan.
     */
    private function tokoTersimpan(): PengaturanToko
    {
        return PengaturanToko::query()->orderBy('id')->first() ?? new PengaturanToko([
            'nama_toko' => PengaturanToko::DEFAULT_NAMA_TOKO,
            'jam_buka' => PengaturanToko::DEFAULT_JAM_BUKA,
            'jam_tutup' => PengaturanToko::DEFAULT_JAM_TUTUP,
        ]);
    }

    private function hapusBerkas(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PengaturanToko $toko): array
    {
        return [
            'nama_toko' => $toko->nama_toko,
            'no_telepon' => $toko->no_telepon,
            'alamat' => $toko->alamat,
            'jam_buka' => PengaturanToko::jam($toko->jam_buka),
            'jam_tutup' => PengaturanToko::jam($toko->jam_tutup),
            'qris_url' => $toko->qrisUrl(),
            'diperbarui_pada' => $toko->updated_at?->toIso8601String(),
            'diperbarui_oleh' => $toko->updated_by === null
                ? null
                : $toko->updatedBy?->nama,
        ];
    }
}
