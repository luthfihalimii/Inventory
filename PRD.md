# Product Requirements Document (PRD)

# ENT Inventory

**Dokumen:** Product Requirements Document  
**Versi:** 0.2 — Revised Draft  
**Status:** Draft untuk validasi tim dan persiapan development  
**Tanggal revisi:** 8 November 2026  
**Pemilik produk:** UKM Jurnalistik / ENT  
**Platform:** Responsive web application  
**Dokumen acuan:** `rencana-project.md` dan keputusan produk hasil diskusi tim

> **Ringkasan keputusan terbaru:** Sistem hanya menerima pengajuan dari anggota aktif; anggota yang terlambat mengembalikan barang otomatis diblokir dari peminjaman baru; serah terima menggunakan QR Code yang diverifikasi Admin/Korlab; dan approval dapat diberikan oleh Admin/Korlab atau WM. Untuk MVP, satu keputusan dari salah satu approver yang berwenang sudah cukup. Kedua role tidak perlu memberikan approval berurutan.

---

## 1. Ringkasan Produk

ENT Inventory adalah aplikasi web untuk mengelola inventaris, peminjaman, pengembalian, pemeriksaan kondisi barang, data anggota, pengumuman, dan riwayat aktivitas inventaris UKM Jurnalistik.

Produk ini dirancang sebagai pusat pengelolaan inventaris organisasi. Fokus utama sistem adalah memastikan barang hanya dipinjam oleh anggota yang memenuhi syarat, setiap pengajuan diperiksa oleh pihak berwenang, barang yang keluar diverifikasi melalui QR Code oleh Admin/Korlab, dan barang yang kembali diperiksa sebelum transaksi dinyatakan selesai.

### 1.1 Masalah yang ingin diselesaikan

- Data inventaris dan lokasi penyimpanan perlu dikelola dalam satu sumber data yang konsisten.
- Pengurus perlu mengetahui barang yang tersedia, dipesan, sedang dipinjam, terlambat dikembalikan, rusak, atau nonaktif.
- Peminjaman perlu dibatasi untuk anggota aktif dan harus melewati persetujuan.
- Proses pengambilan barang perlu diverifikasi agar barang yang diserahkan sesuai dengan pengajuan.
- Keterlambatan pengembalian harus terdeteksi dan berdampak langsung pada hak peminjaman anggota.
- Kondisi dan kelengkapan barang perlu terdokumentasi ketika keluar dan kembali.
- Data anggota, keputusan approval, dan aktivitas administratif perlu dapat ditelusuri, termasuk ketika terjadi pergantian pengurus.

### 1.2 Visi produk

Membangun sistem inventaris organisasi yang mudah digunakan oleh anggota, tetapi memiliki kontrol peminjaman, verifikasi barang, dan histori yang kuat bagi Admin/Korlab serta WM.

### 1.3 Prinsip produk

1. **Active members only:** hanya anggota dengan status aktif yang dapat mengajukan peminjaman.
2. **No approval, no handover:** barang tidak boleh keluar sebelum pengajuan disetujui dan serah terima diverifikasi.
3. **QR-assisted verification:** QR Code membantu mengidentifikasi barang, sedangkan keputusan penyerahan tetap dilakukan oleh Admin/Korlab.
4. **Automatic overdue restriction:** anggota yang memiliki peminjaman terlambat otomatis diblokir dari pengajuan baru.
5. **Condition before and after:** kondisi barang dicatat saat diserahkan dan saat dikembalikan.
6. **Traceable history:** keputusan dan perubahan penting memiliki catatan pelaku serta waktu.
7. **Server-side enforcement:** aturan akses, approval, stok, dan pemblokiran ditegakkan di backend.
8. **Separate operational roles:** User/Pengaju, Admin/Korlab, dan Super Admin/WM memiliki kewenangan yang jelas.

---

## 2. Tujuan, Metrik, dan Batas Keberhasilan

### 2.1 Tujuan produk

- Memusatkan data inventaris ENT dalam satu website.
- Menyediakan katalog barang yang dapat dicari dan difilter oleh anggota.
- Menyediakan alur peminjaman terjadwal dan mendadak.
- Mengharuskan pengajuan melewati approval Admin/Korlab atau WM.
- Memverifikasi barang yang benar-benar keluar dengan QR Code dan pemeriksaan Admin/Korlab.
- Memantau pengembalian, keterlambatan, dan tanggungan anggota.
- Memblokir otomatis anggota yang terlambat mengembalikan barang.
- Menyimpan histori transaksi dan aktivitas administratif untuk kebutuhan pertanggungjawaban.

### 2.2 Metrik keberhasilan

Target berikut merupakan sasaran awal dan dapat ditinjau setelah masa pilot.

| Metrik | Target awal |
|---|---:|
| Anggota aktif yang dapat login dan mengakses sistem | 100% anggota yang telah didaftarkan |
| Pengajuan baru dari akun nonaktif | 0 |
| Peminjaman resmi yang tercatat di sistem | 100% setelah go-live |
| Barang keluar tanpa approval | 0 |
| Serah terima yang diverifikasi Admin/Korlab | 100% |
| Serah terima barang serialized yang mencatat QR Code/unit aset | 100% |
| Transaksi terlambat yang terdeteksi sistem | 100% transaksi aktif yang melewati tenggat |
| Anggota overdue yang otomatis diblokir | 100% |
| Keputusan approval yang memiliki pelaku dan waktu | 100% |
| Transaksi yang memiliki catatan pengembalian | 100% transaksi yang dikembalikan |

### 2.3 Bukan tujuan (Non-goals)

Versi awal tidak ditujukan untuk:

- Menjadi aplikasi akuntansi atau pembukuan keuangan organisasi.
- Mengelola procurement, vendor, invoice, atau pengadaan secara penuh.
- Menggantikan sistem keanggotaan kampus secara otomatis tanpa integrasi resmi.
- Menyediakan aplikasi native Android/iOS. Produk awal berupa website responsif.
- Menyediakan tanda tangan elektronik tersertifikasi.
- Memberlakukan denda uang secara otomatis. Kebijakan sanksi finansial, jika ada, ditentukan terpisah oleh organisasi.

---

## 3. Pengguna, Role, dan Hak Akses

Sistem menggunakan **Role-Based Access Control (RBAC)**. Setiap permintaan ke backend harus diperiksa berdasarkan role, status akun, kepemilikan data, dan kewenangan tindakan.

### 3.1 Role utama

| Role | Tanggung jawab dan hak akses |
|---|---|
| **User / Pengaju** | Login menggunakan NRP dan password; melihat inventaris; membuat pengajuan; menyetujui SOP; melihat status serta riwayat pribadi; mengajukan pengembalian; melihat pengumuman. Hanya anggota aktif yang boleh mengajukan peminjaman. |
| **Admin / Korlab** | Melihat dashboard operasional; memberikan approval atau penolakan; mengelola inventaris; melakukan verifikasi QR Code dan serah terima; memverifikasi pengembalian; mencatat kondisi, kerusakan, dan tindak lanjut; mengelola pengumuman. |
| **Super Admin / WM** | Mengelola data anggota dan status keanggotaan; memberikan approval atau penolakan; melihat informasi seluruh peminjaman; melihat aktivitas administratif/audit; melihat dashboard umum dan pengaturan yang menjadi kewenangannya. WM tidak melakukan checkout atau verifikasi pengembalian dalam alur operasional default. |

### 3.2 Aturan kewenangan approval

- Admin/Korlab dan WM sama-sama termasuk role yang berwenang menyetujui atau menolak pengajuan.
- Untuk MVP, **satu keputusan dari salah satu approver yang berwenang sudah cukup** untuk menyelesaikan tahap approval.
- Pengajuan masuk ke antrean approval bersama yang dapat diakses Admin/Korlab dan WM sesuai hak akses.
- Approver pertama yang memberikan keputusan valid akan mengunci keputusan pengajuan. Approver lain hanya dapat melihat keputusan tersebut dan tidak boleh menimpa atau memberikan keputusan kedua.
- Penolakan wajib disertai alasan.
- Sistem mencatat role, identitas approver, waktu, keputusan, dan catatan.
- Jika organisasi kemudian menetapkan bahwa Admin/Korlab dan WM harus menyetujui secara berurutan, workflow dapat diubah menjadi multi-stage approval. Hal tersebut bukan aturan MVP saat ini.

### 3.3 Prinsip pemisahan akun dan larangan self-approval

- Admin/Korlab atau WM yang ingin meminjam barang sebagai anggota harus login menggunakan akun User terpisah, sesuai alur yang ditetapkan tim.
- Walaupun menggunakan akun terpisah, sistem tetap harus menghubungkan akun tersebut ke identitas anggota/NRP yang sama.
- Pengguna tidak boleh menyetujui pengajuan yang dibuat oleh dirinya sendiri.
- Sistem harus membandingkan identitas anggota peminjam dengan identitas approver, bukan hanya membandingkan ID akun login.
- Jika approver merupakan peminjam atau memiliki konflik kepentingan pada pengajuan tertentu, approver tersebut tidak dapat memutuskan pengajuan itu. Approver lain yang berwenang dapat memprosesnya.

### 3.4 Status anggota dan akun

Status anggota minimal:

- `active`: anggota aktif dan dapat mengajukan peminjaman selama tidak memiliki blokir overdue.
- `inactive`: anggota tidak aktif dan tidak dapat membuat pengajuan baru.

Status pembatasan peminjaman merupakan kondisi terpisah dari status keanggotaan:

- `eligible`: dapat mengajukan jika seluruh validasi lain terpenuhi.
- `borrowing_blocked`: tidak dapat membuat pengajuan baru karena masih memiliki transaksi overdue.

Anggota nonaktif atau anggota yang diblokir tetap harus tercatat sebagai penanggung jawab transaksi yang belum selesai. Menonaktifkan akun tidak menghapus histori maupun kewajiban peminjaman.

---

## 4. Ruang Lingkup Fitur

### 4.1 MVP — Wajib tersedia

1. Login dan logout menggunakan NRP serta password.
2. Manajemen anggota oleh Super Admin/WM.
3. Manajemen role dan data divisi.
4. Dashboard berbeda untuk User, Admin/Korlab, dan WM.
5. Katalog inventaris, kategori, kode barang, jumlah, harga, status, lokasi loker, dan keterangan.
6. Pencarian serta filter inventaris.
7. Pengajuan peminjaman dengan kategori Terjadwal atau Mendadak.
8. Validasi syarat H-2 untuk pengajuan Terjadwal.
9. Pengecualian H-2 dan prioritas antrean untuk pengajuan Mendadak.
10. Persetujuan atau penolakan oleh Admin/Korlab atau WM.
11. Konfirmasi dan pencatatan persetujuan SOP.
12. Validasi stok, reservasi, dan pencegahan double booking.
13. Pembuatan serta pemindaian QR Code barang untuk serah terima.
14. Verifikasi serah terima oleh Admin/Korlab.
15. Peminjaman Saya dan Riwayat Peminjaman.
16. Pengajuan pengembalian oleh User.
17. Verifikasi pengembalian dan pemeriksaan kondisi oleh Admin/Korlab.
18. Deteksi overdue dan pemblokiran otomatis pengajuan baru.
19. Pengumuman yang dapat dikelola Admin/Korlab dan dilihat anggota.
20. Informasi peminjaman dan aktivitas administratif untuk WM.
21. Audit log untuk aktivitas penting.
22. Notifikasi internal dasar.

### 4.2 V1 — Setelah MVP stabil

- Ekspor laporan PDF/Excel.
- Pengingat otomatis H-1 dan notifikasi kanal eksternal yang disetujui organisasi.
- Riwayat maintenance dan jadwal perawatan.
- Upload beberapa foto untuk kondisi barang.
- Kalender ketersediaan.
- SOP versioning dan arsip versi SOP yang lebih lengkap jika belum sempat diselesaikan di MVP.
- Delegasi approver dengan audit trail.

### 4.3 V2 — Pengembangan lanjutan

- PWA dan peningkatan pengalaman mobile.
- Analitik pemakaian aset dan tren peminjaman.
- Integrasi ke sistem keanggotaan atau sistem internal kampus jika tersedia.
- Pencatatan biaya perbaikan dan penggantian barang.
- Persetujuan multi-stage yang dapat dikonfigurasi apabila kebijakan organisasi membutuhkannya.

---

## 5. Modul dan Kebutuhan Fungsional

Prioritas: **P0** = wajib untuk MVP; **P1** = setelah MVP stabil; **P2** = pengembangan lanjutan.

### 5.1 Authentication dan User Management

| ID | Prioritas | Kebutuhan |
|---|---|---|
| AUTH-01 | P0 | User, Admin/Korlab, dan WM dapat login menggunakan kredensial akun yang terdaftar. |
| AUTH-02 | P0 | Login User menggunakan NRP dan password. |
| AUTH-03 | P0 | Sistem memvalidasi akun dan status anggota sebelum memberikan akses pengajuan. |
| AUTH-04 | P0 | NRP harus unik dan terhubung ke satu identitas anggota. |
| AUTH-05 | P0 | Sistem menampilkan nama, NRP, dan divisi dari profil anggota pada formulir peminjaman; data tersebut bersifat read-only bagi User. |
| AUTH-06 | P0 | WM dapat menambah, mengubah, mencari, menyaring, dan menonaktifkan data anggota. |
| AUTH-07 | P0 | Data anggota memuat kode anggota, nama, NRP, divisi, generasi, jabatan, keterangan tambahan, dan status. |
| AUTH-08 | P0 | Hanya anggota berstatus `active` yang dapat membuat pengajuan baru. |
| AUTH-09 | P0 | Akun Admin/Korlab dan WM yang digunakan untuk tugas administratif terpisah dari akun User yang digunakan untuk peminjaman pribadi. |
| AUTH-10 | P0 | Sistem menerapkan larangan self-approval berdasarkan identitas anggota/NRP, termasuk lintas akun. |
| AUTH-11 | P1 | Sistem menyediakan reset password melalui proses yang aman. |
| AUTH-12 | P1 | Sistem menyediakan pencabutan sesi dan pengelolaan akses akun. |

### 5.2 Asset dan Inventory Management

| ID | Prioritas | Kebutuhan |
|---|---|---|
| INV-01 | P0 | Admin/Korlab dapat menambah, melihat, mengubah, dan menonaktifkan inventaris. |
| INV-02 | P0 | Data inventaris memuat kode, nama, kategori, jumlah, harga/nilai jika tersedia, tanggal input, status, lokasi loker, dan keterangan. |
| INV-03 | P0 | Kode inventaris harus unik. |
| INV-04 | P0 | Inventaris dapat dicari atau difilter berdasarkan nama, kode, kategori, dan status. |
| INV-05 | P0 | Sistem menampilkan detail barang, jumlah, status, dan lokasi penyimpanan. |
| INV-06 | P0 | Sistem membedakan barang yang dilacak per unit dan barang yang dilacak berdasarkan kuantitas. |
| INV-07 | P0 | Sistem menampilkan stok total, tersedia, dipesan, dipinjam, rusak, dan nonaktif sesuai tipe barang. |
| INV-08 | P0 | Inventaris yang dinonaktifkan tetap memiliki data historis dan tidak dapat diajukan untuk peminjaman baru. |
| INV-09 | P0 | Setiap barang atau unit yang membutuhkan identifikasi fisik memiliki QR Code unik atau QR Code SKU sesuai metode pelacakan. |
| INV-10 | P0 | Admin/Korlab dapat menampilkan dan mencetak QR Code untuk ditempel pada barang. |
| INV-11 | P0 | Sistem mencatat perubahan jumlah/stok agar perubahan dapat ditelusuri. |
| INV-12 | P1 | Sistem menyimpan foto dan dokumen pendukung inventaris. |

**Jenis pelacakan barang:**

- **Per unit / serialized:** digunakan untuk barang yang perlu ditelusuri satu per satu, misalnya kamera, lensa, laptop, tripod, recorder, atau drone. Setiap unit memiliki kode aset dan QR Code masing-masing.
- **Berdasarkan kuantitas / quantity-tracked:** digunakan untuk barang yang dikelola berdasarkan jumlah, misalnya kabel, baterai umum, alat tulis, atau perlengkapan kecil. QR Code dapat mengidentifikasi SKU barang, lalu Admin/Korlab memverifikasi jumlah yang diserahkan.

QR Code hanya berfungsi untuk identifikasi barang. QR Code bukan bukti persetujuan dan tidak memberikan hak kepada anggota untuk mengambil barang tanpa verifikasi Admin/Korlab.

### 5.3 Borrowing Request — Pengajuan Peminjaman

| ID | Prioritas | Kebutuhan |
|---|---|---|
| BOR-01 | P0 | Hanya anggota berstatus aktif dan tidak sedang diblokir yang dapat membuat pengajuan. |
| BOR-02 | P0 | User dapat memilih satu atau beberapa barang ke dalam satu pengajuan. |
| BOR-03 | P0 | Sistem memvalidasi ketersediaan dan jumlah barang yang diminta. |
| BOR-04 | P0 | User dapat menambahkan barang lain sebelum mengirim pengajuan. |
| BOR-05 | P0 | Sistem menampilkan ringkasan barang dan jumlah sebelum pengajuan dikirim. |
| BOR-06 | P0 | Formulir mengisi NRP, nama, dan divisi otomatis berdasarkan akun anggota dan membuatnya read-only bagi User. |
| BOR-07 | P0 | User memilih kategori pengajuan: `Terjadwal` atau `Mendadak`. |
| BOR-08 | P0 | User mengisi waktu pengambilan/mulai peminjaman dan waktu pengembalian yang direncanakan. |
| BOR-09 | P0 | User mengisi lokasi penggunaan dan/atau informasi lokasi loker/pengambilan sesuai kebutuhan operasional. |
| BOR-10 | P0 | User dapat menambahkan keterangan atau alasan peminjaman. |
| BOR-11 | P0 | Sistem secara otomatis mencatat waktu pengajuan; User tidak dapat mengubah timestamp pengajuan. |
| BOR-12 | P0 | Untuk kategori Terjadwal, sistem memvalidasi ketentuan pengajuan minimal H-2. |
| BOR-13 | P0 | Untuk kategori Mendadak, pengecualian H-2 diberikan dan pengajuan diberi penanda prioritas. |
| BOR-14 | P0 | Pengajuan Mendadak tetap wajib melewati approval dan pemeriksaan ketersediaan. |
| BOR-15 | P0 | User wajib membaca dan mengonfirmasi SOP peminjaman sebelum mengirim pengajuan. |
| BOR-16 | P0 | Sistem menyimpan versi atau identitas SOP yang disetujui oleh User. |
| BOR-17 | P0 | Sistem memberikan nomor referensi unik untuk setiap pengajuan. |
| BOR-18 | P0 | User dapat melihat daftar pengajuan, status, dan detail keputusan melalui menu Peminjaman Saya. |
| BOR-19 | P0 | User dapat membatalkan pengajuan sebelum barang diserahkan sesuai aturan pembatalan. |
| BOR-20 | P1 | User dapat mengajukan perpanjangan sebelum tenggat dengan alasan dan tanggal baru. |

**Data minimal formulir:**

- Identitas anggota: NRP, nama, divisi (otomatis dari akun).
- Kategori: Terjadwal atau Mendadak.
- Barang dan jumlah/unit.
- Waktu pengambilan atau mulai peminjaman.
- Waktu berakhir atau tenggat pengembalian.
- Lokasi penggunaan dan/atau lokasi loker/pengambilan.
- Nama kegiatan atau tujuan peminjaman jika diperlukan.
- Keterangan tambahan/alasan.
- Konfirmasi SOP.

### 5.4 Approval Workflow

| ID | Prioritas | Kebutuhan |
|---|---|---|
| APR-01 | P0 | Pengajuan valid masuk ke antrean approval Admin/Korlab dan WM. |
| APR-02 | P0 | Admin/Korlab maupun WM dapat membuka detail pengajuan yang menunggu keputusan. |
| APR-03 | P0 | Admin/Korlab maupun WM dapat menyetujui atau menolak pengajuan. |
| APR-04 | P0 | Satu keputusan dari salah satu approver yang berwenang sudah cukup untuk MVP. |
| APR-05 | P0 | Keputusan pertama yang berhasil disimpan mengunci status pengajuan; approver lain tidak dapat menimpa keputusan tersebut. |
| APR-06 | P0 | Approver wajib mengisi alasan ketika menolak pengajuan. |
| APR-07 | P0 | Sistem memprioritaskan pengajuan kategori Mendadak dalam antrean pemeriksaan, tanpa mengabaikan antrean Terjadwal. |
| APR-08 | P0 | Approval memeriksa status anggota, status blokir, detail barang, jadwal, dan ketersediaan. |
| APR-09 | P0 | Sistem menolak self-approval berdasarkan identitas anggota/NRP. |
| APR-10 | P0 | Sistem menyimpan identitas approver, role saat keputusan dibuat, waktu, status, dan catatan. |
| APR-11 | P0 | Pengajuan yang ditolak tidak dapat dilanjutkan ke tahap reservasi atau checkout. |
| APR-12 | P0 | Pengajuan yang disetujui berubah menjadi status Disetujui dan barang dicatat sebagai dipesan/reserved. |
| APR-13 | P1 | Sistem dapat mendukung delegasi approver dengan audit trail. |

**Aturan approval MVP:**

`Submitted → Pending Approval → Approved`

atau

`Submitted → Pending Approval → Rejected`

Admin/Korlab dan WM merupakan dua role yang sama-sama berwenang. Pengajuan tidak memerlukan dua persetujuan berurutan. Apabila keputusan diberikan oleh salah satu role, role lainnya melihat hasil keputusan dan riwayatnya.

### 5.5 Reservation dan Availability

| ID | Prioritas | Kebutuhan |
|---|---|---|
| RES-01 | P0 | Pengajuan yang disetujui membuat reservasi untuk barang dan periode yang disetujui. |
| RES-02 | P0 | Reservasi mencegah barang yang sama dialokasikan ke pengajuan lain pada rentang waktu yang bertabrakan. |
| RES-03 | P0 | Pengajuan yang ditolak atau dibatalkan melepaskan alokasi reservasi jika sudah terbentuk. |
| RES-04 | P0 | Sistem menghitung ketersediaan dari stok, reservasi, transaksi aktif, dan status barang. |
| RES-05 | P0 | Approval dan pembuatan reservasi harus menggunakan transaksi database serta locking untuk mencegah double booking. |
| RES-06 | P0 | Admin/Korlab dapat melihat status dan jadwal reservasi. |
| RES-07 | P1 | Anggota dapat melihat kalender ketersediaan. |

### 5.6 Handover / Checkout — QR Code dan Serah Terima

| ID | Prioritas | Kebutuhan |
|---|---|---|
| OUT-01 | P0 | Barang hanya dapat diserahkan jika pengajuan berstatus Disetujui. |
| OUT-02 | P0 | Proses serah terima dilakukan dan diverifikasi oleh Admin/Korlab. |
| OUT-03 | P0 | Admin/Korlab memindai QR Code barang atau memilih hasil pemindaian pada halaman checkout. |
| OUT-04 | P0 | Sistem memvalidasi QR Code terhadap barang/unit yang tercantum pada pengajuan. |
| OUT-05 | P0 | Sistem menolak QR Code barang yang tidak sesuai, nonaktif, rusak, sedang dipinjam, atau tidak termasuk pengajuan. |
| OUT-06 | P0 | Untuk barang serialized, Admin/Korlab mencatat unit aset yang benar-benar diserahkan. |
| OUT-07 | P0 | Untuk barang quantity-tracked, Admin/Korlab mencatat jumlah aktual yang diserahkan. |
| OUT-08 | P0 | Admin/Korlab memeriksa kondisi awal dan kelengkapan barang menggunakan checklist. |
| OUT-09 | P0 | Sistem mencatat tanggal/waktu aktual barang keluar dan identitas petugas yang menyerahkan. |
| OUT-10 | P0 | Peminjam mengonfirmasi penerimaan barang melalui akun atau mekanisme konfirmasi yang disediakan. |
| OUT-11 | P0 | Setelah serah terima berhasil, status transaksi berubah menjadi Sedang Dipinjam dan stok/unit tercatat keluar. |
| OUT-12 | P0 | Sistem menyimpan bukti serah terima, hasil pemindaian, checklist, dan penanggung jawab. |

**Aturan keamanan QR Code:**

- QR Code menggunakan identifier unik atau token yang tidak mengandung password, NRP, atau data pribadi yang tidak diperlukan.
- QR Code tidak boleh langsung mengubah status barang menjadi dipinjam tanpa sesi Admin/Korlab yang terautentikasi.
- Pemindaian harus diikuti pemeriksaan bahwa barang sesuai pengajuan dan masih tersedia.
- Setiap hasil pemindaian serta checkout dicatat pada audit log.

### 5.7 Return dan Inspection — Pengembalian

| ID | Prioritas | Kebutuhan |
|---|---|---|
| RET-01 | P0 | User dapat mengajukan pengembalian dari menu Peminjaman Saya ketika status transaksi Sedang Dipinjam atau Terlambat. |
| RET-02 | P0 | Setelah pengajuan pengembalian dikirim, status menjadi Menunggu Verifikasi Pengembalian. |
| RET-03 | P0 | Admin/Korlab melihat antrean pengembalian yang menunggu verifikasi. |
| RET-04 | P0 | Admin/Korlab mencatat tanggal/waktu aktual barang diterima. |
| RET-05 | P0 | Admin/Korlab memeriksa kondisi fisik, jumlah, dan kelengkapan barang. |
| RET-06 | P0 | Hasil pemeriksaan dapat berupa baik, rusak, tidak lengkap, atau indikasi hilang. |
| RET-07 | P0 | Sistem menyimpan hasil pemeriksaan, catatan, petugas penerima, dan bukti foto sesuai kebijakan. |
| RET-08 | P0 | Jika barang lengkap dan sesuai, Admin/Korlab memverifikasi pengembalian dan status transaksi berubah menjadi Selesai. |
| RET-09 | P0 | Jika ada kerusakan, kekurangan, atau kehilangan, Admin/Korlab mencatat kondisi dan tindak lanjut sebelum transaksi diselesaikan. |
| RET-10 | P0 | Barang rusak atau hilang tidak otomatis kembali ke stok tersedia. |
| RET-11 | P0 | Transaksi tidak boleh diselesaikan selama hasil pemeriksaan belum memiliki keputusan/tindak lanjut yang tercatat. |
| RET-12 | P1 | Sistem mendukung pengembalian sebagian untuk pengajuan yang berisi beberapa barang, dengan status per item. |

### 5.8 Overdue, Automatic Borrowing Block, dan Extension

| ID | Prioritas | Kebutuhan |
|---|---|---|
| OVR-01 | P0 | Sistem mendeteksi transaksi yang belum selesai dan telah melewati tenggat pengembalian. |
| OVR-02 | P0 | Status transaksi terlambat ditandai otomatis sebagai Terlambat/Overdue. |
| OVR-03 | P0 | Ketika anggota memiliki transaksi overdue, sistem otomatis mengaktifkan blokir peminjaman baru pada identitas anggota tersebut. |
| OVR-04 | P0 | Anggota yang diblokir tidak dapat mengirim pengajuan baru, termasuk melalui akun lain yang terhubung ke NRP/identitas anggota yang sama. |
| OVR-05 | P0 | Blokir diterapkan di backend dan tidak dapat dilewati hanya dengan mengakses URL atau mengubah request dari frontend. |
| OVR-06 | P0 | Sistem menampilkan alasan blokir dan transaksi yang menyebabkan anggota diblokir. |
| OVR-07 | P0 | Admin/Korlab dan WM dapat melihat daftar anggota yang diblokir beserta detail keterlambatannya. |
| OVR-08 | P0 | Blokir otomatis dilepas setelah seluruh transaksi overdue milik anggota dikembalikan dan diverifikasi oleh Admin/Korlab, sepanjang tidak ada blokir lain yang masih aktif. |
| OVR-09 | P0 | Sistem menyimpan waktu mulai dan berakhirnya blokir pada histori anggota/audit log. |
| OVR-10 | P1 | Sistem mengirimkan pengingat sebelum tenggat dan notifikasi setelah melewati tenggat. |
| OVR-11 | P1 | User dapat meminta perpanjangan sebelum tenggat; perpanjangan memerlukan persetujuan dan validasi ketersediaan ulang. |

**Aturan utama:** keterlambatan bukan sekadar label pada dashboard. Begitu tenggat terlewati dan barang belum dikembalikan serta diverifikasi, hak pengajuan peminjaman baru anggota otomatis diblokir. Pemblokiran berlaku pada identitas anggota, bukan hanya satu akun login.

### 5.9 Damage, Loss, dan Maintenance

| ID | Prioritas | Kebutuhan |
|---|---|---|
| DMG-01 | P0 | Admin/Korlab dapat mencatat kerusakan, kehilangan, atau kekurangan komponen. |
| DMG-02 | P0 | Laporan terkait dengan aset, transaksi, dan anggota yang bertanggung jawab jika relevan. |
| DMG-03 | P0 | Laporan memuat kronologi, kategori masalah, foto/catatan, waktu, dan petugas pemeriksa. |
| DMG-04 | P0 | Barang rusak atau hilang tidak dihitung sebagai barang tersedia. |
| DMG-05 | P1 | Sistem menyimpan riwayat maintenance, biaya, tanggal, dan petugas/vendor perbaikan. |

### 5.10 Dashboard, Riwayat, dan Pengumuman

| ID | Prioritas | Kebutuhan |
|---|---|---|
| DASH-01 | P0 | Dashboard User menampilkan ringkasan inventaris, pengajuan aktif, status peminjaman, dan pengumuman. |
| DASH-02 | P0 | Dashboard Admin/Korlab menampilkan antrean approval, peminjaman aktif, pengembalian menunggu verifikasi, dan ringkasan inventaris. |
| DASH-03 | P0 | Dashboard WM menampilkan ringkasan anggota, inventaris, dan peminjaman. |
| DASH-04 | P0 | User dapat melihat Peminjaman Saya dan Riwayat Peminjaman pribadi. |
| DASH-05 | P0 | Admin/Korlab dapat mencari riwayat berdasarkan anggota, divisi, barang, tanggal, dan status. |
| DASH-06 | P0 | WM dapat melihat seluruh informasi peminjaman beserta keputusan approval. |
| DASH-07 | P0 | Admin/Korlab dapat membuat, mengubah, melihat, dan mengatur status publikasi pengumuman. |
| DASH-08 | P0 | User dapat melihat daftar dan detail pengumuman yang dipublikasikan. |
| DASH-09 | P0 | WM dapat melihat aktivitas administratif yang mencatat pelaku, tindakan, data terkait, dan waktu perubahan. |
| DASH-10 | P1 | Admin/Korlab dan WM dapat mengekspor laporan sesuai hak akses. |

### 5.11 Notification

| ID | Prioritas | Kebutuhan |
|---|---|---|
| NOT-01 | P0 | User menerima notifikasi saat pengajuan berhasil dikirim. |
| NOT-02 | P0 | User menerima notifikasi saat pengajuan disetujui atau ditolak. |
| NOT-03 | P0 | Admin/Korlab dan WM menerima/menemukan informasi pengajuan baru pada antrean approval. |
| NOT-04 | P0 | Admin/Korlab melihat permintaan pengembalian yang menunggu verifikasi. |
| NOT-05 | P0 | User melihat status overdue dan alasan blokir peminjaman. |
| NOT-06 | P1 | Sistem mengirim pengingat sebelum tenggat dan pemberitahuan keterlambatan melalui kanal eksternal yang dipilih organisasi. |

### 5.12 Audit Log

| ID | Prioritas | Kebutuhan |
|---|---|---|
| AUD-01 | P0 | Sistem mencatat login penting dan kegagalan autentikasi yang relevan. |
| AUD-02 | P0 | Sistem mencatat perubahan data anggota, role, status anggota, dan divisi. |
| AUD-03 | P0 | Sistem mencatat pembuatan/perubahan/nonaktif inventaris dan perubahan stok. |
| AUD-04 | P0 | Sistem mencatat approval, penolakan, checkout, pemindaian QR, return, dan pemeriksaan. |
| AUD-05 | P0 | Sistem mencatat status overdue, pemblokiran, dan pembukaan blokir otomatis. |
| AUD-06 | P0 | Audit log menyimpan pelaku, tindakan, objek terkait, waktu, dan metadata relevan. |
| AUD-07 | P0 | User biasa tidak dapat mengubah atau menghapus audit log. |

---

## 6. Alur Pengguna Utama

### 6.1 Alur User — Login

1. User membuka website ENT Inventory.
2. User memasukkan NRP dan password.
3. Sistem memvalidasi kredensial.
4. Jika tidak valid, sistem menampilkan pesan kesalahan.
5. Jika valid, sistem memeriksa status anggota.
6. Sistem menampilkan Dashboard User.
7. Jika anggota tidak aktif, anggota tidak dapat membuat pengajuan baru.
8. Jika anggota sedang diblokir karena overdue, sistem tetap mengizinkan akses informasi akun dan riwayat, tetapi menolak pengajuan baru.

### 6.2 Alur User — Memilih Inventaris dan Mengajukan Peminjaman

1. User membuka menu Inventaris.
2. Sistem menampilkan katalog inventaris.
3. User mencari atau memfilter barang berdasarkan nama, kode, kategori, atau status.
4. User memilih barang.
5. Sistem menampilkan detail barang, jumlah, status, dan lokasi penyimpanan.
6. Sistem memeriksa ketersediaan.
7. Jika tidak tersedia, sistem menampilkan informasi bahwa barang tidak dapat dipinjam pada periode yang diminta.
8. Jika tersedia, User memasukkan jumlah atau memilih unit yang diinginkan.
9. User dapat menambahkan barang lain atau melanjutkan.
10. Sistem menampilkan ringkasan pengajuan.
11. User mengonfirmasi bahwa ia akan melanjutkan proses peminjaman.
12. Sistem menampilkan SOP peminjaman.
13. User membaca dan menyetujui SOP.
14. Sistem menampilkan formulir peminjaman.
15. Sistem mengisi NRP, nama, dan divisi dari profil anggota secara otomatis dan read-only.
16. User memilih kategori Terjadwal atau Mendadak.
17. User mengisi waktu pengambilan, waktu pengembalian, lokasi, tujuan/keterangan, dan data lain yang diwajibkan.
18. Sistem memvalidasi syarat H-2 untuk kategori Terjadwal.
19. Jika kategori Mendadak, sistem mengecualikan aturan H-2 dan memberi penanda prioritas.
20. Sistem memvalidasi status anggota, status blokir, kelengkapan formulir, jumlah, jadwal, dan stok.
21. Jika tidak valid, sistem menampilkan kesalahan dan meminta User memperbaiki data atau menyelesaikan tanggungan.
22. Jika valid, sistem menampilkan ringkasan akhir.
23. User mengirim pengajuan.
24. Sistem menyimpan pengajuan dengan status Menunggu Persetujuan dan memasukkannya ke antrean Admin/Korlab dan WM.

### 6.3 Alur Admin/Korlab atau WM — Approval Peminjaman

1. Approver login dan membuka menu Manajemen Peminjaman/Approval.
2. Sistem menampilkan daftar pengajuan yang menunggu persetujuan.
3. Pengajuan kategori Mendadak diberi penanda dan diprioritaskan untuk diperiksa.
4. Approver memilih pengajuan.
5. Sistem menampilkan identitas peminjam, NRP, divisi, barang, jumlah, waktu, kategori, dan keterangan.
6. Approver memeriksa status anggota, status blokir, ketersediaan, kepatuhan H-2 atau pengecualian Mendadak, serta kelayakan pengajuan.
7. Approver memilih Setujui atau Tolak.
8. Jika ditolak, approver wajib mengisi alasan.
9. Sistem menyimpan keputusan dan identitas approver.
10. Jika disetujui, status pengajuan berubah menjadi Disetujui dan barang dicatat sebagai reserved/dipesan.
11. Jika ditolak, status pengajuan berubah menjadi Ditolak dan alasan ditampilkan kepada User.
12. Keputusan pertama yang berhasil disimpan mengunci pengajuan; approver lain tidak dapat memberikan keputusan yang bertentangan.

### 6.4 Alur Admin/Korlab — Serah Terima QR Code

1. User datang mengambil barang yang pengajuannya telah disetujui.
2. Admin/Korlab membuka transaksi yang berstatus Disetujui.
3. Admin/Korlab memindai QR Code barang menggunakan perangkat yang tersedia.
4. Sistem mencocokkan QR Code dengan barang/unit yang tercantum dalam pengajuan.
5. Jika QR Code tidak sesuai atau barang tidak layak diserahkan, sistem menolak checkout dan menampilkan alasannya.
6. Jika sesuai, Admin/Korlab memeriksa kondisi dan kelengkapan barang.
7. Admin/Korlab mencatat jumlah/unit aktual, kondisi awal, waktu keluar, dan catatan jika ada.
8. User mengonfirmasi penerimaan barang.
9. Admin/Korlab menyelesaikan proses serah terima.
10. Sistem mencatat petugas dan bukti serah terima.
11. Status transaksi berubah menjadi Sedang Dipinjam.

### 6.5 Alur User — Peminjaman Saya dan Pengembalian

1. User membuka menu Peminjaman Saya.
2. Sistem menampilkan pengajuan dan transaksi milik User.
3. User memilih satu pengajuan/transaksi.
4. Sistem menampilkan detail sesuai status: Menunggu Persetujuan, Ditolak, Disetujui, Sedang Dipinjam, Terlambat, Menunggu Verifikasi Pengembalian, atau Selesai.
5. Jika status Sedang Dipinjam atau Terlambat, User dapat mengajukan pengembalian.
6. Sistem menyimpan permintaan pengembalian dan mengubah status menjadi Menunggu Verifikasi Pengembalian.
7. User mengembalikan barang secara fisik kepada Admin/Korlab.
8. Admin/Korlab memeriksa QR Code/unit, jumlah, kondisi, dan kelengkapan barang.
9. Jika lengkap dan sesuai, Admin/Korlab memverifikasi pengembalian.
10. Sistem mengubah transaksi menjadi Selesai dan memperbarui ketersediaan barang.
11. Jika ada kerusakan, kehilangan, atau kekurangan, Admin/Korlab membuat catatan insiden dan menentukan tindak lanjut sebelum transaksi ditutup.

### 6.6 Alur Keterlambatan dan Pemblokiran Otomatis

1. Sistem memeriksa transaksi aktif berdasarkan tenggat pengembalian.
2. Jika tenggat terlewati dan transaksi belum selesai diverifikasi, sistem menandai transaksi sebagai Terlambat.
3. Sistem mengaktifkan status `borrowing_blocked` pada identitas anggota.
4. User tetap dapat login, melihat transaksi, dan mengajukan pengembalian.
5. Jika User mencoba membuat pengajuan baru, backend menolak permintaan dan menampilkan alasan serta transaksi overdue yang harus diselesaikan.
6. Admin/Korlab menerima informasi keterlambatan dan menindaklanjuti pengembalian.
7. Setelah barang dikembalikan dan diverifikasi, sistem menghitung kembali seluruh transaksi overdue anggota.
8. Jika tidak ada transaksi overdue lain, sistem melepas blokir secara otomatis.
9. Seluruh perubahan status blokir dicatat pada audit log.

### 6.7 Alur Super Admin/WM — Manajemen Anggota

1. WM membuka menu Manajemen Anggota.
2. Sistem menampilkan daftar anggota ENT.
3. WM mencari atau menyaring anggota.
4. WM dapat menambah anggota dengan mengisi kode anggota, nama, NRP, kredensial, divisi, generasi, jabatan, keterangan tambahan, dan status.
5. Sistem memvalidasi kelengkapan serta keunikan kode anggota dan NRP.
6. WM dapat mengubah data anggota.
7. WM dapat menonaktifkan anggota setelah konfirmasi.
8. Riwayat peminjaman anggota tetap disimpan setelah akun dinonaktifkan.

### 6.8 Alur Pengumuman dan Logout

- User dapat melihat daftar dan detail pengumuman yang dipublikasikan.
- Admin/Korlab dapat menambah, mengubah, melihat, dan mengatur status publikasi pengumuman.
- Semua role dapat keluar dari sistem; sistem mengakhiri sesi login dan menampilkan halaman login.

---

## 7. Status dan State Machine

Status tampilan dapat menggunakan Bahasa Indonesia, sedangkan nilai internal database disarankan memakai enum/string konsisten.

### 7.1 Status pengajuan (`borrow_requests.status`)

| Status internal | Label UI | Keterangan |
|---|---|---|
| `pending_approval` | Menunggu Persetujuan | Pengajuan telah dikirim dan menunggu keputusan Admin/Korlab atau WM. |
| `approved` | Disetujui | Pengajuan mendapat persetujuan dan barang telah direservasi. |
| `rejected` | Ditolak | Pengajuan ditolak dan alasan tersimpan. |
| `cancelled` | Dibatalkan | Pengajuan dibatalkan sebelum barang diserahkan. |

Transisi utama:

`pending_approval → approved | rejected | cancelled`

### 7.2 Status transaksi pinjaman (`loan_transactions.status`)

| Status internal | Label UI | Keterangan |
|---|---|---|
| `reserved` | Disetujui / Menunggu Pengambilan | Pengajuan disetujui dan barang dipesan, tetapi belum diserahkan. |
| `borrowed` | Sedang Dipinjam | Barang telah diserahkan dan checkout tercatat. |
| `overdue` | Terlambat | Tenggat terlewati dan pengembalian belum diverifikasi. |
| `return_pending_verification` | Menunggu Verifikasi Pengembalian | User telah mengajukan pengembalian dan barang menunggu pemeriksaan Admin/Korlab. |
| `completed` | Selesai | Pengembalian telah diverifikasi dan transaksi selesai. |
| `cancelled` | Dibatalkan | Reservasi/peminjaman dibatalkan sesuai aturan. |

Transisi utama:

`reserved → borrowed → return_pending_verification → completed`

Transaksi `borrowed` dapat menjadi `overdue` ketika tenggat terlewati. Transaksi `overdue` tetap dapat dilanjutkan ke `return_pending_verification` dan `completed`.

### 7.3 Status aset/barang

Status minimum:

- `available`: tersedia.
- `reserved`: dipesan untuk pengajuan yang disetujui.
- `borrowed`: sedang dipinjam.
- `damaged`: rusak dan tidak tersedia untuk dipinjam.
- `maintenance`: sedang diperbaiki/diperiksa.
- `lost`: hilang.
- `inactive`: dinonaktifkan.

### 7.4 Status anggota untuk peminjaman

Status keanggotaan dan status kelayakan meminjam disimpan terpisah:

- `membership_status`: `active` atau `inactive`.
- `borrowing_status`: `eligible` atau `borrowing_blocked`.

Anggota hanya boleh mengajukan jika `membership_status = active` dan `borrowing_status = eligible`.

### 7.5 Catatan implementasi status

- Status pengajuan dan status transaksi adalah dua hal berbeda.
- Approval tidak otomatis berarti barang sudah dipinjam; status `borrowed` hanya berlaku setelah checkout QR Code dan verifikasi Admin/Korlab berhasil.
- Status overdue dapat dihitung dari tenggat dan status transaksi, tetapi penanda blokir anggota harus diperbarui secara konsisten oleh scheduler serta diperiksa ulang saat pengajuan dibuat.
- Barang tidak boleh kembali ke `available` hanya karena User mengajukan pengembalian. Admin/Korlab harus menyelesaikan pemeriksaan terlebih dahulu.
- Perubahan status hanya dapat dilakukan melalui aksi domain/backend yang sah.

---

## 8. Aturan Bisnis Inti

1. Hanya anggota aktif yang dapat mengajukan peminjaman.
2. Anggota yang memiliki transaksi overdue otomatis diblokir dari pengajuan baru.
3. Pemblokiran berlaku pada identitas anggota/NRP, bukan hanya satu akun.
4. Anggota yang diblokir masih dapat login, melihat histori, dan mengajukan pengembalian.
5. Kategori Terjadwal wajib memenuhi aturan pengajuan H-2.
6. Kategori Mendadak mendapat pengecualian H-2 dan prioritas pemeriksaan, tetapi tetap wajib mendapat approval.
7. Pengajuan wajib menyertakan data barang, jumlah/unit, waktu pengambilan, tenggat pengembalian, lokasi, dan keterangan yang diwajibkan.
8. User wajib membaca dan mengonfirmasi SOP sebelum mengirim pengajuan.
9. Admin/Korlab dan WM sama-sama berwenang menyetujui atau menolak pengajuan.
10. Untuk MVP, satu keputusan valid dari salah satu approver sudah cukup; keputusan pertama mengunci pengajuan.
11. Tidak ada self-approval, termasuk jika approver memakai akun administratif dan akun User yang berbeda tetapi terhubung dengan identitas anggota yang sama.
12. Barang yang telah disetujui menjadi reserved dan tidak boleh dialokasikan ganda.
13. Barang tidak boleh keluar tanpa scan/identifikasi QR Code, verifikasi Admin/Korlab, pencatatan kondisi awal, dan konfirmasi serah terima.
14. QR Code tidak dapat menggantikan approval atau otorisasi Admin/Korlab.
15. Barang yang kembali wajib diverifikasi Admin/Korlab.
16. Barang rusak, hilang, atau tidak lengkap tidak otomatis menjadi tersedia.
17. Blokir overdue hanya dilepas otomatis setelah seluruh transaksi overdue anggota selesai diverifikasi.
18. Pengguna tidak boleh menghapus permanen transaksi, approval, inspeksi, atau audit log melalui antarmuka aplikasi.
19. Perubahan status anggota menjadi inactive tidak menghapus histori dan kewajiban peminjaman.
20. Seluruh aturan dan otorisasi ditegakkan di backend.

### 8.1 Kebijakan yang perlu dikonfigurasi atau disahkan pengurus

- Definisi H-2: dua hari kalender atau dua hari kerja.
- Batas jam pengajuan untuk kategori Terjadwal dan Mendadak.
- Batas maksimal durasi peminjaman per kategori barang.
- Apakah pengajuan Mendadak memerlukan bukti atau alasan khusus.
- Batas jumlah barang yang dapat dipinjam dalam satu pengajuan.
- Ketentuan pembatalan setelah approval.
- Kapan reservasi dianggap kedaluwarsa jika barang tidak diambil.
- Checklist kondisi per kategori barang.
- Penanganan barang rusak, hilang, atau tidak lengkap.
- Apakah insiden selain overdue juga dapat memblokir peminjaman.
- Retensi audit log dan bukti foto.
- Apakah approval satu approver tetap berlaku atau kelak perlu diubah menjadi approval berurutan.

---

## 9. Kebutuhan Data dan Rancangan Entitas

Rancangan berikut merupakan logical data model awal. Nama tabel dan kolom dapat disesuaikan saat membuat ERD dan migration.

| Entitas/Tabel | Fungsi dan data utama |
|---|---|
| `users` | Akun login, password hash, status akun, dan relasi ke anggota. |
| `members` | Kode anggota, nama, NRP unik, divisi, generasi, jabatan, keterangan, status aktif/nonaktif, borrowing status. |
| `roles` | Role User, Admin/Korlab, dan Super Admin/WM. |
| `user_roles` | Relasi akun dengan role. Memungkinkan akun administratif terpisah dari akun User untuk identitas anggota yang sama. |
| `divisions` | Daftar divisi organisasi. |
| `categories` | Kategori inventaris. |
| `items` | Master barang: kode, nama, kategori, metode tracking, jumlah, harga/nilai, tanggal input, status, lokasi, keterangan. |
| `assets` | Unit barang individual untuk inventaris serialized, termasuk asset code, serial number, QR identifier, kondisi, dan status. |
| `locations` | Lokasi penyimpanan/loker barang. |
| `borrow_requests` | Pengajuan, peminjam, kategori Terjadwal/Mendadak, waktu, lokasi, tujuan, status, dan timestamp. |
| `borrow_request_items` | Daftar barang, jumlah, dan/atau unit yang diminta dalam pengajuan. |
| `approvals` | Identitas approver, role, keputusan, catatan, dan waktu. Untuk MVP satu keputusan final per pengajuan. |
| `reservations` | Alokasi barang atau unit untuk periode yang telah disetujui. |
| `loan_transactions` | Transaksi pinjaman aktual, status, waktu checkout, tenggat, dan waktu selesai. |
| `loan_items` | Barang/unit dan jumlah aktual yang diserahkan dalam transaksi. |
| `handover_records` | Petugas penyerah, peminjam, waktu, hasil QR scan, checklist, dan konfirmasi penerimaan. |
| `return_requests` | Permintaan pengembalian dari User dan status verifikasi. |
| `return_inspections` | Petugas penerima, waktu, kondisi, kelengkapan, catatan, dan bukti foto. |
| `damage_reports` | Kerusakan, kehilangan, kekurangan, kronologi, dan tindak lanjut. |
| `announcements` | Judul, isi, status publikasi, pembuat, dan waktu publikasi. |
| `sop_documents` | Versi dan isi SOP peminjaman. |
| `sop_acknowledgements` | Anggota, pengajuan, versi SOP yang disetujui, dan waktu persetujuan. |
| `notifications` | Notifikasi internal kepada User, Admin/Korlab, atau WM. |
| `audit_logs` | Pelaku, tindakan, objek, perubahan relevan, waktu, dan metadata. |

### 9.1 Relasi utama

- Satu `member` dapat memiliki satu atau lebih akun `users` jika akun User dan akun administratif dipisahkan sesuai kebijakan tim.
- Satu anggota memiliki satu NRP unik.
- Satu `borrow_request` memiliki banyak `borrow_request_items`.
- Satu pengajuan memiliki satu keputusan final pada workflow MVP, dicatat di `approvals`.
- Pengajuan yang disetujui membuat satu atau lebih `reservations`.
- Checkout menghasilkan `loan_transactions` dan `loan_items`.
- Transaksi dapat memiliki `handover_records`, `return_requests`, `return_inspections`, dan `damage_reports`.
- QR Code mengacu pada `assets` atau `items` sesuai jenis tracking.
- Perubahan anggota, inventaris, pengajuan, approval, transaksi, inspeksi, dan blokir menghasilkan `audit_logs`.

### 9.2 Prinsip integritas data

- NRP, kode anggota, kode inventaris, asset code, dan nomor referensi pengajuan harus unik sesuai cakupannya.
- Password disimpan dalam bentuk hash, bukan plaintext.
- Approval final dan reservasi dilakukan secara atomik.
- Checkout, pemindaian QR yang tervalidasi, dan perubahan status transaksi dilakukan dalam database transaction.
- Pengembalian dan pembaruan stok dilakukan secara atomik.
- Gunakan locking atau mekanisme database yang sesuai untuk mencegah double booking dan double checkout.
- Penghitungan stok harus konsisten antara barang tersedia, reserved, borrowed, damaged, dan inactive.
- Audit log tidak dapat diedit oleh User biasa.
- Data anggota dan inventaris yang dinonaktifkan tidak dihapus permanen jika masih direferensikan transaksi historis.

---

## 10. Halaman dan Navigasi Produk

### 10.1 Area User / Pengaju

1. **Dashboard** — ringkasan inventaris, status pinjaman, dan pengumuman.
2. **Inventaris** — katalog, pencarian, filter, detail, jumlah, status, dan lokasi.
3. **Form Peminjaman** — keranjang barang, SOP, kategori Terjadwal/Mendadak, dan formulir pengajuan.
4. **Peminjaman Saya** — daftar pengajuan/transaksi aktif dan status terkini.
5. **Riwayat Peminjaman** — histori transaksi pribadi.
6. **Pengumuman** — daftar dan detail pengumuman.
7. **Pengaturan Akun** — pengaturan akun yang diperbolehkan.
8. **Logout** — mengakhiri sesi.

### 10.2 Area Admin / Korlab

1. **Dashboard** — pengajuan menunggu approval, pinjaman aktif, pengembalian menunggu verifikasi, dan ringkasan inventaris.
2. **Manajemen Peminjaman** — antrean approval dan detail pengajuan.
3. **Verifikasi Pengembalian** — daftar return yang perlu diperiksa.
4. **QR Checkout / Serah Terima** — pemindaian QR, pemeriksaan barang, dan pencatatan checkout.
5. **Riwayat Peminjaman** — pencarian seluruh transaksi sesuai kewenangan.
6. **Manajemen Inventaris** — tambah, edit, detail, dan nonaktifkan barang.
7. **Pengumuman** — tambah, edit, detail, dan status publikasi.
8. **Pengaturan Akun** — pengaturan sesuai hak akses.
9. **Logout** — mengakhiri sesi.

### 10.3 Area Super Admin / WM

1. **Dashboard** — ringkasan anggota, inventaris, dan peminjaman.
2. **Approval Peminjaman** — antrean dan keputusan approval/penolakan.
3. **Manajemen Anggota** — tambah, cari, edit, detail, dan nonaktifkan anggota.
4. **Informasi Peminjaman** — melihat seluruh detail dan keputusan transaksi.
5. **Anggota Terblokir** — melihat anggota dengan status overdue dan penyebabnya.
6. **Aktivitas Administratif** — melihat audit trail perubahan oleh Admin maupun WM.
7. **Pengaturan Akun** — pengaturan sesuai hak akses.
8. **Logout** — mengakhiri sesi.

WM dapat memberikan approval, tetapi tidak melakukan proses checkout QR Code atau verifikasi pengembalian dalam workflow operasional default.

---

## 11. Kebutuhan Non-Fungsional

### 11.1 Security

- Gunakan password hashing yang sesuai dengan framework.
- Terapkan HTTPS pada seluruh akses website.
- Terapkan authorization backend pada setiap endpoint dan aksi sensitif.
- Terapkan CSRF protection, validasi input, output escaping, rate limiting login, dan proteksi session.
- Batasi akses berdasarkan role dan kepemilikan data.
- Cegah self-approval lintas akun yang terhubung ke anggota/NRP yang sama.
- QR Code tidak mengandung secret atau data pribadi sensitif dan tidak dapat melakukan checkout tanpa autentikasi Admin/Korlab.
- Validasi upload file, tipe MIME, ukuran, dan nama file.
- Jangan menyimpan password atau secret di audit log.
- Audit log dan histori transaksi harus terlindungi dari perubahan tidak sah.

### 11.2 Reliability dan data safety

- Approval, reservasi, checkout, return, perubahan stok, dan aktivasi blokir harus atomik.
- Scheduler overdue harus idempotent dan tidak menghasilkan efek duplikat.
- Sistem memeriksa overdue saat pengajuan dibuat, meskipun scheduler belum berjalan.
- Lakukan backup database secara berkala dan uji pemulihan.
- Kegagalan notifikasi tidak boleh membatalkan transaksi inti yang sudah berhasil disimpan.

### 11.3 Performance

- Halaman daftar menggunakan pagination.
- Pencarian dan filter harus menggunakan query database yang efisien.
- Dashboard memuat ringkasan agregat dan tidak mengambil seluruh histori sekaligus.
- Operasi QR scan harus memberikan hasil validasi yang cepat pada jaringan normal.

### 11.4 Usability dan accessibility

- Antarmuka responsif untuk desktop dan ponsel.
- Status menggunakan label yang mudah dipahami dalam Bahasa Indonesia.
- Formulir menampilkan validasi dan alasan kesalahan dengan jelas.
- Alur peminjaman menampilkan langkah yang sedang berlangsung.
- Halaman checkout dan return dibuat ringkas untuk penggunaan operasional.
- Tombol tindakan penting seperti Setujui, Tolak, Checkout, dan Verifikasi Pengembalian harus memiliki konfirmasi yang sesuai.

### 11.5 Maintainability

- Kode diorganisasi per domain/modul: Authentication, Members, Inventory, Borrowing, Approval, Handover, Return, Notifications, dan Reporting.
- Business rule tidak diletakkan hanya di controller atau frontend.
- Migration, seeder, dan automated test disediakan.
- Dokumentasi konfigurasi, deployment, dan backup diperbarui.

---

## 12. Rekomendasi Arsitektur dan Teknologi

### 12.1 Arsitektur

Rekomendasi awal adalah **Modular Monolith**. Sistem belum membutuhkan microservices karena domain dan skala operasional awal masih dapat dikelola dalam satu aplikasi backend.

Komponen utama:

- Responsive web frontend untuk User, Admin/Korlab, dan WM.
- Backend untuk autentikasi, RBAC, validasi, approval, inventory, dan transaksi.
- Relational database sebagai sumber data utama.
- Queue worker untuk notifikasi dan pekerjaan asynchronous.
- Scheduler untuk pemeriksaan tenggat, overdue, dan pemblokiran otomatis.
- Storage privat untuk foto aset dan bukti inspeksi.
- Modul QR Code untuk pembuatan, pencetakan, dan pemindaian label.

### 12.2 Stack yang direkomendasikan

| Lapisan | Teknologi rekomendasi | Catatan |
|---|---|---|
| Backend | Laravel versi stabil yang didukung saat implementasi | Authentication, policies, validation, queue, scheduler, ORM, dan business logic. |
| Frontend | Vue 3 + Inertia.js | Dashboard dan form interaktif dalam satu aplikasi. |
| UI | Tailwind CSS | Styling responsif dan komponen antarmuka. |
| Database | PostgreSQL | Constraint, transaksi, locking, dan query relasional. |
| Queue/cache | Redis (opsional pada tahap awal) | Queue worker, cache, dan kebutuhan sementara. |
| QR Code | Library QR Code yang kompatibel dengan backend/frontend | QR identifier dan proses scan melalui kamera perangkat yang didukung. |
| File storage | Private local storage pada awalnya atau S3-compatible object storage | Foto dan dokumen tidak disimpan sebagai blob di tabel utama. |
| Deployment | Docker + reverse proxy HTTPS | Konsistensi environment dan deployment. |

### 12.3 Batas arsitektur

- PostgreSQL menjadi sumber kebenaran untuk anggota, status akun, inventaris, stok, approval, reservasi, transaksi, dan blokir.
- Frontend tidak boleh menjadi sumber otoritas untuk menentukan akses atau ketersediaan.
- Semua operasi penting diproses melalui service/action domain yang sama.
- Scheduler digunakan untuk menandai overdue dan memblokir anggota, tetapi validasi overdue juga dilakukan ketika User mengirim pengajuan.
- Pemindaian QR Code hanya memulai proses identifikasi; keputusan checkout tetap harus melalui validasi backend dan Admin/Korlab.

---

## 13. Acceptance Criteria MVP

MVP dianggap memenuhi kebutuhan dasar jika seluruh kriteria P0 berikut lulus pengujian.

### 13.1 Akun dan akses

- [ ] User dapat login menggunakan NRP dan password.
- [ ] NRP anggota bersifat unik.
- [ ] Profil NRP, nama, dan divisi terisi otomatis pada formulir peminjaman.
- [ ] Anggota nonaktif tidak dapat membuat pengajuan baru.
- [ ] Admin/Korlab dan WM dapat masuk ke area sesuai role.
- [ ] Admin/Korlab dan WM dapat menggunakan akun User terpisah untuk peminjaman pribadi.
- [ ] Sistem menolak self-approval lintas akun yang memiliki identitas anggota/NRP sama.

### 13.2 Inventaris

- [ ] Admin/Korlab dapat menambah, mengubah, melihat, dan menonaktifkan inventaris.
- [ ] Kode inventaris unik.
- [ ] Katalog dapat dicari berdasarkan nama, kode, kategori, dan status.
- [ ] Detail barang menampilkan jumlah, status, dan lokasi loker.
- [ ] Barang nonaktif, rusak, hilang, atau dalam maintenance tidak dihitung tersedia.
- [ ] QR Code dapat dibuat dan dicetak untuk barang/unit yang dikelola.

### 13.3 Pengajuan dan approval

- [ ] Hanya anggota aktif yang tidak diblokir dapat mengirim pengajuan.
- [ ] User dapat memilih beberapa barang dan mengisi jumlah/unit.
- [ ] Pengajuan Terjadwal divalidasi terhadap aturan H-2.
- [ ] Pengajuan Mendadak mendapat pengecualian H-2 dan penanda prioritas.
- [ ] Kategori Mendadak tetap memerlukan approval.
- [ ] User wajib menyetujui SOP sebelum mengirim pengajuan.
- [ ] Admin/Korlab dan WM sama-sama dapat menyetujui atau menolak pengajuan.
- [ ] Satu keputusan dari salah satu approver cukup untuk MVP.
- [ ] Keputusan pertama mengunci pengajuan dan tidak dapat ditimpa approver lain.
- [ ] Penolakan wajib memiliki alasan.
- [ ] Approval dan reservasi dilakukan secara atomik.
- [ ] Dua pengajuan bersamaan tidak dapat mereservasi unit yang sama pada waktu bertabrakan.

### 13.4 Serah terima QR Code

- [ ] Barang tidak dapat checkout tanpa approval.
- [ ] Admin/Korlab dapat memindai QR Code barang.
- [ ] Sistem menolak QR yang tidak cocok dengan pengajuan atau barang yang tidak tersedia.
- [ ] Checkout mencatat unit/jumlah aktual, kondisi awal, waktu, petugas, dan peminjam.
- [ ] User mengonfirmasi penerimaan.
- [ ] Status berubah menjadi Sedang Dipinjam hanya setelah proses serah terima berhasil.

### 13.5 Pengembalian dan overdue

- [ ] User dapat mengajukan pengembalian dari transaksi aktif.
- [ ] Status berubah menjadi Menunggu Verifikasi Pengembalian.
- [ ] Admin/Korlab dapat memeriksa kondisi dan kelengkapan barang.
- [ ] Barang yang baik dan lengkap dapat diselesaikan dan tersedia kembali.
- [ ] Barang rusak, hilang, atau tidak lengkap tidak otomatis tersedia.
- [ ] Sistem mendeteksi transaksi yang melewati tenggat.
- [ ] Anggota overdue otomatis diblokir dari pengajuan baru.
- [ ] Blokir berlaku untuk identitas anggota meskipun anggota menggunakan akun berbeda.
- [ ] Blokir dilepas otomatis setelah seluruh transaksi overdue selesai diverifikasi.

### 13.6 WM, pengumuman, dan audit

- [ ] WM dapat menambah, mencari, mengubah, dan menonaktifkan data anggota.
- [ ] WM dapat melihat informasi peminjaman dan keputusan approval.
- [ ] WM dapat melihat aktivitas administratif.
- [ ] Admin/Korlab dapat mengelola pengumuman.
- [ ] User dapat melihat pengumuman yang dipublikasikan.
- [ ] Approval, QR checkout, return, perubahan anggota, perubahan inventaris, dan blokir menghasilkan audit log.
- [ ] User biasa tidak dapat mengubah atau menghapus audit log.

---

## 14. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Data inventaris awal tidak akurat | Ketersediaan yang tampil tidak sesuai kondisi fisik | Stock opname, verifikasi kode, jumlah, kondisi, dan lokasi sebelum go-live. |
| Pengajuan Mendadak disalahgunakan | Pengecualian H-2 menjadi jalur bypass | Wajibkan alasan, beri penanda prioritas, dan tetap wajib approval. |
| Approver tidak tersedia | Pengajuan menumpuk | Kedua role berwenang dapat memproses; keputusan dan identitas approver dicatat. |
| Dua approver mengambil keputusan bersamaan | Status approval tidak konsisten | Database transaction, row locking, dan aturan first valid decision wins. |
| QR Code salah atau disalin | Barang yang salah dapat diserahkan | Validasi server-side terhadap unit, pengajuan, status, dan petugas yang login. |
| Barang keluar tanpa dicatat | Histori dan stok tidak akurat | SOP operasional dan larangan checkout tanpa QR serta verifikasi Admin/Korlab. |
| Anggota terlambat mengembalikan | Aset tidak tersedia | Overdue detection, blokir otomatis, dan dashboard tanggungan. |
| Scheduler terlambat berjalan | Blokir tidak langsung muncul | Periksa tenggat juga pada saat pembuatan pengajuan dan akses transaksi. |
| Admin menyalahgunakan hak akses | Data dapat dimanipulasi | Least privilege, audit log, larangan self-approval, dan review berkala. |
| Anggota dinonaktifkan saat masih meminjam | Tanggungan tidak terlacak | Pertahankan relasi transaksi dan tanggung jawab meskipun akun nonaktif. |

---

## 15. Rencana Implementasi

### Milestone 0 — Validasi kebutuhan dan persiapan data

- Konfirmasi struktur anggota, divisi, generasi, dan jabatan.
- Tetapkan daftar Admin/Korlab dan WM yang berwenang.
- Sepakati definisi H-2 dan jam operasional pengambilan barang.
- Inventarisasi barang dan lakukan stock opname.
- Tentukan kategori, kode, tipe tracking, lokasi loker, dan kondisi awal.
- Finalisasi SOP peminjaman dan pengembalian.
- Tentukan checklist pemeriksaan per kategori barang.

### Milestone 1 — Fondasi aplikasi

- Setup repository, environment, database, dan deployment awal.
- Implementasi authentication NRP/password.
- Implementasi anggota, role, dan divisi.
- Implementasi dashboard dasar tiap role.
- Implementasi kategori, lokasi, master barang, unit aset, dan stok.
- Implementasi QR Code generation dan label.

### Milestone 2 — Core borrowing workflow

- Implementasi katalog dan form peminjaman.
- Implementasi kategori Terjadwal/Mendadak dan validasi H-2.
- Implementasi SOP acknowledgement.
- Implementasi antrean approval Admin/Korlab dan WM.
- Implementasi validasi availability dan reservasi.
- Implementasi scan QR dan checkout dengan verifikasi Admin/Korlab.
- Implementasi return request dan return inspection.

### Milestone 3 — Kontrol dan operasional

- Implementasi overdue detection dan automatic borrowing block.
- Implementasi notifikasi internal.
- Implementasi damage/loss report.
- Implementasi pengumuman.
- Implementasi dashboard, riwayat, laporan dasar, dan audit log.
- Pengujian end-to-end, otorisasi, transaksi bersamaan, dan pemulihan data.

### Milestone 4 — Pilot dan go-live

- Uji coba pada kelompok anggota terbatas.
- Cocokkan data sistem dengan stok fisik.
- Latih User, Admin/Korlab, dan WM.
- Uji skenario peminjaman normal, mendadak, QR tidak cocok, overdue, blokir, dan return bermasalah.
- Perbaiki temuan pilot.
- Terapkan SOP bahwa peminjaman resmi wajib melalui ENT Inventory.

---

## 16. Keputusan Produk dan Hal yang Masih Perlu Dikonfirmasi

### 16.1 Keputusan yang sudah ditetapkan tim

| Topik | Keputusan |
|---|---|
| Pengguna yang boleh meminjam | Hanya anggota aktif. |
| Identitas login User | NRP dan password. |
| Keterlambatan | Anggota yang terlambat otomatis diblokir dari peminjaman baru. |
| Serah terima | QR Code dan verifikasi Admin/Korlab. |
| Pemberi approval | Admin/Korlab dan WM. |
| Akun peminjaman pribadi pengurus | Admin/Korlab dan WM menggunakan akun User terpisah untuk meminjam. |
| Kategori peminjaman | Terjadwal dan Mendadak. |
| Syarat kategori Terjadwal | Memenuhi ketentuan H-2. |
| Kategori Mendadak | Dikecualikan dari H-2 dan diprioritaskan untuk pemeriksaan; approval tetap wajib. |
| SOP | User harus membaca dan mengonfirmasi SOP sebelum mengirim pengajuan. |
| Verifikasi pengembalian | Dilakukan Admin/Korlab. |
| Pengelolaan anggota | Dilakukan Super Admin/WM. |

### 16.2 Asumsi workflow yang dipakai untuk MVP

- Admin/Korlab dan WM adalah dua role yang sama-sama boleh mengambil keputusan approval.
- Satu approval dari salah satu role sudah cukup; tidak ada dua approval berurutan.
- Keputusan yang lebih dahulu berhasil disimpan menjadi keputusan final.
- Admin/Korlab menangani verifikasi fisik serah terima dan pengembalian.
- WM menangani manajemen anggota, monitoring, aktivitas administratif, dan dapat melakukan approval.

Asumsi tersebut dibuat agar keputusan terbaru dapat langsung diterjemahkan menjadi workflow implementasi tanpa menambahkan tahap approval yang belum diminta tim.

### 16.3 Hal yang perlu ditetapkan sebelum go-live

| Pertanyaan | Status |
|---|---|
| H-2 dihitung sebagai dua hari kalender atau dua hari kerja? | Belum ditetapkan. |
| Jam batas pengajuan dan jam layanan pengambilan barang? | Belum ditetapkan. |
| Maksimal durasi peminjaman per kategori? | Belum ditetapkan. |
| Batas jumlah barang per pengajuan? | Belum ditetapkan. |
| Apakah kategori Mendadak memerlukan bukti pendukung? | Belum ditetapkan. |
| Berapa lama reservasi Disetujui boleh menunggu pengambilan sebelum kedaluwarsa? | Belum ditetapkan. |
| Apakah pembukaan blokir manual diperbolehkan dalam kondisi khusus? | Belum ditetapkan; default MVP adalah blokir otomatis dilepas setelah overdue selesai diverifikasi. |
| Bagaimana penanganan kerusakan, kehilangan, dan barang tidak lengkap? | Perlu SOP organisasi. |
| Apakah WM juga perlu hak checkout/return di masa mendatang? | Tidak termasuk workflow default saat ini. |
| Apakah diperlukan ekspor PDF/Excel pada MVP? | Direkomendasikan setelah MVP stabil. |

---

## 17. Definition of Done

Sebuah fitur dianggap selesai jika:

1. Kebutuhan fungsional dan acceptance criteria terkait telah dipenuhi.
2. Validasi serta otorisasi backend telah diterapkan.
3. Alur sukses, gagal, dan edge case utama telah diuji.
4. Perubahan data penting menghasilkan histori/audit log.
5. UI responsif dan menampilkan pesan kesalahan yang dapat dipahami.
6. Tidak ada secret atau data sensitif yang masuk ke repository.
7. Dokumentasi penggunaan dan konfigurasi yang relevan telah diperbarui.
8. Migration dapat dijalankan pada lingkungan baru.
9. Backup dan restore database dasar telah diuji.
10. Pengujian mencakup akun nonaktif, akun overdue, self-approval, approval bersamaan, QR tidak cocok, dan pengembalian dengan kondisi bermasalah.

---

## 18. Ringkasan Keputusan Produk

ENT Inventory dibangun sebagai aplikasi web inventaris UKM Jurnalistik dengan tiga role utama: User/Pengaju, Admin/Korlab, dan Super Admin/WM.

MVP berfokus pada alur berikut:

`Login NRP → Katalog Inventaris → Pilih Barang → Konfirmasi SOP → Form Pengajuan → Validasi H-2 / Mendadak → Approval Admin/Korlab atau WM → Reservasi → Scan QR + Verifikasi Admin/Korlab → Sedang Dipinjam → Pengajuan Pengembalian → Verifikasi Admin/Korlab → Selesai`

Jika peminjam melewati tenggat pengembalian, sistem menandai transaksi sebagai overdue dan otomatis memblokir identitas anggota tersebut dari pengajuan baru sampai seluruh transaksi overdue diselesaikan dan diverifikasi.

Prinsip yang tidak boleh dikompromikan:

- Hanya anggota aktif yang dapat meminjam.
- Tidak ada barang keluar tanpa approval dan verifikasi Admin/Korlab.
- Admin/Korlab dan WM sama-sama berwenang melakukan approval.
- Tidak ada self-approval, termasuk lintas akun.
- QR Code mengidentifikasi barang, bukan menggantikan otorisasi.
- Tidak ada double booking.
- Pengembalian harus diperiksa sebelum transaksi selesai.
- Anggota overdue otomatis diblokir dari peminjaman baru.
- Barang rusak atau hilang tidak otomatis tersedia kembali.
- Riwayat transaksi dan aktivitas penting tidak dihapus.
- Semua aturan dan hak akses ditegakkan di backend.

**Prioritas pengembangan:** selesaikan identitas anggota dan role, inventaris, workflow approval, QR handover, return inspection, serta automatic overdue restriction terlebih dahulu. Fitur ekspor lanjutan, notifikasi eksternal, analitik, dan maintenance dapat dikembangkan setelah alur inti berjalan stabil.
