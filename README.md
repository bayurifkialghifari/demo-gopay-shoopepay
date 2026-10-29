# Demo QRIS Dinamis — GoPay & ShopeePay

Demo PHP untuk membuat QRIS dinamis dan memantau pembayaran melalui API **[Karuhun Developer](https://qris.karuhundeveloper.com/)**. Browser berkomunikasi dengan backend PHP; backend meneruskan request ke API QRIS. API token dan token transaksi disimpan di session server.

## Menjalankan demo

Gunakan PHP 8.1 atau lebih baru, session PHP yang dapat ditulis, dan ekstensi cURL atau `allow_url_fopen` untuk koneksi HTTP melalui stream. Tidak perlu Composer atau proses build frontend.

```bash
git clone https://github.com/bayurifkialghifari/demo-gopay-shoopepay.git
cd demo-gopay-shoopepay
php -S 127.0.0.1:8080 -t .
```

Buka `http://127.0.0.1:8080`, hubungkan API token, lalu masukkan nominal dan pilih provider. Nilai provider yang dikirim ke API adalah `gopay`, `shopee`, atau kosong untuk mengikuti pengaturan akun. Halaman `showcase.php` berisi contoh tampilan tanpa membuat transaksi.

Base URL default adalah `https://qris.karuhundeveloper.com`. Jika perlu menggunakan server API lain, atur `QRIS_API_BASE_URL` di environment proses PHP. Token akun dimasukkan melalui halaman demo, bukan disimpan di source code.

## Status implementasi

Bagian **Flow integrasi** di bawah menjelaskan alur yang dituju: generate QRIS → payment page → polling 5 detik → deadline 15 menit, dengan scheduler tiap menit sebagai pemeriksaan dari server. Berikut perbedaannya dengan demo saat ini.

| Bagian | Demo saat ini | Flow integrasi yang dituju |
| --- | --- | --- |
| Generate QRIS | Sudah tersedia melalui aksi `create` | Simpan transaksi di database setelah QRIS berhasil dibuat |
| Payment page | QRIS dan status tampil di panel pada `index.php` | Halaman pembayaran berdasarkan ID transaksi yang dapat dibuka kembali |
| Polling 5 detik | Sudah tersedia; interval default 5 detik dan perlu mengaktifkan **Cek pembayaran otomatis** | Aktif otomatis selama transaksi ongoing |
| Deadline 15 menit | Mengikuti `expires_at` atau `expires_in_seconds` dari API; belum menetapkan batas lokal 15 menit | Backend menetapkan deadline maksimal 15 menit sejak QRIS dibuat |
| Penyimpanan transaksi | Satu transaksi aktif per session PHP | Penyimpanan persisten untuk seluruh transaksi ongoing |
| Scheduler tiap menit | Belum diimplementasikan | Worker server memeriksa seluruh transaksi ongoing tanpa bergantung pada browser |

## Flow integrasi

Database dan scheduler pada diagram ini merupakan rancangan integrasi, belum bagian dari kode demo.

```mermaid
sequenceDiagram
    actor User as Pengguna
    participant Page as Payment page
    participant Backend as Backend aplikasi
    participant API as API QRIS Karuhun
    participant DB as Database transaksi
    participant Job as Scheduler tiap menit

    User->>Backend: Buat pembayaran dengan nominal dan provider
    Backend->>API: POST /v1/qris/create
    API-->>Backend: ID, token transaksi, nominal final, expiry
    Backend->>DB: Simpan pending dan deadline maksimal 15 menit
    Backend-->>Page: Tampilkan QRIS, nominal final, countdown

    loop Setiap 5 detik selama pending dan sebelum deadline
        Page->>Backend: Minta status pembayaran
        Backend->>DB: Baca dan kunci transaksi
        opt Transaksi masih ongoing
            Backend->>API: POST /v1/payment/check dengan wait=false
            API-->>Backend: pending, success, atau expired
            Backend->>DB: Simpan hasil pemeriksaan
        end
        Backend-->>Page: Status terbaru dan deadline
    end

    loop Setiap menit, termasuk saat payment page ditutup
        Job->>DB: Ambil transaksi ongoing dan kunci per transaksi
        Job->>API: Periksa pembayaran ongoing
        API-->>Job: Status pembayaran
        Job->>DB: Perbarui status atau tandai expired sesuai deadline
    end

    Note over Page,DB: Polling berhenti pada success atau expired; timeout jaringan bukan status pembayaran
```

### 1. Generate QRIS

1. Backend memvalidasi nominal Rupiah berupa bilangan bulat dan provider yang dipilih.
2. Backend memanggil `POST /v1/qris/create` menggunakan header `x-api-key` dan `Idempotency-Key`.
3. Backend menyimpan ID QRIS, token transaksi, nominal final, provider, status `pending`, `created_at`, dan `expires_at` di penyimpanan persisten. Token transaksi digunakan hanya oleh backend.
4. Tetapkan deadline dengan waktu server: `expires_at = min(created_at + 15 menit, expires_at dari API)`. Jika API tidak memberikan expiry, gunakan `created_at + 15 menit`; jika hanya memberikan `expires_in_seconds`, ubah menjadi timestamp terlebih dahulu. Deadline lokal tidak memperpanjang masa berlaku QRIS provider.
5. Arahkan pengguna ke payment page berdasarkan ID pembayaran aplikasi. Tampilkan nominal final dari API karena bisa berbeda dari nominal input akibat kode unik.

Pada demo, `create_qris()` di `api.php` melakukan request pembuatan dan menyimpan satu transaksi di session. Responsnya menampilkan QRIS langsung pada panel pembayaran di halaman yang sama. Demo membuat `Idempotency-Key` baru untuk setiap request pembuatan; integrasi checkout perlu menyimpan satu key per percobaan pembayaran dan memakai key yang sama ketika mengulang request yang hasilnya belum diketahui.

### 2. Payment page dan polling 5 detik

- Payment page membaca status dari backend dan menampilkan gambar QRIS, nominal, provider, serta countdown menuju deadline.
- Selama status `pending` dan deadline belum lewat, periksa status setiap **5 detik** dengan `POST /v1/payment/check` dari backend. Kirim token transaksi dan `wait: false`. Browser tidak memanggil API provider secara langsung.
- Jadwalkan request berikutnya setelah request sebelumnya selesai agar pengecekan tidak tumpang tindih. Pada demo, ini menggunakan recursive `setTimeout`, sehingga jeda 5 detik dihitung setelah respons diterima.
- Jika pembayaran dikonfirmasi, simpan `success`, waktu pembayaran, dan referensi transaksi provider; tampilkan pembayaran selesai dan hentikan polling.
- Jika API melaporkan `expired`, hentikan polling dan minta pengguna membuat QRIS baru. Pada rancangan integrasi, backend juga menegakkan batas lokal 15 menit.
- Jika koneksi gagal atau API membatasi request, pertahankan status pembayaran terakhir, tampilkan kegagalan pengecekan, dan coba lagi dengan backoff. Error jaringan tidak boleh dianggap sebagai pembayaran gagal atau berhasil.

Countdown browser adalah tampilan. Backend tetap menjadi sumber keputusan deadline dan status pembayaran.

### 3. Scheduler tiap menit

Scheduler dibutuhkan agar pemeriksaan tetap berjalan ketika pengguna menutup tab atau kehilangan koneksi. Session browser saja tidak cukup untuk menemukan seluruh transaksi ongoing.

1. Jalankan worker setiap **1 menit** melalui cron atau scheduler aplikasi. Pola cron-nya adalah `* * * * *`; demo belum menyediakan command worker untuk dipasang ke cron.
2. Ambil transaksi berstatus `pending` dari database secara bertahap. Di sini, **ongoing** berarti transaksi yang masih menunggu hasil pembayaran, termasuk yang perlu pemeriksaan terakhir di batas deadline.
3. Gunakan lock per transaksi agar worker dan polling browser tidak memeriksa serta menulis transaksi yang sama secara bersamaan. Gunakan lock untuk job juga agar dua jadwal tidak menjalankan worker yang sama secara bertumpuk.
4. Periksa status melalui API QRIS. Simpan `success` atau `expired` jika dikonfirmasi; pertahankan `pending` jika belum dibayar dan deadline belum lewat.
5. Saat deadline tercapai, lakukan rekonsiliasi terakhir untuk pembayaran yang mungkin baru terdeteksi. Pembayaran yang dikonfirmasi API harus diperiksa berdasarkan waktu pembayaran dan aturan expiry provider. Jika pengecekan gagal, hentikan penerimaan pembayaran baru untuk transaksi itu, catat rekonsiliasi yang tertunda, dan coba kembali; jangan menyimpulkan belum dibayar hanya dari timeout.
6. Jika pemeriksaan berhasil menyatakan belum dibayar dan deadline sudah lewat, ubah status menjadi `expired`. Transaksi terminal tidak masuk pemeriksaan rutin berikutnya.
7. Catat waktu pemeriksaan dan hasilnya tanpa mencatat API token atau token transaksi. Proses pemenuhan pesanan satu kali saja meskipun browser dan scheduler sama-sama menerima status `success`.

Data minimal untuk integrasi persisten: ID pembayaran aplikasi, ID QRIS provider, referensi kredensial akun, token transaksi yang dilindungi di server, nominal diminta, nominal final, provider, status, waktu dibuat, deadline, waktu pemeriksaan terakhir, waktu pembayaran, referensi pembayaran provider, dan penanda kebutuhan rekonsiliasi.

## API backend demo

Semua aksi memakai `POST /api.php` dengan JSON, cookie session yang sama, dan header `X-CSRF-Token`. Token CSRF diperoleh dari meta `csrf-token` pada `index.php`; modul `assets/api.js` sudah mengirim header tersebut.

| Aksi | Body tambahan | Fungsi |
| --- | --- | --- |
| `state` | Tidak ada | Membaca koneksi, akun, dan transaksi aktif pada session |
| `save_token` | `token` | Memvalidasi token lewat API akun dan menyimpannya di session |
| `account` | Tidak ada | Memuat ulang ringkasan akun |
| `create` | `amount`, `provider` opsional | Membuat QRIS dinamis |
| `check` | Tidak ada | Memeriksa pembayaran transaksi aktif di session |
| `reset_transaction` | Tidak ada | Menghapus referensi transaksi aktif dari session |
| `clear_token` | Tidak ada | Menghapus token, akun, dan transaksi dari session |

Contoh body untuk membuat QRIS:

```json
{
  "action": "create",
  "amount": 25000,
  "provider": "gopay"
}
```

Contoh body untuk memeriksa pembayaran:

```json
{
  "action": "check"
}
```

Respons `check` berisi `check.paid`, `check.status`, `transaction`, dan `state`. Status pembayaran yang dikenali backend adalah `pending`, `success`, dan `expired`. Field top-level `success: true` berarti request berhasil diproses; pembayaran baru dinyatakan diterima dari status transaksi atau `check.paid`.

Reset transaksi dan hapus token hanya membersihkan session demo; keduanya tidak mengirim pembatalan QRIS ke provider.

## Endpoint API QRIS yang dipakai

Base URL: **https://qris.karuhundeveloper.com**. Kontrak berikut mengikuti pemanggilan pada `lib/QrisClient.php`.

| Method | Endpoint | Pemakaian |
| --- | --- | --- |
| `GET` | `/v1/account` | Validasi token dan ringkasan akun |
| `POST` | `/v1/qris/create` | Generate QRIS dengan `amount` dan `provider` opsional |
| `POST` | `/v1/payment/check` | Cek status dengan `token` transaksi dan `wait: false` |
| `GET` | `/qr/{id}` | Ambil gambar QRIS melalui proxy `qr.php` |

Backend mengirim API token pada header `x-api-key`. Browser menerima gambar melalui `qr.php`, referensi transaksi yang disamarkan, dan data pembayaran yang dipilih oleh backend.

## Struktur proyek

```text
index.php             Halaman konfigurasi dan pembayaran
api.php               API JSON berbasis session
qr.php                Proxy gambar QRIS
showcase.php          Contoh komponen dan status UI
lib/bootstrap.php     Session, CSRF, validasi, dan pemilihan data respons
lib/QrisClient.php    Client HTTP API QRIS
assets/app.js         Alur UI, countdown, dan polling
assets/api.js         Request JSON ke backend
assets/ui.js          Format data dan helper tampilan
assets/styles.css     Tema dan layout
DESIGN.md             Catatan sistem desain
```

Demo belum memiliki database, worker scheduler, webhook, atau pemenuhan pesanan. Flow di README ini menjadi panduan untuk menambahkan integrasi tersebut.
