# AUTH-12 — Manajemen Sesi & Pencabutan Akses

**Status:** disetujui (short design — perubahan kecil)

## Intent

PRD `AUTH-12`: "Sistem menyediakan pencabutan sesi dan pengelolaan akses akun."

Saat ini satu-satunya pencabutan sesi terjadi saat WM menonaktifkan akun (`AccountController::updateStatus`). Tiga celah nyata:

1. Anggota tidak bisa melihat perangkat mana yang masih login, dan tidak bisa mengeluarkan perangkat yang bukan miliknya.
2. WM mereset password akun, tetapi sesi lama tetap valid — password baru tidak memaksa keluar.
3. Anggota mengganti password sendiri, sesi lama tetap hidup.

## Non-goals

- Delegasi approver (`APR-13`) — sprint lain.
- 2FA, perangkat tepercaya, atau riwayat login persisten di luar tabel `sessions`.
- Pencabutan sesi oleh WM per sesi (WM cukup "cabut semua" lewat reset password/nonaktif).

## Keputusan

- **Tanpa migration.** Tabel `sessions` sudah ada (`id, user_id, ip_address, user_agent, last_activity`). Sumber kebenaran = tabel itu, bukan tabel baru.
- **Satu service** `App\Services\SessionService` dipakai dua tempat (halaman keamanan user + reset password WM). Tiga operasi: daftar, cabut satu, cabut semua (kecuali sesi berjalan).
- **Label perangkat** diturunkan dari `user_agent` dengan pencocokan sederhana (Chrome/Firefox/Safari/Edge/Opera + Android/iOS/Windows/macOS/Linux), tanpa dependency baru. UA mentah tidak ditampilkan (panjang dan tidak berguna bagi anggota).
- **Sesi berjalan tidak bisa dicabut dari daftar** — untuk itu ada tombol Keluar. Mencabutnya dari daftar akan memutus request yang sedang berjalan dan membingungkan.
- **Ganti password sendiri** → cabut semua sesi lain, sesi berjalan tetap. **Reset password oleh WM** → cabut semua sesi akun itu.
- **Audit**: `session.revoked`, `session.revoked_others`, `account.password_changed`, dan metadata `sessions_revoked` pada `account.password_reset` yang sudah ada.

## Yang dibangun

- `GET /settings/security` menampilkan prop `sessions` (perangkat, IP, terakhir aktif, penanda sesi ini).
- `DELETE /settings/sessions/{session}` — cabut satu sesi milik sendiri.
- `DELETE /settings/sessions` — cabut semua sesi lain.
- Section "Perangkat & sesi" di `resources/js/pages/settings/Security.vue`.

## Kriteria terima

- Anggota melihat hanya sesinya sendiri; mencabut sesi milik orang lain → 404.
- Setelah "cabut semua sesi lain", request berikutnya dari sesi lain ditolak (baris sesi hilang).
- Ganti password sendiri mempertahankan sesi berjalan dan menghapus yang lain.
- Reset password oleh WM menghapus seluruh sesi akun tersebut.
- Setiap pencabutan meninggalkan satu baris audit.
