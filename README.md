# Monopoly Web Game

Permainan Monopoly berbasis web yang dibuat dengan Laravel. Tujuan akhirnya: bermain melawan AI atau bersama teman secara online, dengan satu akun **Owner / Game Master** yang bisa ikut bermain sekaligus mengatur jalannya permainan.

> **Status:** Tahap 1–3 selesai (game lokal hot-seat dengan aturan lengkap). AI, multiplayer online, panel Game Master, dan real-time masih dalam pengerjaan.

## Fitur saat ini

- Papan 40 petak (properti, stasiun, utilitas, pajak, kartu, penjara, parkir bebas) dengan konfigurasi di satu tempat (`config/monopoly.php`).
- Game lokal 2–4 pemain di satu perangkat (hot-seat), dikendalikan oleh host.
- Dadu dihasilkan server, pergerakan bidak, gaji START, dadu kembar dan giliran tambahan.
- Beli properti dan sewa (properti, stasiun, utilitas, bonus kelompok warna lengkap).
- Rumah dan hotel: wajib punya satu kelompok warna penuh, pembangunan dan penjualan merata, stok bank terbatas.
- Hipotek: gadai dan tebus (nilai gadai + bunga 10%).
- Penjara: masuk lewat petak, dadu kembar ke-3, atau kartu; keluar dengan denda, kartu bebas penjara, atau dadu kembar.
- Kartu Kesempatan dan Dana Umum (12 kartu per dek, diacak server).
- Utang dan kebangkrutan: jual bangunan atau gadai untuk melunasi; jika aset tidak cukup, pemain bangkrut.
- Keamanan: validasi di server, kunci baris database + transaksi, idempotency key, pengecekan versi state, rate limiting, policy akses room.
- Akun Owner dibuat lewat perintah Artisan (tidak ada pendaftaran publik untuk role owner).

## Belum tersedia

| Tahap | Isi |
|---|---|
| 4 | AI lawan (Easy dan Hard) |
| 5 | Multiplayer online: room, kode room, lobby, reconnect |
| 6 | Panel Game Master (uang, dadu, properti, posisi, giliran, audit log privat) |
| 7 | Real-time Laravel Reverb/Echo, finalisasi, dokumentasi deployment |

Lelang properti juga belum ada (tombolnya sengaja tidak ditampilkan).

## Teknologi

- PHP 8.2+ dan Laravel 12
- Blade, HTML, CSS, JavaScript (tanpa framework frontend)
- MySQL/MariaDB (SQLite dipakai untuk test)
- Laravel Reverb dan Echo (disiapkan untuk Tahap 7)

## Setup

Prasyarat: PHP 8.2+, Composer, Node.js, dan MySQL/MariaDB (misalnya XAMPP).

```powershell
composer install
copy .env.example .env
php artisan key:generate
```

Buka `.env` dan atur database. Contoh untuk MySQL/MariaDB:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monopoly
DB_USERNAME=root
DB_PASSWORD=
```

Buat database `monopoly` terlebih dahulu (phpMyAdmin atau `CREATE DATABASE monopoly;`), lalu:

```powershell
php artisan migrate
npm install
npm run build
```

### Membuat akun Owner

Hanya satu akun owner yang diizinkan. Password diminta lewat input tersembunyi (minimal 12 karakter) dan tidak disimpan di source code.

```powershell
php artisan monopoly:create-owner
```

### Menjalankan

```powershell
php artisan serve
```

Buka http://127.0.0.1:8000, daftar atau login, lalu mulai game lokal dari menu.

## Test

```powershell
php artisan test
```

Cakupan saat ini: papan dan perhitungan sewa, autentikasi dan pembatasan owner, alur game (gerak, beli, sewa, dadu kembar, idempotensi, versi state, akses), serta Tahap 3 (bangunan, hipotek, penjara, kartu, utang, kebangkrutan).

## Struktur kode

```
app/
  Console/Commands/CreateOwner.php   Pembuatan akun owner
  Http/Controllers/                  AuthController, GameController
  Http/Middleware/EnsureOwner.php    Pembatas akses owner (404 untuk pengguna biasa)
  Models/                            GameRoom, GamePlayer, PropertyOwnership, GameCardDeck, ...
  Policies/GameRoomPolicy.php        Hanya host/anggota room yang boleh mengakses game
  Services/
    GameEngine.php                   Orkestrator aksi (satu transaksi per aksi)
    BoardService, DiceService, MovementService, TurnManager
    PropertyService, RentService, TransactionService
    BuildingService, MortgageService, JailService, CardService, BankruptcyService
    GameStateService                 Snapshot publik state game
config/
  monopoly.php                       Aturan, papan, harga, sewa
  monopoly_cards.php                 Definisi kartu Kesempatan dan Dana Umum
resources/views/game.blade.php       Halaman permainan
tests/                               Unit dan Feature test
```

## Prinsip desain

- **Server adalah sumber kebenaran.** Browser hanya mengirim permintaan aksi; saldo, dadu, kepemilikan, dan giliran ditentukan server.
- **Satu pintu perubahan state.** Semua aksi lewat `GameEngine::perform()`: room dikunci, aksi divalidasi, lalu di-commit atau dibatalkan seluruhnya.
- **Aturan satu sumber.** Alasan penolakan aksi (mis. kenapa belum bisa membangun) dihasilkan service yang sama dengan yang dipakai engine, sehingga UI dan aturan tidak bisa berbeda.
- **Log publik dan audit privat dipisah**, supaya aksi rahasia Game Master (Tahap 6) tidak bocor ke pemain lain.

## Catatan aturan

- Membangun dan menebus hanya di giliran sendiri (sebelum melempar dadu atau setelah aksi selesai); menjual dan menggadai juga bisa saat menunggu keputusan beli atau melunasi utang.
- Bangkrut ke pemain: uang dan properti pindah (status gadai tetap, bangunan dikembalikan ke bank). Bangkrut ke bank: properti kembali ke bank tanpa gadai.
- Kartu "terima dari tiap pemain": pemain yang uangnya kurang membayar sebisanya.

## Lisensi

Proyek pribadi. Framework Laravel berlisensi [MIT](https://opensource.org/licenses/MIT).