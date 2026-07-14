<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Data menu asli GresSOY (dari menu resmi kedai).
     * Struktur: kategori => [nama menu => [ukuran => harga]].
     * Ukuran null = item tanpa varian ukuran (dessert/cookies).
     *
     * @var array<string, array<string, array<string, int>>>
     */
    private const MENU = [
        'Soya Signature' => [
            'Original' => ['Hot' => 17000, 'Reguler' => 17000, 'Large' => 21000, '250ml' => 22000, '500ml' => 39000, '1000ml' => 74000],
            'Taro Thanos' => ['Reguler' => 25000, 'Large' => 30000, '250ml' => 27000, '500ml' => 49000, '1000ml' => 89000],
            'Redvelvet' => ['Reguler' => 25000, 'Large' => 30000, '250ml' => 27000, '500ml' => 49000, '1000ml' => 89000],
            'Mango Smell Good' => ['Reguler' => 25000, 'Large' => 30000, '250ml' => 27000, '500ml' => 49000, '1000ml' => 89000],
            'Royal Belgian' => ['Reguler' => 25000, 'Large' => 30000, '250ml' => 27000, '500ml' => 49000, '1000ml' => 89000],
        ],
        'Soya Chocolate' => [
            'Choco Maniac' => ['Hot' => 25000, 'Reguler' => 25000, 'Large' => 30000, '250ml' => 27000, '500ml' => 49000, '1000ml' => 89000],
            'Choco Oat' => ['Hot' => 27000, 'Reguler' => 27000, 'Large' => 32000, '250ml' => 30000, '500ml' => 55000, '1000ml' => 92000],
            'Dark Choco' => ['Hot' => 29000, 'Reguler' => 29000, 'Large' => 34000, '250ml' => 33000, '500ml' => 58000, '1000ml' => 95000],
            'Choco Coffee' => ['Hot' => 27000, 'Reguler' => 27000, 'Large' => 32000, '250ml' => 30000, '500ml' => 55000, '1000ml' => 92000],
        ],
        'Soya Coffee' => [
            'Coffee Kopi' => ['Hot' => 21000, 'Reguler' => 21000, 'Large' => 26000, '250ml' => 24000, '500ml' => 42000, '1000ml' => 80000],
            'Tiramisu' => ['Hot' => 25000, 'Reguler' => 25000, 'Large' => 30000, '250ml' => 27000, '500ml' => 49000, '1000ml' => 89000],
            'Cappuccino' => ['Hot' => 25000, 'Reguler' => 25000, 'Large' => 30000, '250ml' => 27000, '500ml' => 49000, '1000ml' => 89000],
        ],
        'Soya Tropical' => [
            'Honey Lemon' => ['Reguler' => 20000, 'Large' => 25000, '250ml' => 23000, '500ml' => 44000, '1000ml' => 84000],
            'Mango Monggo' => ['Reguler' => 20000, 'Large' => 25000, '250ml' => 23000, '500ml' => 44000, '1000ml' => 84000],
        ],
        'Soya Tea' => [
            'Green Tea' => ['Hot' => 25000, 'Reguler' => 25000, 'Large' => 30000, '250ml' => 27000, '500ml' => 49000, '1000ml' => 89000],
            'Thai Tea' => ['Hot' => 22000, 'Reguler' => 22000, 'Large' => 27000, '250ml' => 26000, '500ml' => 46000, '1000ml' => 87000],
        ],
        'Dessert & Cookies' => [
            'Kembang Tahu Tahwa' => ['' => 15000],
            'Soy Milk Pudding' => ['' => 15000],
            'Vegan Cookies Peanut' => ['' => 40000],
        ],
    ];

    /**
     * Komposisi/deskripsi rasa per menu (disimpan di kolom `rasa`).
     *
     * @var array<string, string>
     */
    private const RASA = [
        'Original' => 'Soya Original Premium + Brown Sugar',
        'Taro Thanos' => 'Soya Original Premium + Taro Premium + Brown Sugar',
        'Redvelvet' => 'Soya Original Premium + Redvelvet Premium + Brown Sugar',
        'Mango Smell Good' => 'Soya Original Premium + Mango Premium + Brown Sugar',
        'Royal Belgian' => 'Soya Original Premium + Royal Belgian + Brown Sugar',
        'Choco Maniac' => 'Soya Original Premium + Chocolate Premium + Brown Sugar',
        'Choco Oat' => 'Soya Original Premium + Chocolate Premium Mix Oat + Brown Sugar',
        'Dark Choco' => 'Soya Original Premium + Pure Chocolate + Brown Sugar',
        'Choco Coffee' => 'Soya Original Premium + Chocolate Premium Mix Espresso + Brown Sugar',
        'Coffee Kopi' => 'Soya Original Premium + Robusta Mix Arabica Premium + Brown Sugar',
        'Tiramisu' => 'Soya Original Premium + Tiramisu Premium + Brown Sugar',
        'Cappuccino' => 'Soya Original Premium + Cappuccino Premium + Brown Sugar',
        'Honey Lemon' => 'Soya Original Premium + Special Madu Lemon',
        'Mango Monggo' => 'Soya Original Premium + Special Mangga Gandaria',
        'Green Tea' => 'Soya Original Premium + Greentea Thailand Premium + Brown Sugar',
        'Thai Tea' => 'Soya Original Premium + Tea Thailand Premium + Brown Sugar',
        'Kembang Tahu Tahwa' => 'Dessert kembang tahu lembut dari sari kedelai',
        'Soy Milk Pudding' => 'Puding susu kedelai lembut',
        'Vegan Cookies Peanut' => 'Cookies vegan kacang, tanpa telur, susu, maupun mentega',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Password default factory: 'password'
        User::factory()->create([
            'nama' => 'Manager Gressoy',
            'email' => 'manager@gressoy.test',
            'role' => 'manager',
        ]);

        User::factory()->create([
            'nama' => 'Kasir Gressoy',
            'email' => 'kasir@gressoy.test',
            'role' => 'kasir',
        ]);

        foreach (self::MENU as $namaKategori => $daftarMenu) {
            $kategori = Kategori::create(['nama' => $namaKategori]);

            foreach ($daftarMenu as $namaMenu => $varian) {
                foreach ($varian as $ukuran => $harga) {
                    Menu::create([
                        'kategori_id' => $kategori->id,
                        'nama' => $namaMenu,
                        'rasa' => self::RASA[$namaMenu] ?? null,
                        'ukuran' => $ukuran === '' ? null : $ukuran,
                        'harga' => $harga,
                    ]);
                }
            }
        }
    }
}
