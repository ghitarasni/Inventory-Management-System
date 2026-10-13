# BrandKicks Vault - Inventory Management System (Mini Project 3)

Aplikasi manajemen inventaris sepatu branded berbasis PHP Native dan Relational Database MySQL, dibangun untuk memenuhi tugas akhir praktikum Pemrograman Web (Pertemuan 4).

---

## 1. Deskripsi Proyek

BrandKicks Inventory adalah sistem manajemen rantai pasok dan inventaris sepatu branded original (Nike, Adidas, New Balance, Converse, Puma, Vans, Asics, Reebok). Aplikasi ini mengimplementasikan database relasional yang kompleks, autentikasi dan otorisasi berbasis peran (Role-Based Access Control) antara Admin dan Staff, relasi Many-to-Many antara produk dan supplier, serta transaksi stok (barang masuk dan keluar) menggunakan Database Transactions (Commit dan Rollback) untuk menjamin integritas data.

---

## 2. Fitur Utama dan Kepatuhan Aturan Bisnis

### A. Autentikasi dan Otorisasi (RBAC)
- Otentikasi Pengguna: Sistem login menggunakan fungsi password_verify() dengan enkripsi algoritma BCRYPT dan mitigasi Session Fixation via session_regenerate_id().
- Role Administrator:
  - Mengelola master data produk (Create, Read, Update, Delete).
  - Mengelola master data mitra supplier/distributor.
  - Melakukan transaksi barang masuk dan keluar.
  - Mengakses seluruh statistik agregat dashboard.
- Role Staff:
  - Mengakses dashboard dan katalog inventaris (Read Only).
  - Melakukan transaksi operasional barang masuk (stok in) dan barang keluar (stok out).
  - Dilarang mengakses modul administrasi master produk (Create, Edit, Delete) dan manajemen supplier (HTTP 403 Forbidden).

### B. Relasi Database dan Master Data
- Relasi One-to-Many: Antara tabel categories dan products.
- Relasi Many-to-Many: Antara tabel products dan suppliers melalui tabel perantara product_supplier.
- Integritas Kunci Asing (Foreign Key): Menjamin konsistensi referensial antar tabel dengan onDelete cascade pada relasi pivot.

### C. Transaksi Stok (Database Transactions)
- Barang Masuk (public/stock/in.php): Menambah jumlah stok fisik dan mencatat entri histori ke tabel stock_movements.
- Barang Keluar (public/stock/out.php): Mengurangi jumlah stok fisik untuk pengiriman atau penjualan.
- ACID Compliance: Operasi stok dibungkus dalam blok try-catch dengan $pdo->beginTransaction(), $pdo->commit(), dan $pdo->rollBack() serta row-level locking (SELECT ... FOR UPDATE).

### D. Aturan Bisnis (Business Rules Kritis Sesuai Slide 33)
1. SKU harus unik di seluruh sistem.
2. Harga satuan dan jumlah kuantitas tidak boleh bernilai negatif.
3. Kuantitas barang keluar tidak boleh melebihi sisa stok fisik di gudang.
4. Nilai stok tidak boleh menjadi negatif.
5. Produk yang memiliki rekam jejak histori transaksi stok dilarang dihapus sembarangan.
6. Halaman internal mewajibkan login pengguna.
7. Staff tidak memiliki akses ke hak istimewa administrator.
8. Pengurutan data (Sorting) dibatasi hanya pada daftar kolom yang diizinkan (Whitelist: id, name, price, stock, sku).

### E. Fitur Eksplorasi Data
- Filter Kategori: Menyaring produk berdasarkan kategori sepatu.
- Pencarian (Search): Mencari berdasarkan nama produk, kode SKU, atau nama operator.
- Pengurutan (Sorting Whitelist): Berdasarkan ID, nama, harga, dan stok.
- Paginasi (Pagination): Menampilkan data per halaman secara efisien.

---

## 3. Struktur Direktori Proyek

```text
mini-project_3/
├── config/
│   └── database.php            # Konfigurasi koneksi basis data PDO
├── helpers/
│   ├── auth.php                # Helper autentikasi, otorisasi, dan CSRF
│   ├── escape.php              # Helper proteksi XSS e()
│   └── validation.php          # Helper validasi aturan bisnis server-side
├── views/
│   ├── header.php              # Template navigasi dan identitas pengguna
│   └── footer.php              # Template penutup HTML
├── database/
│   └── schema.sql              # Skema DDL dan data awal inventaris
├── public/
│   ├── assets/
│   │   └── style.css           # Berkas CSS responsif
│   ├── uploads/                # Direktori penyimpanan foto produk sepatu
│   ├── index.php               # Gateway redirect
│   ├── login.php               # Formulir login autentikasi
│   ├── logout.php              # Handler keluar sistem
│   ├── dashboard.php           # Ringkasan statistik dan laporan agregat
│   ├── products/
│   │   ├── index.php           # Katalog master produk + filter + sorting + paging
│   │   ├── create.php          # Tambah master produk (Khusus Admin)
│   │   ├── edit.php            # Ubah data produk (Khusus Admin)
│   │   └── delete.php          # Hapus produk aman (Khusus Admin)
│   ├── stock/
│   │   ├── index.php           # Histori transaksi stok masuk dan keluar
│   │   ├── in.php              # Form transaksi barang masuk gudang
│   │   └── out.php             # Form transaksi barang keluar gudang
│   └── suppliers/
│       ├── index.php           # Katalog daftar rekanan distributor
│       └── create.php          # Tambah supplier baru (Khusus Admin)
└── README.md                   # Dokumentasi teknis proyek
```

---

## 4. Kredensial Pengguna Demo

| Role | Username | Password | Hak Akses |
|---|---|---|---|
| Administrator | admin | admin123 | Seluruh modul sistem, CRUD master, transaksi, dan laporan |
| Staff | staff | staff123 | Transaksi barang masuk/keluar, histori stok, dan melihat katalog |

---

## 5. Panduan Menjalankan Aplikasi

### Langkah 1: Persiapan Basis Data
1. Pastikan layanan Apache dan MySQL pada XAMPP Control Panel telah aktif.
2. Impor berkas skema dan data awal berikut ke basis data store_db:
   database/schema.sql

### Langkah 2: Menjalankan Server Melalui Terminal
Buka terminal pada direktori D:\punya-ghita\mini-project_3, lalu jalankan perintah:

```powershell
php -S localhost:8080 -t public
```

Buka peramban web dan akses alamat:
http://localhost:8080

---

## 6. Skenario Pengujian Validasi dan Hak Akses (Sesuai Slide 38)

### Skenario 1: Uji Validasi Error (Kasus Abnormal)
- Uji Coba: Lakukan transaksi Barang Keluar dengan memasukkan kuantitas yang melebihi stok fisik (misalnya produk memiliki sisa 8 pasang, diinput 20 pasang).
- Hasil: Sistem membatalkan proses dengan rollback transaksi dan menampilkan pesan: "Jumlah barang keluar melebihi sisa stok yang tersedia saat ini".

### Skenario 2: Uji Akses Terlarang (Kasus Otorisasi)
- Uji Coba: Login menggunakan akun Staff (staff / staff123), kemudian coba buka URL form penambahan master produk langsung di http://localhost:8080/products/create.php.
- Hasil: Sistem menolak akses dengan HTTP status 403 Forbidden, me-redirect pengguna ke dashboard, dan menampilkan pesan: "Akses ditolak: Hanya Administrator yang berhak mengakses halaman ini".

### Skenario 3: Uji Penghapusan Produk Berhistori
- Uji Coba: Login sebagai Admin dan coba hapus produk yang sudah memiliki catatan di histori transaksi stok.
- Hasil: Sistem menolak penghapusan untuk menjaga konsistensi rekam jejak inventaris.

---

## 7. Matriks Pengujian Checklist Penilaian (Slide 35-36)

| Komponen | Bobot | Skenario Pengujian | Status |
|---|---|---|---|
| Desain database & relasi | 20% | Relasi categories, products, suppliers, product_supplier (M:N), dan stock_movements | Terpenuhi |
| Foreign key & integritas data | 10% | Integritas referensial dan pencegahan orphan data berjalan sesuai skema | Terpenuhi |
| CRUD master data | 10% | Penambahan dan pembaruan produk beserta multi-supplier distributor | Terpenuhi |
| Transaksi masuk/keluar | 20% | Transaksi stok in/out menggunakan commit/rollback dan locking row | Terpenuhi |
| Search, filter, sorting, paging | 10% | Parameter GET aman dengan prepared statements dan pagination aktif | Terpenuhi |
| Login, session, authorization | 10% | Proteksi halaman internal, pemisahan hak akses Admin dan Staff | Terpenuhi |
| Keamanan aplikasi | 15% | Prepared Statements, escaping anti-XSS, proteksi CSRF token | Terpenuhi |
| Kerapian & presentasi | 5% | Tata letak UI responsif, struktur direktori teratur, tanpa duplikasi kode | Terpenuhi |
