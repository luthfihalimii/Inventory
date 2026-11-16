# ENT Inventory - UI/UX Overhaul Design

**Status:** Approved 15 November 2026
**Tanggal:** 15 November 2026
**Pemilik:** UKM Jurnalistik / ENT
**Cakupan:** Seluruh aplikasi, pendekatan token-first (opsi A)
**Referensi visual:** dashboard biru-putih dan login split-screen yang diberikan pengguna

---

## 1. Ringkasan

Merombak tampilan ENT Inventory agar terasa seperti produk yang dirawat, bukan starter kit. Arah visualnya: kanvas biru muda, permukaan putih, kartu dengan sudut membulat dan border tipis, aksen biru, serta Plus Jakarta Sans. Login memakai pola split-screen: form di kiri, panel biru berisi cuplikan UI produk di kanan.

Yang berubah adalah lapisan presentasi. Route, aturan domain, status, alur approval, checkout QR, pengembalian, dan audit tidak berubah. Label yang dipakai pengujian e2e dipertahankan.

**Alasan:** aplikasi sudah lengkap secara fungsi, tetapi tampilan masih memakai token dan komponen starter kit. Pengguna meminta identitas visual yang konsisten dan responsif di semua perangkat.

---

## 2. Tujuan, Non-goals, dan Anti-goals

### 2.1 Tujuan

1. Satu bahasa visual untuk seluruh halaman: login, dashboard, katalog, peminjaman, approval, checkout, pengembalian, admin inventaris, WM, pengaturan, dan QR.
2. Plus Jakarta Sans terpasang dan dipakai konsisten.
3. Shell aplikasi baru: sidebar putih, header ringkas, navigasi mobile yang jelas.
4. Login split-screen yang responsif, tanpa kontrol sosial palsu.
5. Dashboard memakai data nyata: KPI yang sudah ada, tren pengajuan 6 bulan, distribusi status, dan pengumuman.
6. Semua halaman lolos di 320px sampai 1440px, light dan dark mode.
7. Aksesibilitas dasar: kontras AA, fokus terlihat, keyboard, label form, target sentuh 44px, reduced motion.

### 2.2 Non-goals

- Mengubah model data, route, controller selain agregat dashboard, atau aturan bisnis.
- Menambah library chart, UI, atau animasi baru.
- Menambah fitur yang belum ada (social login, registrasi publik, pencarian global, export baru).
- Mengganti teks label yang dipakai e2e kecuali diperlukan.
- Membuat logo final, foto, avatar, atau testimoni.

### 2.3 Anti-goals

Redesign dinyatakan gagal jika:

- ada kontrol yang tampil tetapi tidak berfungsi;
- angka, tren, atau avatar dibuat-buat;
- salah satu halaman overflow horizontal di mobile;
- dark mode rusak atau kontras gagal;
- alur e2e utama berubah atau selector berbasis teks hilang;
- dashboard menampilkan grafik yang tidak bisa dijelaskan datanya.

---

## 3. Batasan

| Batasan | Keputusan |
|---|---|
| Stack | Laravel 13, Inertia 3, Vue 3.5, Tailwind 4, Vite Plus. Tidak menambah dependency. |
| Font | Plus Jakarta Sans dari plugin `bunny()` yang sudah ada di `vite.config.ts`. |
| Ikon | `@lucide/vue` yang sudah terpasang, hanya untuk navigasi dan status yang relevan. |
| Chart | SVG/CSS buatan sendiri di dalam komponen Vue. Tanpa library. |
| Data | Semua angka berasal dari database. Tidak ada angka contoh. |
| Tema | Light dan dark harus berfungsi. Toggle appearance yang sudah ada dipertahankan. |
| E2E | Target: suite lulus tanpa perubahan. Label seperti "Kirim pengajuan", "Setujui", "Serahkan", "Periksa", "Ajukan pengembalian", teks status, ID input `#category`, `#usage_location`, `#pickup_at`, `#return_at`, h1 nomor referensi di halaman detail, dan struktur `tbody tr` dipertahankan. Jika markup benar-benar harus berubah, hanya selector struktural yang boleh disesuaikan; alur dan label tidak. |
| Bahasa | UI Bahasa Indonesia. Nama status database tetap bahasa Inggris. |
| Aset | Logo memakai monogram teks "ENT" sebagai placeholder yang jujur. Tidak ada foto atau avatar buatan. |

---

## 4. Design Read

> Reading this as: aplikasi inventaris internal untuk anggota, admin korlab, dan WM UKM Jurnalistik, dengan bahasa visual dashboard biru tenang yang rapi, dial ENERGY 2 / RHYTHM 2 / MOTION 1.

- **ENERGY 2:** biru yang percaya diri, bukan halaman yang berteriak. Fokus pada keterbacaan data.
- **RHYTHM 2:** pola konsisten dengan beberapa jeda hierarki. Dashboard punya grid 2:1, halaman lain memakai header, toolbar, lalu kartu tabel.
- **MOTION 1:** hanya transisi hover, fokus, dan buka-tutup sidebar. Tidak ada animasi masuk, parallax, atau loop.

**Design Read dideklarasikan sebelum generasi**, sesuai R-37. Tidak ada label "draft without direction" karena arah visual sudah diberikan pengguna.

---

## 5. Pendekatan

### A. Token-first menyeluruh (dipilih)

Ganti token, font, shell, login, dashboard, dan komponen bersama. Semua halaman mewarisi identitas baru. Halaman inti dirombak layoutnya; tabel admin dan WM dipoles tanpa membongkar struktur data.

- **Kelebihan:** hasil konsisten, diff lebih kecil, risiko regresi rendah, bisa diverifikasi bertahap.
- **Kekurangan:** halaman admin dalam terasa evolusi, bukan redesign total per halaman.
- **Alasan dipilih:** permintaan "total" paling efektif dicapai lewat sistem, bukan 40 file yang ditulis ulang dengan pola berbeda-beda.

### B. Total per halaman

Semua halaman didesain ulang satu per satu.

- **Kelebihan:** kontrol penuh atas setiap layar.
- **Kekurangan:** waktu jauh lebih lama, risiko regresi e2e dan inkonsistensi antar halaman lebih besar.
- **Ditolak:** biaya tinggi tanpa manfaat proporsional untuk aplikasi internal dengan pola tabel dan form yang berulang.

### C. Fondasi plus showcase

Hanya token, shell, login, dan dashboard.

- **Kelebihan:** paling cepat.
- **Kekurangan:** halaman lain tetap terasa setengah jadi.
- **Ditolak:** tidak memenuhi permintaan total overhaul.

---

## 6. Arah Visual

### 6.1 Identitas

- Nama produk: **ENT Inventory**.
- Monogram: huruf **ENT** pada kotak biru membulat, dipakai di sidebar dan login. Ini placeholder sampai ada logo resmi.
- Motif identitas: **kartu rak**. Sudut membulat, border tipis, dan susunan kartu yang rapi dipakai konsisten di KPI, panel, dan cuplikan UI login. Motif ini menghubungkan tampilan dengan ide inventaris tanpa ilustrasi generik.

### 6.2 Palet

| Token | Light | Dark | Alasan satu baris |
|---|---|---|---|
| `background` | `#F3F6FC` | `#0B1120` | Kanvas biru netral memisahkan konten dari sidebar putih. |
| `foreground` | `#0F172A` | `#F8FAFC` | Teks utama dengan kontras tinggi. |
| `card` | `#FFFFFF` | `#111827` | Permukaan data harus paling tenang dan mudah dibaca. |
| `primary` | `#2563EB` | `#3B82F6` | Biru adalah sinyal aksi dan status aktif, bukan dekorasi. |
| `primary-foreground` | `#FFFFFF` | `#0B1120` | Kontras tombol 5.17:1 di light dan 5.12:1 di dark. |
| `primary-soft` | `#EAF1FF` | `#172554` | Latar item aktif dan ikon, dosis rendah. |
| `muted-foreground` | `#5B6B82` | `#94A3B8` | Kontras 5.01:1 di kanvas light, 7.34:1 di dark. |
| `border` | `#E2E8F0` | `#263244` | Pemisah halus, bukan dekorasi. |
| `input` | `#7C8BA1` | `#64748B` | Border kontrol memenuhi non-text 3:1 di light. |
| `ring` | `#2563EB` | `#93C5FD` | Fokus terlihat di kedua tema. |
| `success` | `#166534` di `#DCFCE7` | `#86EFAC` di `#14532D` | Status berhasil selalu berpasangan dengan teks. |
| `warning` | `#92400E` di `#FEF3C7` | `#FCD34D` di `#78350F` | Status menunggu selalu berpasangan dengan teks. |
| `danger` | `#991B1B` di `#FEE2E2` | `#FCA5A5` di `#7F1D1D` | Status gagal atau terlambat selalu berpasangan dengan teks. |
| `info` | `#1E40AF` di `#DBEAFE` | `#BFDBFE` di `#1E3A8A` | Status informasi selalu berpasangan dengan teks. |

Semua pasangan teks di atas sudah diukur dengan `contrast-check.py`. Pasangan yang gagal tidak dipakai.

Biru tidak dipakai untuk setiap ikon, border, atau latar. Aksen biru hanya untuk aksi utama, item navigasi aktif, fokus, dan seri utama chart.

### 6.3 Tipografi

- Font: **Plus Jakarta Sans**, bobot 400, 500, 600, 700, 800.
- Alasan: bentuk geometris-humanisnya terasa institusional tetapi ramah, cocok untuk aplikasi organisasi, dan angka serta teks Indonesia tetap jelas pada ukuran kecil.
- Skala:

| Peran | Mobile | Desktop | Bobot |
|---|---|---|---|
| H1 halaman | 22px / 1.25 | 28px / 1.2 | 700 |
| H2 seksi | 18px / 1.3 | 20px / 1.3 | 600 |
| Body | 14px / 1.5 | 14px / 1.5 | 400 |
| Label form | 13px / 1.4 | 13px / 1.4 | 500 |
| Meta | 12px / 1.4 | 12px / 1.4 | 500 |
| Angka KPI | 26px / 1.1 | 32px / 1.1 | 700, tabular |
| Kode atau referensi | 13px / 1.4 | 13px / 1.4 | 500, tabular |

- Tidak ada uppercase dengan letter-spacing lebar. Label grup sidebar memakai 12px medium, sentence case.

### 6.4 Radius, bayangan, dan ruang

- Radius: kartu 16px, kontrol dan tombol 12px, chip status 999px hanya karena bentuknya menandakan status, modal 16px.
- Bayangan: hanya untuk lapisan mengambang (dropdown, popover, modal) dan panel login. Kartu memakai border, bukan bayangan, agar halaman tidak terasa melayang.
- Spacing: skala 4px. Gutter halaman 16px mobile, 24px tablet, 32px desktop. Padding kartu 20px mobile, 24px desktop. Jarak antar seksi 24px, jarak antar blok 16px.
- Lebar konten maksimum: 1440px, rata tengah.

### 6.5 Ikon

- Lucide yang sudah terpasang, ukuran 18px di navigasi, 16px inline.
- Ikon harus punya makna: paket untuk inventaris, clipboard untuk peminjaman, cek untuk approval, scan untuk checkout, undo untuk pengembalian, jam untuk keterlambatan.
- Tidak ada ikon sparkle, bintang, atau robot. Tidak ada ikon dekoratif di setiap judul.

### 6.6 Latar dan dekorasi

- Latar datar. Tanpa grid, titik, noise, orb, atau mesh gradient.
- Panel biru login memakai bentuk geometris tipis yang menyerupai susunan rak, dengan opasitas rendah. Alasan: mengaitkan panel dengan inventaris tanpa mengganggu teks.
- Tidak ada garis aksen kiri pada kartu atau baris.

---

## 7. Shell Aplikasi

### 7.1 Sidebar

- Permukaan putih (light) atau `#0F172A` (dark), lebar 272px, 64px saat collapsed.
- Logo monogram dan wordmark di atas.
- Grup navigasi memakai label sentence case 12px, bukan uppercase.
- Item aktif: latar `primary-soft`, teks `primary`, radius 12px. Tidak ada garis kiri.
- Item nonaktif: teks `muted-foreground`, hover latar `#F1F5F9`.
- Footer: menu pengguna yang sudah ada dan tautan bantuan yang sudah ada.
- Mobile di bawah 768px: sidebar menjadi sheet dari kiri, dipicu tombol berlabel "Menu" dengan `aria-label="Buka menu navigasi"`.
- Desktop: tetap bisa collapsed menjadi rail ikon. Tooltip tetap berfungsi.

### 7.2 Header

- Tinggi 56px mobile, 72px desktop.
- Isi: tombol menu (mobile), breadcrumb (desktop), spacer, tautan notifikasi nyata, dan (di halaman pengaturan) tautan kembali.
- Background kanvas, tanpa glass. Border bawah hanya saat ada konten yang di-scroll di belakangnya, jika mudah; jika tidak, tanpa border.
- Tidak ada search global palsu. Pencarian tetap di halaman inventaris dan halaman yang memang punya filter.
- Tidak ada avatar di header. Menu pengguna tetap di sidebar agar tidak ada dua menu yang sama.

### 7.3 Konten

- `main` memakai `id="main-content"` dan skip link "Lewati ke konten" di awal layout.
- Padding dan lebar konten diatur satu kali di `AppContent`, bukan di setiap halaman.
- Setiap halaman memakai `PageHeader` untuk h1, deskripsi, dan aksi. Tidak ada h1 ganda.

---

## 8. Halaman Login dan Auth

- Latar kanvas, kartu utama maksimum 1120px, radius 24px, dua kolom di `lg` ke atas.
- Kolom kiri: form maksimum 384px, rata tengah vertikal, padding 32px.
- Kolom kanan: panel biru `#2563EB`, sudut membulat, berisi:
  - cuplikan UI produk yang dibangun dari markup asli: sidebar mini dengan label nyata (Dashboard, Inventaris, Peminjaman, Approval, Checkout, Pengembalian), satu kartu ringkasan tanpa angka, dan chip status nyata (Menunggu, Disetujui, Terlambat);
  - headline "Inventaris rapi, peminjaman tercatat.";
  - subteks "Satu tempat untuk katalog aset, pengajuan, persetujuan, dan pengembalian.";
- Tidak ada tombol Google atau Facebook karena belum ada autentikasi sosial.
- Tidak ada pembatas "or".
- Tidak ada tautan daftar karena registrasi publik tidak ada. Ganti dengan kalimat "Akun anggota dibuat oleh WM." jika memang sesuai.
- Form: label terlihat, input 44px, tombol "Masuk" 44px, tautan "Lupa password?", checkbox "Ingat saya".
- Halaman `ForgotPassword`, `ResetPassword`, dan `ChangePassword` memakai layout split yang sama. Panel kanan tetap menampilkan cuplikan produk yang sama.
- Mobile di bawah `lg`: panel kanan disembunyikan. Form memenuhi lebar dengan padding 16px, logo di atas, dan tidak ada overflow di 320px.

---

## 9. Dashboard

### 9.1 Struktur

1. `PageHeader`: "Dashboard" dan "Ringkasan inventaris dan peminjaman."
2. Baris KPI: data yang sudah ada dari `DashboardController`, tanpa delta palsu. Grid 1 kolom di mobile, 2 di `sm`, 3 di `md`, 5 di `xl` bila jumlahnya 5.
3. Grid utama 2:1 di `lg`: tren pengajuan dan panel pengumuman.
4. Baris kedua: distribusi status dan aksi cepat nyata (Ajukan peminjaman, Lihat inventaris, Buka approval, atau Checkout sesuai peran).
5. Pengumuman tetap memakai data nyata dan empty state "Belum ada pengumuman."

### 9.2 Data baru

Tambahan di `DashboardController`, tanpa migrasi:

- `trend`: jumlah `BorrowRequest` per bulan selama 6 bulan terakhir. Untuk anggota, difilter `member_id`. Bulan kosong diisi nol di PHP.
- `statusBreakdown`: jumlah `BorrowRequest` per status saat ini. Untuk anggota, difilter `member_id`.
- `recentLoans` (opsional, hanya jika murah): 5 pinjaman terbaru milik anggota atau 5 transaksi terbaru untuk admin. Jika menambah query terlalu banyak, lewati; pengumuman sudah cukup.
- Semua label status diterjemahkan di frontend, bukan di database.

### 9.3 Chart

- **Tren:** area chart SVG sederhana. Sumbu Y mulai dari nol, grid horizontal halus, label bulan di sumbu X. Warna seri `primary`. Area fill memakai gradient biru ke transparan sebagai encoding volume, bukan dekorasi halaman.
- **Distribusi status:** bar horizontal CSS. Setiap baris menampilkan label status, jumlah, dan bar dengan warna semantik. Warna selalu didampingi teks.
- Setiap chart dibungkus `<figure>` dengan `<figcaption>` yang menyatakan pertanyaannya, misalnya "Pengajuan peminjaman, 6 bulan terakhir".
- Data tabel tersedia dalam elemen `sr-only` untuk pembaca layar.
- Jika tidak ada data: tampilkan empty state spesifik, bukan chart kosong.
- Tanpa tooltip interaktif di iterasi pertama. Nilai terbaca dari label dan tabel sr-only.

---

## 10. Halaman Lain

### 10.1 Katalog inventaris

- Header, toolbar filter dalam satu kartu, grid kartu barang di desktop dan daftar ringkas di mobile.
- Setiap kartu: nama, kode, kategori, lokasi, ketersediaan, dan aksi "Lihat detail" yang nyata.
- Filter yang ada dipertahankan. Empty state membedakan "belum ada barang" dan "filter tidak menemukan hasil", dengan aksi reset filter.
- Halaman detail: panel ringkasan, status ketersediaan, informasi lokasi, dan tombol "Tambah ke keranjang" dengan teks yang sama seperti sekarang.

### 10.2 Peminjaman

- Daftar pengajuan memakai kartu tabel, status chip, dan aksi yang sudah ada.
- Form pengajuan tetap memakai field dan alur yang sama. Tombol "Kirim pengajuan" tidak berubah.
- Detail pengajuan memakai layout dua kolom di desktop, satu kolom di mobile, dengan ringkasan status dan timeline yang jelas.

### 10.3 Admin, WM, dan pengaturan

- Halaman **Index dan Show** untuk approval, checkout, dan pengembalian termasuk halaman inti dan mendapat rework layout penuh: header, ringkasan status, panel detail dua kolom di desktop, alur scan QR yang lebih jelas, dan aksi yang tetap sama. Urutan aksi dan label tidak berubah.
- Tabel dan form admin inventaris, WM, laporan, perpanjangan, dan pengumuman memakai `TableShell` dan `PageHeader`: kartu putih, header sticky di dalam scroll container, border tipis, dan caption sr-only. Struktur data tidak dibongkar.
- Kolom prioritas tampil di mobile; kolom sekunder disembunyikan di bawah `md` atau `lg`. Aksi baris tetap terlihat.
- Status chip menggantikan warna hardcoded. Angka dan tanggal memakai `tabular-nums`.
- Form pengaturan memakai lebar baca maksimum 640px dan navigasi pengaturan berbentuk kartu di mobile, sidebar di desktop.
- `AppearanceTabs` dipertahankan, tetapi direstyle menjadi tiga kartu pilihan tema dengan label Light, Dark, System.
- Halaman QR tetap tanpa layout aplikasi dan selalu light untuk hasil cetak. Halaman ini diberi `color-scheme: light` dan print style, karena label gelap tidak cocok untuk dicetak.
- Dialog bawaan browser (`confirm` dan `prompt`) tetap dipakai pada iterasi ini karena sudah berfungsi dan diuji. Penggantian ke `Dialog` aplikasi dicatat sebagai follow-up, bukan bagian dari overhaul ini.
- Tautan footer sidebar "Repository" dan "Documentation" dihapus karena mengarah ke starter kit Laravel, bukan produk ini. Menu pengguna tetap.

### 10.4 Welcome

- Halaman `Welcome` bawaan starter kit diganti dengan redirect: pengguna login diarahkan ke dashboard, pengguna tamu diarahkan ke login.
- Alasan: ini aplikasi internal, bukan landing page publik. Halaman bawaan Laravel tidak memberi nilai produk dan menambah permukaan yang harus dirawat.
- Route `home` tetap ada agar tautan lama tidak rusak.

---

## 11. Komponen Bersama

Komponen baru di `resources/js/components/`:

| Komponen | Fungsi |
|---|---|
| `PageHeader.vue` | h1, deskripsi, slot aksi. Dipakai semua halaman. |
| `StatCard.vue` | Ikon, label, nilai, tautan nyata. Tanpa delta palsu. |
| `StatusBadge.vue` | Pemetaan status ke tone semantik, teks selalu ada. |
| `EmptyState.vue` | Judul, alasan, dan aksi. Bukan sekadar "No data". |
| `TableShell.vue` | Kartu tabel, overflow-x, caption sr-only, slot toolbar. |
| `TrendChart.vue` | Area chart SVG dari data nyata. |
| `StatusBars.vue` | Distribusi status dari data nyata. |
| `AuthShowcase.vue` | Panel biru login dengan cuplikan UI produk. |

Komponen UI dasar (`Button`, `Input`, `Card`, `Dialog`, `Sheet`, `Sidebar`) tetap dipakai. Token dan beberapa class di `app.css` disesuaikan. File `components/ui/*` yang dihasilkan shadcn hanya disentuh bila perlu.

---

## 12. State, Copy, dan Motion

### 12.1 State

- **Empty:** setiap daftar punya pesan spesifik dan aksi berikutnya. Contoh: "Belum ada pengajuan." dengan tombol "Ajukan peminjaman".
- **Loading:** progress bar Inertia tetap dipakai. Tombol submit menampilkan `Spinner` dan `disabled`. Form filter menonaktifkan kontrol selama proses jika mudah.
- **Error:** error validasi tetap memakai `InputError` dan `AlertError`. Error halaman memakai halaman error Laravel yang ada. Tidak ada state error palsu yang hanya dekorasi.

### 12.2 Copy

- Bahasa Indonesia, sentence case.
- CTA spesifik: "Ajukan peminjaman", "Buka approval", "Lihat inventaris", "Masuk", "Kirim pengajuan". Tidak ada "Get Started" atau "Learn More".
- Tidak ada buzzword, em dash, angka tanpa sumber, atau nama orang fiktif.
- Empty state menyebut penyebab dan langkah berikutnya.
- Istilah yang sudah dipakai e2e tidak diubah.

### 12.3 Motion

- Transisi 150ms untuk hover, fokus, dan perubahan warna.
- Buka-tutup sidebar mengikuti komponen yang ada.
- Tidak ada animasi masuk halaman, parallax, atau loop.
- Semua motion non-esensial dihentikan dengan `prefers-reduced-motion: reduce`.

---

## 13. Aksesibilitas

- Kontras teks minimal 4.5:1, teks besar 3:1. Semua pasangan token diukur dengan `contrast-check.py`.
- Non-text 3:1 untuk border input dan indikator fokus.
- Fokus `:focus-visible` 2px `ring` dengan offset 2px, terlihat di light dan dark.
- Semua kontrol native: `button`, `a[href]`, `label[for]`.
- Ikon-only memakai `aria-label`; ikon dekoratif `aria-hidden="true"`.
- `cursor: pointer` untuk semua kontrol aktif, `not-allowed` untuk disabled, diatur sekali di base layer.
- `aria-current="page"` pada item navigasi aktif.
- Skip link dan landmark `main`.
- Chart punya teks alternatif dan tabel sr-only.
- Target sentuh minimal 44px dengan jarak yang cukup.
- `prefers-reduced-motion` dihormati.
- Tidak ada `user-scalable=no`.

---

## 14. Responsif

| Area | 320-639 | 640-767 | 768-1023 | 1024+ |
|---|---|---|---|---|
| Sidebar | Sheet | Sheet | Rail atau sheet | Expanded 272px |
| Header | 56px, menu + notifikasi | 56px | 64px | 72px |
| KPI | 1 kolom | 2 kolom | 3 kolom | 5 kolom |
| Dashboard utama | 1 kolom | 1 kolom | 1 kolom | 2:1 |
| Login | 1 kolom, panel kanan sembunyi | 1 kolom | 1 kolom | 2 kolom |
| Tabel | Scroll horizontal, kolom prioritas | Sama | Kolom sekunder muncul | Semua kolom |
| Form | Aksi full width | Aksi inline | Aksi inline | Aksi inline |
| Chart | Tinggi 180px | 200px | 220px | 240px |

Breakpoint mengikuti Tailwind default dan dipilih karena konten mulai sesak di titik itu, bukan karena daftar perangkat.

---

## 15. File dan Area yang Disentuh

Perkiraan, bukan daftar final:

- `vite.config.ts`: font Plus Jakarta Sans.
- `resources/css/app.css`: token light/dark, font stack, base cursor, focus, reduced motion, utilitas tabel.
- `resources/js/app.ts`: warna progress bar.
- `resources/views/app.blade.php`: warna latar awal light/dark.
- `resources/js/components/AppSidebar.vue`, `AppSidebarHeader.vue`, `NavMain.vue`, `NavFooter.vue`, `NavUser.vue`: shell.
- `resources/js/components/AppContent.vue`, `AppShell.vue`: padding dan landmark.
- `resources/js/layouts/auth/AuthSplitLayout.vue`, `layouts/AuthLayout.vue`: login split.
- `resources/js/pages/auth/*.vue`: form dan copy.
- `resources/js/pages/Dashboard.vue`: kartu, chart, pengumuman, aksi.
- `app/Http/Controllers/DashboardController.php`: agregat tren dan status.
- `resources/js/components/*` baru: komponen bersama di bagian 11.
- Halaman `resources/js/pages/**/*.vue`: header, tabel, status chip, empty state, padding.
- `resources/js/layouts/settings/Layout.vue`, `pages/settings/*.vue`: pengaturan.
- `routes/web.php`: redirect Welcome.
- `public/favicon.svg`: monogram placeholder.

Tidak ada perubahan pada model, migrasi, policy, atau aturan domain.

---

## 16. Verifikasi

Wajib sebelum menyerahkan:

1. `bun run build`
2. `bun run types:check`
3. `bun run check`
4. `php artisan test` bila database test tersedia
5. `bun run e2e` bila database e2e tersedia
6. Kontras token dan pasangan teks dengan `contrast-check.py`
7. Browser manual di 320, 375, 768, 1024, dan 1440 piksel, light dan dark
8. Keyboard: Tab, Shift+Tab, Enter, Space, Escape pada dialog dan sheet
9. Zoom 200% pada lebar 375px
10. Laporan Delivery Gate antislop, satu baris per item, dengan bukti konkret

Jika database tidak tersedia, langkah 4 dan 5 dilaporkan sebagai tidak dijalankan, bukan PASS.

---

## 17. Keputusan dan Alasan

- **Token-first, bukan rewrite per halaman:** diff lebih kecil dan identitas konsisten.
- **Plus Jakarta Sans:** karakter institusional yang ramah, sesuai referensi dan teks Indonesia.
- **Biru `#2563EB` sebagai aksen utama:** menandai aksi dan status aktif, bukan menghias setiap elemen.
- **Kartu memakai border, bukan bayangan:** halaman data tetap datar dan mudah dipindai.
- **Chart buatan sendiri:** dua bentuk chart tidak sepadan dengan dependency baru.
- **Chart dari data nyata:** kejujuran data lebih penting daripada tampilan yang penuh.
- **Panel login memakai cuplikan UI asli:** bukan screenshot palsu dan tidak menampilkan angka karangan.
- **Tanpa social login:** belum ada autentikasi sosial, jadi tombolnya tidak boleh ada.
- **Welcome menjadi redirect:** aplikasi internal, bukan landing page.
- **Menu pengguna tetap di sidebar:** menghindari dua menu pengguna yang sama di layar.
- **Label dan kontrak e2e dipertahankan:** redesign tidak boleh memutus pengujian alur utama. Perubahan selector struktural hanya dipakai sebagai jalan terakhir.
- **Halaman Show admin termasuk halaman inti:** approval, checkout, dan pengembalian adalah layar kerja harian admin, bukan tabel biasa.
- **QR selalu light:** hasilnya untuk dicetak, bukan dibaca di layar gelap.
- **Dialog browser tetap dipakai:** berfungsi, aksesibel secara bawaan, dan sudah diuji. Penggantian ke dialog aplikasi adalah follow-up.
- **Tautan starter kit dihapus:** navigasi hanya berisi tujuan yang relevan dengan produk.

---

## 18. Yang Diserahkan ke Builder

- Strategi override class pada komponen sidebar shadcn, tanpa merusak perilaku collapsed dan mobile.
- Bentuk kurva area chart dan pemilihan titik label sumbu X agar tidak bertabrakan di 320px.
- Kolom mana yang menjadi prioritas di setiap tabel pada mobile.
- Apakah `recentLoans` layak ditambahkan setelah query dashboard diukur.
- Detail copy empty state per halaman, selama mengikuti aturan bagian 12.2.
- Urutan pengerjaan per halaman setelah fondasi dan dashboard selesai.
