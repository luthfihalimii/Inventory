# ENT Inventory UI/UX Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti seluruh lapisan visual ENT Inventory menjadi sistem biru-putih yang konsisten, responsif, dan aksesibel, dengan login split-screen dan dashboard berbasis data nyata.

**Architecture:** Token-first. Token Tailwind, font, shell, komponen bersama, dan layout auth diganti lebih dulu. Halaman inti memakai komponen bersama. Tabel admin dan WM memakai `TableShell` tanpa membongkar struktur data. Chart dashboard memakai SVG/CSS tanpa dependency baru dan data agregat dari controller.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3.5, Tailwind 4, Vite Plus, PHPUnit 12, Playwright.

**Spec:** `docs/superpowers/specs/2026-11-15-ui-overhaul-design.md`

## Global Constraints

- Font: Plus Jakarta Sans, bobot 400, 500, 600, 700, 800.
- Dial: ENERGY 2 / RHYTHM 2 / MOTION 1.
- Palet light: background `#F3F6FC`, foreground `#0F172A`, card `#FFFFFF`, primary `#2563EB`, primary-soft `#EAF1FF`, muted `#5B6B82`, border `#E2E8F0`, input `#7C8BA1`, ring `#2563EB`.
- Palet dark: background `#0B1120`, card `#111827`, foreground `#F8FAFC`, primary `#3B82F6`, primary foreground `#0B1120`, muted `#94A3B8`, border `#263244`, input `#64748B`, ring `#93C5FD`.
- Radius: kartu 16px, kontrol 12px, chip status 999px, modal 16px.
- Tidak menambah dependency npm, Composer, atau library chart.
- Semua angka dashboard berasal dari database. Tidak ada delta atau angka karangan.
- Label dan kontrak e2e dipertahankan: "Kirim pengajuan", "Setujui", "Serahkan", "Periksa", "Ajukan pengembalian", "Selesaikan transaksi", "Cari", "Tambah ke keranjang", teks status, ID `#category`, `#usage_location`, `#pickup_at`, `#return_at`, h1 nomor referensi di halaman detail, dan struktur `tbody tr`.
- Tidak ada em dash di teks yang ditulis agen.
- Target sentuh minimal 44px, fokus terlihat, kontras AA, `prefers-reduced-motion`.
- QR selalu light untuk cetak.
- Commit tidak termasuk dalam plan ini karena pengguna belum meminta commit. Setiap task berakhir dengan checkpoint verifikasi.

## Review Focus

1. **Overflow horizontal di 320px.** Tabel, filter, dan chart bisa melebihi viewport. Task 9 menambahkan Playwright yang memeriksa `document.documentElement.scrollWidth <= window.innerWidth` di dashboard, inventaris, peminjaman, dan satu halaman admin pada 320px dan 375px.
2. **Kontrak e2e rusak.** ID form, h1 referensi, jumlah checkbox SOP, dan `tbody tr` dipakai suite. Task 6 dan 7 menjalankan `bun run e2e` dan mempertahankan semua kontrak.
3. **Kontras dark mode.** Token chart, badge, dan input bisa gagal di dark. Task 1 dan Task 9 menjalankan `contrast-check.py` untuk semua pasangan token dan badge.
4. **Agregat dashboard salah.** Bulan kosong, scope anggota, dan timezone bisa menghasilkan angka salah. Task 5 menambahkan feature test dengan request bulan ini, bulan lalu, dan request milik anggota lain.
5. **Sidebar mobile tidak bisa ditutup atau menutupi konten.** Sheet harus bisa dibuka, ditutup, dan ditutup dengan Escape. Task 3 dan Task 9 menguji di viewport mobile.

---

### Task 1: Design Tokens, Font, and Base Layer

**Files:**
- Modify: `vite.config.ts`
- Modify: `resources/css/app.css`
- Modify: `resources/views/app.blade.php`
- Modify: `resources/js/app.ts`
- Modify: `public/favicon.svg`
- Modify: `resources/js/types/ui.ts`

**Interfaces:**
- Consumes: tidak ada.
- Produces: token `--primary-soft`, `--color-primary-soft`, `StatusTone`.

- [ ] **Step 1: Ganti font di `vite.config.ts`**

Ganti blok `bunny('Instrument Sans', { weights: [400, 500, 600] })` menjadi:

```ts
bunny('Plus Jakarta Sans', {
    weights: [400, 500, 600, 700, 800],
}),
```

- [ ] **Step 2: Tulis ulang token di `resources/css/app.css`**

Pertahankan `@import 'tailwindcss'`, `@import 'tw-animate-css'`, `@source`, dan `@custom-variant dark`. Ubah:

- `--font-sans` di `@theme inline` menjadi `'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif, ...`.
- Hapus override `--font-sans` di `@layer utilities`.
- Tambahkan `--color-primary-soft: var(--primary-soft);` di `@theme inline`.
- Ganti seluruh blok `:root` dan `.dark` dengan nilai berikut.

```css
:root {
    --background: #f3f6fc;
    --foreground: #0f172a;
    --card: #ffffff;
    --card-foreground: #0f172a;
    --popover: #ffffff;
    --popover-foreground: #0f172a;
    --primary: #2563eb;
    --primary-foreground: #ffffff;
    --primary-soft: #eaf1ff;
    --secondary: #f1f5f9;
    --secondary-foreground: #0f172a;
    --muted: #f1f5f9;
    --muted-foreground: #5b6b82;
    --accent: #eaf1ff;
    --accent-foreground: #1e40af;
    --destructive: #dc2626;
    --destructive-foreground: #ffffff;
    --border: #e2e8f0;
    --input: #7c8ba1;
    --ring: #2563eb;
    --chart-1: #2563eb;
    --chart-2: #93c5fd;
    --chart-3: #166534;
    --chart-4: #92400e;
    --chart-5: #991b1b;
    --radius: 0.75rem;
    --sidebar: #ffffff;
    --sidebar-background: #ffffff;
    --sidebar-foreground: #0f172a;
    --sidebar-primary: #2563eb;
    --sidebar-primary-foreground: #ffffff;
    --sidebar-accent: #eaf1ff;
    --sidebar-accent-foreground: #1e40af;
    --sidebar-border: #e2e8f0;
    --sidebar-ring: #2563eb;
}

.dark {
    --background: #0b1120;
    --foreground: #f8fafc;
    --card: #111827;
    --card-foreground: #f8fafc;
    --popover: #111827;
    --popover-foreground: #f8fafc;
    --primary: #3b82f6;
    --primary-foreground: #0b1120;
    --primary-soft: #172554;
    --secondary: #1e293b;
    --secondary-foreground: #f8fafc;
    --muted: #1e293b;
    --muted-foreground: #94a3b8;
    --accent: #172554;
    --accent-foreground: #bfdbfe;
    --destructive: #ef4444;
    --destructive-foreground: #0b1120;
    --border: #263244;
    --input: #64748b;
    --ring: #93c5fd;
    --chart-1: #60a5fa;
    --chart-2: #93c5fd;
    --chart-3: #86efac;
    --chart-4: #fcd34d;
    --chart-5: #fca5a5;
    --sidebar: #0f172a;
    --sidebar-background: #0f172a;
    --sidebar-foreground: #f8fafc;
    --sidebar-primary: #3b82f6;
    --sidebar-primary-foreground: #0b1120;
    --sidebar-accent: #172554;
    --sidebar-accent-foreground: #bfdbfe;
    --sidebar-border: #263244;
    --sidebar-ring: #93c5fd;
}
```

- [ ] **Step 3: Tambahkan base layer aksesibilitas**

Tambahkan di akhir `@layer base`:

```css
html {
    scroll-behavior: smooth;
}

button:not(:disabled),
[role='button']:not([aria-disabled='true']),
summary,
select:not(:disabled),
label[for],
a[href] {
    cursor: pointer;
}

button:disabled,
[aria-disabled='true'],
select:disabled {
    cursor: not-allowed;
}

:where(a, button, [role='button'], input, select, textarea, summary):focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
```

- [ ] **Step 4: Ubah warna awal di `resources/views/app.blade.php`**

Ganti `oklch(1 0 0)` menjadi `#f3f6fc` dan `oklch(0.145 0 0)` menjadi `#0b1120`. Ubah `lang` menjadi `lang="id"`. Tambahkan dua meta theme color setelah viewport:

```html
<meta name="theme-color" content="#f3f6fc" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1120" media="(prefers-color-scheme: dark)">
```

- [ ] **Step 5: Ubah progress bar di `resources/js/app.ts`**

Ganti `color: '#4B5563'` menjadi `color: '#2563EB'`.

- [ ] **Step 6: Ganti `public/favicon.svg` dengan monogram placeholder**

Gunakan SVG sederhana: kotak biru `#2563EB` dengan tiga batang putih bertumpuk yang menyerupai rak. Tidak perlu teks. Ini placeholder yang jujur, bukan logo final.

- [ ] **Step 7: Tambahkan `StatusTone` di `resources/js/types/ui.ts`**

```ts
export type StatusTone =
    | 'neutral'
    | 'info'
    | 'success'
    | 'warning'
    | 'danger';
```

- [ ] **Step 8: Verifikasi**

Run: `bun run build`
Expected: PASS tanpa error CSS atau font.

Run: `bun run types:check`
Expected: PASS.

Run: `grep -r "Plus Jakarta Sans" public/build/assets/*.css | head -1`
Expected: nama font ditemukan.

---

### Task 2: Shared UI Components

**Files:**
- Create: `resources/js/components/PageHeader.vue`
- Create: `resources/js/components/StatCard.vue`
- Create: `resources/js/components/StatusBadge.vue`
- Create: `resources/js/components/EmptyState.vue`
- Create: `resources/js/components/TableShell.vue`
- Modify: `resources/js/types/ui.ts`

**Interfaces:**
- Consumes: `StatusTone` dari Task 1, token dari Task 1.
- Produces:
  - `PageHeader` props `title: string`, `description?: string`, slot `actions`.
  - `StatCard` props `label: string`, `value: number`, `href?: string | null`, `icon?: Component`.
  - `StatusBadge` props `label: string`, `tone?: StatusTone`.
  - `EmptyState` props `title: string`, `description?: string`, `icon?: Component`, slot `actions`.
  - `TableShell` props `caption: string`, slot `toolbar`, default slot untuk `thead` dan `tbody`.

- [ ] **Step 1: Buat `PageHeader.vue`**

```vue
<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0">
            <h1
                class="text-[22px] leading-tight font-bold tracking-tight text-foreground md:text-[28px]"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-1 max-w-2xl text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center gap-2"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
```

- [ ] **Step 2: Buat `StatCard.vue`**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

const props = defineProps<{
    label: string;
    value: number;
    href?: string | null;
    icon?: Component;
}>();

const formatted = new Intl.NumberFormat('id-ID').format(props.value);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href ?? undefined"
        class="flex min-h-32 flex-col justify-between rounded-2xl border bg-card p-5 transition-colors"
        :class="href ? 'hover:border-primary/40 hover:bg-primary-soft/40' : ''"
    >
        <div class="flex items-start justify-between gap-3">
            <span class="text-sm font-medium text-muted-foreground">
                {{ label }}
            </span>
            <span
                v-if="icon"
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"
            >
                <component :is="icon" class="size-5" aria-hidden="true" />
            </span>
        </div>
        <p class="mt-4 text-[26px] leading-none font-bold tabular-nums md:text-[32px]">
            {{ formatted }}
        </p>
    </component>
</template>
```

- [ ] **Step 3: Buat `StatusBadge.vue`**

```vue
<script setup lang="ts">
import { computed } from 'vue';
import type { StatusTone } from '@/types';

const props = withDefaults(
    defineProps<{
        label: string;
        tone?: StatusTone;
    }>(),
    { tone: 'neutral' },
);

const toneClass = computed(
    () =>
        ({
            neutral:
                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            info: 'bg-[#DBEAFE] text-[#1E40AF] dark:bg-[#1E3A8A] dark:text-[#BFDBFE]',
            success:
                'bg-[#DCFCE7] text-[#166534] dark:bg-[#14532D] dark:text-[#86EFAC]',
            warning:
                'bg-[#FEF3C7] text-[#92400E] dark:bg-[#78350F] dark:text-[#FCD34D]',
            danger:
                'bg-[#FEE2E2] text-[#991B1B] dark:bg-[#7F1D1D] dark:text-[#FCA5A5]',
        })[props.tone],
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
        :class="toneClass"
    >
        {{ label }}
    </span>
</template>
```

- [ ] **Step 4: Buat `EmptyState.vue`**

```vue
<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
    }>(),
    { icon: Inbox },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed bg-card px-6 py-12 text-center"
    >
        <span
            class="flex size-12 items-center justify-center rounded-2xl bg-primary-soft text-primary"
        >
            <component :is="icon" class="size-6" aria-hidden="true" />
        </span>
        <p class="mt-4 font-semibold text-foreground">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-4 flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
```

- [ ] **Step 5: Buat `TableShell.vue`**

```vue
<script setup lang="ts">
defineProps<{
    caption: string;
}>();
</script>

<template>
    <div class="overflow-hidden rounded-2xl border bg-card">
        <div v-if="$slots.toolbar" class="border-b p-4">
            <slot name="toolbar" />
        </div>
        <div class="overflow-x-auto">
            <table
                class="w-full text-sm [&_tbody_tr]:border-t [&_tbody_tr]:border-border/70 [&_tbody_tr:hover]:bg-slate-50/70 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-xs [&_th]:font-semibold [&_th]:text-muted-foreground [&_thead]:bg-slate-50 dark:[&_tbody_tr:hover]:bg-slate-900/40 dark:[&_thead]:bg-slate-900/60"
            >
                <caption class="sr-only">
                    {{ caption }}
                </caption>
                <slot />
            </table>
        </div>
    </div>
</template>
```

- [ ] **Step 6: Verifikasi**

Run: `bun run types:check`
Expected: PASS.

Run: `bun run build`
Expected: PASS.

---

### Task 3: App Shell, Sidebar, and Header

**Files:**
- Modify: `resources/js/components/AppLogo.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/components/AppSidebarHeader.vue`
- Modify: `resources/js/components/AppContent.vue`
- Modify: `resources/js/components/NavMain.vue`
- Modify: `resources/js/components/NavUser.vue`
- Modify: `resources/js/components/UserInfo.vue`
- Modify: `resources/js/layouts/app/AppSidebarLayout.vue`
- Modify: `resources/js/components/ui/sidebar/SidebarTrigger.vue`
- Delete: `resources/js/components/NavFooter.vue`

**Interfaces:**
- Consumes: token dari Task 1.
- Produces: shell dengan `id="main-content"`, tautan notifikasi nyata, label "Menu" mobile, dan skip link.

- [ ] **Step 1: Restyle `AppLogo.vue`**

Monogram kotak biru `size-9 rounded-xl bg-primary text-primary-foreground`, teks `ENT` di dalamnya dengan `text-xs font-extrabold tracking-tight`. Wordmark `ENT Inventory` di sampingnya, `text-sm font-bold`. Di dark, monogram tetap `bg-primary` dengan `text-primary-foreground`.

- [ ] **Step 2: Ubah `AppSidebar.vue`**

- Hapus import `NavFooter`, `BookOpen`, `FolderGit2`.
- Hapus array `footerNavItems`.
- Hapus `<NavFooter :items="footerNavItems" />` dari `SidebarFooter`.
- Pertahankan `NavMain` dan `NavUser`.
- Ubah label grup menjadi sentence case: `Persetujuan`, `Laporan`, `Inventaris`, `Manajemen`. Tidak ada uppercase.

- [ ] **Step 3: Restyle `NavMain.vue`**

- `SidebarGroupLabel` memakai `text-xs font-semibold text-muted-foreground`.
- `SidebarMenuButton` mendapat class `h-10 rounded-xl px-3 data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold data-[active=true]:text-sidebar-accent-foreground`.
- `Link` mendapat `aria-current="page"` bila `isCurrentUrl(item.href)`.

- [ ] **Step 4: Ubah `SidebarTrigger.vue`**

Ganti `<span class="sr-only">Toggle sidebar</span>` menjadi:

```vue
<span class="text-sm font-medium md:sr-only">Menu</span>
```

Tambahkan `aria-label="Buka menu navigasi"` pada `Button`. Ukuran tombol menjadi `h-11 w-auto px-2.5 md:h-9 md:w-9 md:px-0`.

- [ ] **Step 5: Tulis ulang `AppSidebarHeader.vue`**

- `<header class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 bg-background px-4 md:h-[72px] md:px-6 lg:px-8">`
- Kiri: `SidebarTrigger`, breadcrumb (disembunyikan di bawah `md` jika kosong).
- Kanan: `Link` ke `/notifications` dengan `Bell` dan `aria-label` yang menyebut jumlah belum dibaca bila ada.
- Gunakan `usePage().props.unreadNotifications` (sudah tersedia dari middleware) untuk badge angka nyata. Jika 0, tidak ada badge.
- Badge: `absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground`.
- Di halaman pengaturan, tambahkan tombol "Kembali" hanya jika breadcrumb kedua ada; gunakan `Link` ke breadcrumb pertama. Jika tidak mudah, lewati dan biarkan breadcrumb.

- [ ] **Step 6: Ubah `AppContent.vue`**

Tambahkan `id="main-content"` pada `SidebarInset`, `class="min-w-0"` pada root, dan padding konten:

```html
<SidebarInset
    id="main-content"
    :class="className"
    class="min-w-0 bg-background px-4 py-5 md:px-6 md:py-6 lg:px-8 lg:py-8"
>
```

Hapus `mx-auto max-w-7xl` dari varian header.

- [ ] **Step 7: Tambahkan skip link di `AppSidebarLayout.vue`**

Letakkan sebelum `<AppShell>`:

```html
<a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-xl focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-primary-foreground"
>
    Lewati ke konten
</a>
```

- [ ] **Step 8: Restyle `NavUser.vue` dan `UserInfo.vue`**

- `UserInfo` memakai avatar inisial `size-9 rounded-xl bg-primary-soft text-primary` dengan teks inisial.
- `NavUser` `SidebarMenuButton` memakai `h-12 rounded-xl`.
- Pertahankan `data-test="sidebar-menu-button"`.

- [ ] **Step 9: Hapus `NavFooter.vue`**

Pastikan tidak ada import yang tersisa:

Run: `grep -rn "NavFooter" resources/js`
Expected: tidak ada hasil.

- [ ] **Step 10: Verifikasi**

Run: `bun run types:check`
Expected: PASS.

Run: `bun run build`
Expected: PASS.

Manual: buka halaman login lalu dashboard. Di 375px, tombol "Menu" terlihat dan membuka sheet. Di 1440px, sidebar 272px dan trigger menjadi ikon.

---

### Task 4: Auth Split Layout

**Files:**
- Modify: `resources/js/layouts/AuthLayout.vue`
- Modify: `resources/js/layouts/auth/AuthSplitLayout.vue`
- Create: `resources/js/components/AuthShowcase.vue`
- Modify: `resources/js/pages/auth/Login.vue`
- Modify: `resources/js/pages/auth/ForgotPassword.vue`
- Modify: `resources/js/pages/auth/ResetPassword.vue`
- Modify: `resources/js/pages/auth/ChangePassword.vue`

**Interfaces:**
- Consumes: `AppLogo`, token, `Button`, `Input`, `Label`.
- Produces: layout auth split yang dipakai semua halaman `auth/*`.

- [ ] **Step 1: Buat `AuthShowcase.vue`**

Struktur:

```vue
<template>
    <aside class="relative h-full overflow-hidden rounded-3xl bg-primary p-8 text-primary-foreground lg:p-12">
        <div class="relative z-10 flex h-full flex-col justify-between gap-10">
            <div class="rounded-2xl bg-white/10 p-4" aria-hidden="true">
                <!-- mini sidebar labels: Dashboard, Inventaris, Peminjaman, Approval, Checkout, Pengembalian -->
                <!-- one card with status chips: Menunggu, Disetujui, Terlambat -->
            </div>
            <div>
                <h2 class="text-3xl leading-tight font-bold">
                    Inventaris rapi, peminjaman tercatat.
                </h2>
                <p class="mt-3 max-w-md text-sm text-primary-foreground/80">
                    Satu tempat untuk katalog aset, pengajuan, persetujuan, dan pengembalian.
                </p>
            </div>
        </div>
        <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-[3rem] border border-white/10" aria-hidden="true" />
        <div class="pointer-events-none absolute -bottom-20 -left-20 size-72 rounded-[3rem] border border-white/10" aria-hidden="true" />
    </aside>
</template>
```

Mini UI memakai label nyata dan chip nyata, tanpa angka. Beri `aria-hidden="true"` pada mini UI, bukan pada headline.

- [ ] **Step 2: Tulis ulang `AuthSplitLayout.vue`**

- Root: `min-h-dvh bg-background p-4 md:p-6 lg:p-8`.
- Kartu: `mx-auto grid w-full max-w-6xl overflow-hidden rounded-3xl border bg-card lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]`.
- Kiri: `flex flex-col justify-center px-6 py-10 md:px-12 lg:px-16`.
- Form wrapper: `mx-auto w-full max-w-sm`.
- Logo di atas, judul `text-2xl font-bold`, deskripsi `text-sm text-muted-foreground`.
- `<slot />`.
- Kanan: `<AuthShowcase class="hidden lg:block" />`.
- Mobile: panel kanan hidden, padding 16px.

- [ ] **Step 3: Ubah `AuthLayout.vue`**

Import `AuthSplitLayout` dan gunakan itu, bukan `AuthSimpleLayout`.

- [ ] **Step 4: Restyle `Login.vue`**

- `defineOptions.layout.title`: `Masuk ke akun`.
- `description`: `Masukkan NRP atau username dan password untuk melanjutkan.`
- Pertahankan `<Head title="Log in" />`? Ganti menjadi `<Head title="Masuk" />`. E2E tidak memakai title.
- Input: `class="h-11 rounded-xl"`, label `text-[13px] font-medium`.
- Checkbox dan tautan lupa password tetap.
- Tombol submit: `class="mt-2 h-11 w-full rounded-xl"`, teks `Masuk`.
- Tambahkan di bawah form: `<p class="text-center text-xs text-muted-foreground">Akun anggota dibuat oleh WM.</p>`.
- Pertahankan `data-test="login-button"`, `name`, `id`, `autocomplete`, `tabindex`, `v-focus`.

- [ ] **Step 5: Restyle halaman auth lain**

Untuk `ForgotPassword.vue`, `ResetPassword.vue`, dan `ChangePassword.vue`:

- Pastikan `defineOptions.layout.title` dan `description` terisi.
- Input memakai `h-11 rounded-xl`.
- Tombol submit `h-11 w-full rounded-xl`.
- Pertahankan field `name`, `id`, dan logika form.

- [ ] **Step 6: Verifikasi**

Run: `bun run types:check`
Expected: PASS.

Run: `bun run build`
Expected: PASS.

Manual: 375px, form tidak overflow, panel kanan tidak terlihat. 1440px, panel biru terlihat.

---

### Task 5: Dashboard Aggregates and UI

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `tests/Feature/DashboardTest.php`
- Create: `resources/js/components/TrendChart.vue`
- Create: `resources/js/components/StatusBars.vue`
- Modify: `resources/js/pages/Dashboard.vue`

**Interfaces:**
- Consumes: `PageHeader`, `StatCard`, `StatusBadge`, `EmptyState`, token.
- Produces:
  - Props dashboard: `trend: { label: string; value: number }[]` (6 item), `statusBreakdown: { status: string; value: number }[]`, `scope: 'personal' | 'all'`.
  - `TrendChart` props `points: { label: string; value: number }[]`, `title: string`, `description?: string`.
  - `StatusBars` props `items: { label: string; value: number; tone: StatusTone }[]`, `title: string`, `description?: string`.

- [ ] **Step 1: Tulis failing test di `tests/Feature/DashboardTest.php`**

Tambahkan import `App\Models\BorrowRequest`, `App\Models\Member`, dan `Inertia\Testing\AssertableInertia as Assert`. Tambahkan test:

```php
public function test_dashboard_exposes_member_scoped_trend_and_status_data(): void
{
    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id]);

    BorrowRequest::factory()->for($member)->create([
        'status' => 'pending_approval',
        'created_at' => now()->startOfMonth(),
    ]);
    BorrowRequest::factory()->for($member)->create([
        'status' => 'approved',
        'created_at' => now()->subMonthNoOverflow()->startOfMonth(),
    ]);
    BorrowRequest::factory()->create([
        'status' => 'approved',
        'created_at' => now()->startOfMonth(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('scope', 'personal')
            ->has('trend', 6)
            ->where('trend.4.value', 1)
            ->where('trend.5.value', 1)
            ->has('statusBreakdown', 2)
            ->where('statusBreakdown.0.status', 'pending_approval')
            ->where('statusBreakdown.0.value', 1)
            ->where('statusBreakdown.1.status', 'approved')
            ->where('statusBreakdown.1.value', 1)
        );
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=test_dashboard_exposes_member_scoped_trend_and_status_data`
Expected: FAIL karena prop `scope`, `trend`, dan `statusBreakdown` belum ada.

- [ ] **Step 3: Implementasikan agregat di `DashboardController.php`**

Tambahkan method `dashboardAnalytics(array $roles, ?int $memberId): array` yang mengembalikan `scope`, `trend`, dan `statusBreakdown`.

Aturan:

- `$isAdmin = in_array('admin_korlab', $roles, true) || in_array('wm', $roles, true);`
- `$scope = $isAdmin ? 'all' : 'personal';`
- `$scopedMemberId = $isAdmin ? null : $memberId;`
- Jika `$scope === 'personal'` dan `$scopedMemberId === null`, kembalikan `trend` berisi 6 bulan dengan nilai 0 dan `statusBreakdown` kosong.
- Query trend:
  ```php
  $counts = BorrowRequest::query()
      ->when($scopedMemberId, fn ($query) => $query->where('member_id', $scopedMemberId))
      ->where('created_at', '>=', now()->startOfMonth()->subMonths(5))
      ->selectRaw("to_char(date_trunc('month', created_at), 'YYYY-MM') as month, count(*) as total")
      ->groupBy('month')
      ->pluck('total', 'month');
  ```
- Bangun 6 bulan dari `now()->startOfMonth()->subMonths(5)` sampai bulan ini. Label memakai `$cursor->locale('id')->isoFormat('MMM')`. Nilai memakai `(int) ($counts[$cursor->format('Y-m')] ?? 0)`.
- Query status:
  ```php
  $statusCounts = BorrowRequest::query()
      ->when($scopedMemberId, fn ($query) => $query->where('member_id', $scopedMemberId))
      ->selectRaw('status, count(*) as total')
      ->groupBy('status')
      ->pluck('total', 'status');
  ```
- Urutan status: `pending_approval`, `approved`, `rejected`, `cancelled`. Sertakan hanya nilai di atas 0.
- Tambahkan ketiga prop ke `Inertia::render`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=test_dashboard_exposes_member_scoped_trend_and_status_data`
Expected: PASS.

- [ ] **Step 5: Buat `TrendChart.vue`**

Props sesuai Interfaces. Struktur:

- `<figure class="rounded-2xl border bg-card p-5">`
- `<figcaption>`: judul `text-base font-semibold` dan deskripsi `text-sm text-muted-foreground`.
- Area chart SVG `viewBox="0 0 100 100" preserveAspectRatio="none"` dengan `class="mt-4 h-44 w-full sm:h-52"`.
- Path area: `M 0 100 L x0 y0 ... L 100 100 Z`, fill `url(#<id>)`.
- Path garis: `M x0 y0 L ...`, `stroke="var(--chart-1)"`, `stroke-width="2"`, `vector-effect="non-scaling-stroke"`, `fill="none"`, `stroke-linejoin="round"`, `stroke-linecap="round"`.
- Gradient `linearGradient` dari `var(--chart-1)` opasitas 0.22 ke 0.02.
- Grid horizontal tiga garis memakai `<line>` dengan `stroke="var(--border)"` dan `vector-effect="non-scaling-stroke"`.
- Label bulan di bawah chart memakai grid kolom `repeat(points.length, minmax(0, 1fr))`, `text-center text-xs text-muted-foreground`.
- `<table class="sr-only">` dengan caption, thead Bulan/Nilai, dan baris data.
- `useId()` dari Vue untuk id gradient.
- Jangan render chart di komponen ini jika semua nilai 0; pemanggil yang memilih `EmptyState`.

- [ ] **Step 6: Buat `StatusBars.vue`**

Props sesuai Interfaces. Struktur:

- `<section class="rounded-2xl border bg-card p-5">`
- Judul dan deskripsi.
- `<ul class="mt-4 grid gap-3">`
- Setiap baris: label di kiri, count `tabular-nums` di kanan, bar di bawah dengan `h-2 rounded-full bg-muted` dan inner bar `h-2 rounded-full` dengan warna tone dan `width: value/max*100%`.
- `max` minimal 1.
- Tone memakai pasangan yang sama dengan `StatusBadge`, tetapi bar memakai warna solid: info `#2563EB`, success `#16A34A`, warning `#D97706`, danger `#DC2626`, neutral `#64748B`.
- Teks label dan angka selalu terlihat, jadi warna bukan satu-satunya sinyal.

- [ ] **Step 7: Tulis ulang `Dashboard.vue`**

- Props baru: `stats`, `announcements`, `trend`, `statusBreakdown`, `scope`.
- Import ikon relevan: `ClipboardCheck`, `ScanLine`, `Undo2`, `Wrench`, `AlertTriangle`, `Users`, `Package`, `Clock`, `ArrowRight`, `Megaphone`.
- `PageHeader` dengan judul `Dashboard` dan deskripsi `Ringkasan inventaris dan peminjaman.` Aksi: `Button` ke `/peminjaman/baru` dengan teks `Ajukan peminjaman` (hanya jika role user? Tampilkan untuk semua, route mengatur akses).
- KPI grid: `grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5`. Setiap `StatCard` mendapat ikon berdasarkan label:
  - `Menunggu approval` -> `ClipboardCheck`
  - `Siap diserahkan` -> `ScanLine`
  - `Menunggu verifikasi kembali` -> `Undo2`
  - `Laporan kerusakan terbuka` -> `Wrench`
  - `Pinjaman terlambat` -> `AlertTriangle`
  - `Anggota terblokir` -> `AlertTriangle`
  - `Anggota aktif` -> `Users`
  - `Pengajuan diproses` -> `ClipboardCheck`
  - `Pinjaman aktif` -> `Package`
  - `Terlambat` -> `Clock`
  - `Riwayat selesai` -> `Package`
- Jika `stats` kosong: `EmptyState` dengan judul `Belum ada ringkasan untuk akun ini.` dan deskripsi `Hubungi WM jika akun belum terhubung ke anggota.`.
- Grid utama: `grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]`.
- Kiri: `TrendChart` dengan title `Pengajuan peminjaman, 6 bulan terakhir` untuk `scope === 'all'`, atau `Pengajuan saya, 6 bulan terakhir` untuk personal. Deskripsi `Jumlah pengajuan yang masuk per bulan.`. Jika semua nilai 0, `EmptyState` dengan aksi ke `/inventory`.
- Kanan: panel `Pengumuman` memakai `Card` atau `section rounded-2xl border bg-card p-5`. Daftar 5 pengumuman, tanggal, isi dengan `line-clamp-3`. Empty state `Belum ada pengumuman.`.
- Baris kedua: `StatusBars` dengan title `Status pengajuan saat ini`, dan kartu `Aksi cepat` berisi tautan nyata: `Lihat inventaris`, `Ajukan peminjaman`, `Buka approval` (jika admin/WM), `Checkout` (jika admin/WM).
- Pemetaan status label dan tone:
  - `pending_approval`: `Menunggu`, warning
  - `approved`: `Disetujui`, success
  - `rejected`: `Ditolak`, danger
  - `cancelled`: `Dibatalkan`, neutral
- Hapus `formatDate` jika tidak dipakai; pertahankan untuk pengumuman.
- Tidak ada angka delta, tidak ada chart kosong, tidak ada menu tiga titik dekoratif.

- [ ] **Step 8: Verifikasi**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS.

Run: `bun run types:check`
Expected: PASS.

Run: `bun run build`
Expected: PASS.

Manual: dashboard light dan dark, 375px dan 1440px. Chart dan panel tidak overflow.

---

### Task 6: Member-Facing Pages

**Files:**
- Modify: `resources/js/pages/inventory/Index.vue`
- Modify: `resources/js/pages/inventory/Show.vue`
- Modify: `resources/js/pages/borrowing/Index.vue`
- Modify: `resources/js/pages/borrowing/Create.vue`
- Modify: `resources/js/pages/borrowing/Show.vue`
- Modify: `resources/js/pages/notifications/Index.vue`

**Interfaces:**
- Consumes: `PageHeader`, `TableShell`, `StatusBadge`, `EmptyState`, token.
- Produces: halaman anggota yang responsif tanpa mengubah route, filter, atau label e2e.

- [ ] **Step 1: Restyle `inventory/Index.vue`**

- Ganti header manual dengan `PageHeader` judul `Inventaris`, deskripsi `Katalog barang UKM Jurnalistik dan ketersediaannya.`.
- Bungkus filter dalam `section class="rounded-2xl border bg-card p-4"` dengan grid `sm:grid-cols-2 lg:grid-cols-4`. Input/select `h-11 rounded-xl`. Tombol `Terapkan`.
- Ganti tabel dengan grid kartu: `grid gap-4 sm:grid-cols-2 xl:grid-cols-3`.
- Setiap kartu: kode `font-mono text-xs text-muted-foreground`, nama sebagai `Link` ke `/inventory/{id}` dengan class `font-semibold hover:text-primary`, kategori dan lokasi dengan ikon `Tag` dan `MapPin`, status chip (`Tersedia` success, `Nonaktif` neutral), dan teks `Tersedia X dari Y`.
- Jika `items.data.length === 0`: `EmptyState` judul `Tidak ada barang yang cocok.` deskripsi `Ubah kata kunci atau filter, lalu coba lagi.` Aksi `Button` `variant="outline"` untuk reset filter ke `/inventory`.
- Pagination: pertahankan logika `items.links`, gunakan `Button size="sm"` dan `Link`.
- Pertahankan link name berisi nama barang untuk e2e.

- [ ] **Step 2: Restyle `inventory/Show.vue`**

- `PageHeader` judul nama barang, deskripsi kode dan kategori.
- Ringkasan ketersediaan dalam kartu terpisah: total, tersedia, dipinjam.
- Panel detail, jadwal 3 bulan, dan riwayat perawatan memakai `rounded-2xl border bg-card p-4 md:p-5`.
- Tombol `Tambah ke keranjang` mempertahankan teks dan perilaku.
- Form laporan kerusakan tetap.
- Di mobile, kalender jadwal menjadi scroll horizontal yang terkontrol, bukan overflow halaman. Bungkus dengan `overflow-x-auto` dan `min-w-[640px]` pada grid kalender.
- Pertahankan label status yang sudah ada.

- [ ] **Step 3: Restyle `borrowing/Index.vue`**

- `PageHeader` judul `Peminjaman Saya`, deskripsi `Riwayat dan status pengajuan peminjaman.` Aksi `Button` ke `/peminjaman/baru` teks `Ajukan peminjaman`.
- `TableShell caption="Daftar pengajuan peminjaman"`.
- Status memakai `StatusBadge` dengan tone: `pending_approval` warning, `approved` success, `rejected` danger, `cancelled` neutral.
- Kategori `Terjadwal` info, `Mendadak` warning.
- Link `Detail` dipertahankan.
- Empty state `Belum ada pengajuan.` dengan aksi `Ajukan peminjaman`.

- [ ] **Step 4: Restyle `borrowing/Create.vue`**

- `PageHeader` judul `Pengajuan peminjaman`, deskripsi `Periksa barang, isi jadwal, lalu setujui SOP.`.
- Blok peringatan keterlambatan memakai `rounded-2xl border border-destructive/40 bg-destructive/5 p-4`.
- Ganti em dash pada daftar blok dengan titik dua: `{{ block.reference_no ?? 'Transaksi' }}: tenggat ...`.
- Keranjang memakai `TableShell caption="Barang di keranjang"` atau kartu, tetapi pertahankan `tbody tr` dan tombol `Hapus`.
- Form memakai `section class="rounded-2xl border bg-card p-4 md:p-6"`. Semua input `h-11 rounded-xl`. Textarea `rounded-xl`.
- Pertahankan ID `category`, `usage_location`, `pickup_at`, `return_at`, `purpose`, `notes`.
- Pertahankan tepat satu checkbox SOP.
- Tombol `Kirim pengajuan` dan `Batal` dipertahankan.
- Empty state keranjang tetap dan tautan ke `/inventory`.

- [ ] **Step 5: Restyle `borrowing/Show.vue`**

- Pertahankan `<h1>` berisi `request.reference_no ?? #id` agar e2e membaca referensi.
- Tambahkan `StatusBadge` di dekat h1, bukan menggantikan h1.
- Ringkasan dalam `dl` kartu, timeline vertikal sederhana untuk status pengajuan dan pinjaman.
- Tabel barang memakai `TableShell caption="Barang yang diajukan"`.
- Tombol `Ajukan pengembalian`, `Batalkan pengajuan`, dan aksi perpanjangan dipertahankan.
- Dialog browser tetap.

- [ ] **Step 6: Restyle `notifications/Index.vue`**

- `PageHeader` judul `Notifikasi`, deskripsi `Pembaruan pengajuan, pengembalian, dan pengumuman.`.
- Daftar kartu `rounded-2xl border bg-card p-4`, judul, isi, waktu, dan tombol `Tandai dibaca` yang nyata.
- Empty state `Belum ada notifikasi.`.
- Pertahankan aksi `markAllRead` dan `markRead`.

- [ ] **Step 7: Verifikasi**

Run: `bun run types:check`
Expected: PASS.

Run: `bun run build`
Expected: PASS.

Run: `bun run e2e -- --grep "happy path|urgent|overdue"`
Expected: PASS jika database e2e tersedia. Jika tidak, catat `NOT RUN` dengan alasannya.

---

### Task 7: Admin Core Index and Show Pages

**Files:**
- Modify: `resources/js/pages/admin/approval/Index.vue`
- Modify: `resources/js/pages/admin/approval/Show.vue`
- Modify: `resources/js/pages/admin/checkout/Index.vue`
- Modify: `resources/js/pages/admin/checkout/Show.vue`
- Modify: `resources/js/pages/admin/return/Index.vue`
- Modify: `resources/js/pages/admin/return/Show.vue`
- Modify: `resources/js/pages/admin/extensions/Index.vue`
- Modify: `resources/js/pages/admin/reports/Index.vue`

**Interfaces:**
- Consumes: `PageHeader`, `TableShell`, `StatusBadge`, `EmptyState`, token.
- Produces: halaman kerja admin yang lebih jelas, dengan label dan dialog yang sama.

- [ ] **Step 1: Restyle halaman Index**

Untuk approval, checkout, return, extensions, reports:

- Ganti header manual dengan `PageHeader`.
- Bungkus filter dalam `section class="rounded-2xl border bg-card p-4"`.
- Bungkus tabel dengan `TableShell` dan `caption` yang sesuai.
- Ganti status hardcoded dengan `StatusBadge`.
- Pertahankan label aksi: `Periksa`, `Serahkan`, `Cari`, `Terapkan`.
- Empty state spesifik dengan aksi yang relevan.

- [ ] **Step 2: Restyle `admin/approval/Show.vue`**

- Pertahankan `<h1>` berisi referensi.
- Header memakai `PageHeader` dengan aksi `Kembali`.
- Ringkasan dua kolom: detail pengajuan dan status anggota.
- Tabel barang memakai `TableShell`.
- Tombol `Setujui`, `Tolak`, dan `Batalkan` dipertahankan. `window.confirm` tetap.
- Panel keputusan dan catatan memakai `section rounded-2xl border bg-card p-4`.

- [ ] **Step 3: Restyle `admin/checkout/Show.vue`**

- Pertahankan `<h1>` berisi referensi.
- Layout desktop dua kolom: kiri panel scan dan daftar barang, kanan ringkasan transaksi dan aksi.
- Panel scan memakai kartu dengan input `h-11 rounded-xl`, tombol `Periksa`, dan pesan scan dengan warna semantik plus teks.
- Tabel barang memakai `TableShell`.
- Form checklist dan tombol `Konfirmasi serah terima` dipertahankan.
- Pesan scan seperti `E2E Kamera cocok.` tidak diubah.

- [ ] **Step 4: Restyle `admin/return/Show.vue`**

- Pertahankan `<h1>` berisi referensi.
- Layout desktop dua kolom: kiri detail pinjaman dan foto, kanan form verifikasi.
- Tabel barang dan form kondisi memakai `TableShell` dan kartu.
- Pertahankan label kondisi `Baik`, `Rusak`, `Tidak lengkap`, `Hilang`, tombol `Selesaikan transaksi`, dan dialog.
- Foto memakai `aspect-square` dan `object-cover` agar tidak menggeser layout.

- [ ] **Step 5: Verifikasi**

Run: `bun run types:check`
Expected: PASS.

Run: `bun run build`
Expected: PASS.

Run: `bun run e2e`
Expected: PASS jika database e2e tersedia. Jika tidak, catat `NOT RUN`.

---

### Task 8: Remaining Admin, WM, Settings, QR, and Welcome

**Files:**
- Modify: `resources/js/pages/admin/announcements/Index.vue`
- Modify: `resources/js/pages/admin/inventory/items/Index.vue`
- Modify: `resources/js/pages/admin/inventory/items/Qr.vue`
- Modify: `resources/js/pages/admin/inventory/categories/Index.vue`
- Modify: `resources/js/pages/admin/inventory/locations/Index.vue`
- Modify: `resources/js/pages/admin/inventory/assets/Index.vue`
- Modify: `resources/js/pages/admin/inventory/damage/Index.vue`
- Modify: `resources/js/pages/wm/members/Index.vue`
- Modify: `resources/js/pages/wm/divisions/Index.vue`
- Modify: `resources/js/pages/wm/accounts/Index.vue`
- Modify: `resources/js/pages/wm/blocked/Index.vue`
- Modify: `resources/js/pages/wm/audit/Index.vue`
- Modify: `resources/js/layouts/settings/Layout.vue`
- Modify: `resources/js/pages/settings/Profile.vue`
- Modify: `resources/js/pages/settings/Security.vue`
- Modify: `resources/js/pages/settings/Appearance.vue`
- Modify: `resources/js/components/AppearanceTabs.vue`
- Modify: `routes/web.php`
- Modify: `tests/Feature/ExampleTest.php`
- Modify: `resources/js/app.ts`
- Delete: `resources/js/pages/Welcome.vue`

**Interfaces:**
- Consumes: semua komponen bersama.
- Produces: seluruh halaman memakai pola baru; `/` menjadi redirect.

- [ ] **Step 1: Terapkan pola halaman pada admin inventaris dan WM**

Untuk setiap file Index di daftar:

- Ganti header manual dengan `PageHeader`.
- Bungkus filter dan tabel dengan `section`/`TableShell`.
- Ganti status hardcoded dengan `StatusBadge`.
- Pertahankan semua form, field `name`, tombol, dan aksi.
- Tambahkan empty state yang menyebut penyebab dan aksi berikutnya.

- [ ] **Step 2: Restyle `AppearanceTabs.vue`**

Tiga kartu tema dalam grid `sm:grid-cols-3`, masing-masing `button` dengan ikon, label, dan state terpilih `border-primary bg-primary-soft text-primary`. Pertahankan `updateAppearance`.

- [ ] **Step 3: Restyle `settings/Layout.vue`**

- Header `PageHeader` judul `Pengaturan`, deskripsi `Kelola profil dan keamanan akun.`.
- Navigasi pengaturan: kartu horizontal yang bisa di-scroll di mobile, sidebar vertikal di `lg`.
- Item aktif memakai `bg-primary-soft text-primary`.
- Pertahankan route profile, security, appearance.

- [ ] **Step 4: Restyle halaman settings**

- `Profile.vue`, `Security.vue`, `Appearance.vue`: form dalam kartu `rounded-2xl border bg-card p-5`, input `h-11 rounded-xl`, tombol utama `h-11 rounded-xl`.
- Pertahankan field dan aksi.

- [ ] **Step 5: QR page light-only**

Di `admin/inventory/items/Qr.vue`:

- Root memakai `style="color-scheme: light"` dan `class="bg-white text-slate-900"`.
- Tambahkan `@media print { @page { margin: 12mm; } }` di scoped style.
- Jangan memakai token dark di halaman ini.
- Pertahankan layout cetak dan QR.

- [ ] **Step 6: Welcome redirect**

Di `routes/web.php`, ganti:

```php
Route::inertia('/', 'Welcome')->name('home');
```

dengan:

```php
Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'))->name('home');
```

Di `resources/js/app.ts`, hapus case `name === 'Welcome'`. Hapus `resources/js/pages/Welcome.vue`.

Di `tests/Feature/ExampleTest.php`, ubah assertion menjadi redirect:

```php
$response->assertRedirect(route('login'));
```

- [ ] **Step 7: Verifikasi**

Run: `php artisan test --filter=ExampleTest`
Expected: PASS.

Run: `bun run types:check`
Expected: PASS.

Run: `bun run build`
Expected: PASS.

Manual: QR page tetap putih di dark mode. `/` mengarah ke login.

---

### Task 9: Responsive, Accessibility, and Delivery Gate

**Files:**
- Create: `e2e/06-ui-shell.spec.ts`
- Modify: halaman yang ditemukan gagal saat audit.

**Interfaces:**
- Consumes: semua task sebelumnya.
- Produces: bukti verifikasi dan Delivery Gate report.

- [ ] **Step 1: Tambahkan `e2e/06-ui-shell.spec.ts`**

Test yang wajib ada:

```ts
import { expect, test } from '@playwright/test';

test.describe('UI shell', () => {
    test('login fits mobile and hides the showcase', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/login');
        await expect(page.getByRole('button', { name: 'Masuk' })).toBeVisible();
        await expect(page.getByText('Inventaris rapi, peminjaman tercatat.')).toBeHidden();
        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth > window.innerWidth,
        );
        expect(overflow).toBe(false);
    });

    test('dashboard holds at desktop and mobile', async ({ page }) => {
        // login helper yang sudah ada
        // 1440px: sidebar terlihat, PageHeader dan chart terlihat
        // 375px: tombol Menu terlihat, tidak ada overflow
    });
});
```

Gunakan helper login yang sudah ada di `e2e/helpers.ts` agar tidak menduplikasi kredensial.

- [ ] **Step 2: Jalankan build, types, dan lint**

Run: `bun run build`
Expected: PASS.

Run: `bun run types:check`
Expected: PASS.

Run: `bun run check`
Expected: PASS. Jika gagal, jalankan `bun run check:fix` lalu ulangi.

- [ ] **Step 3: Jalankan PHPUnit**

Run: `php artisan test`
Expected: PASS. Jika database test tidak tersedia, catat `NOT RUN` dan alasannya.

- [ ] **Step 4: Jalankan Playwright**

Run: `bun run e2e`
Expected: PASS. Jika database e2e tidak tersedia, catat `NOT RUN` dan alasannya.

- [ ] **Step 5: Audit kontras**

Jalankan `contrast-check.py` untuk:

- `#FFFFFF` pada `#2563EB`
- `#5B6B82` pada `#F3F6FC`
- `#0F172A` pada `#FFFFFF`
- `#5B6B82` pada `#FFFFFF`
- `#166534` pada `#DCFCE7`
- `#92400E` pada `#FEF3C7`
- `#991B1B` pada `#FEE2E2`
- `#1E40AF` pada `#DBEAFE`
- `#0B1120` pada `#3B82F6`
- `#94A3B8` pada `#0B1120`
- `#86EFAC` pada `#14532D`
- `#FCD34D` pada `#78350F`
- `#FCA5A5` pada `#7F1D1D`
- `#BFDBFE` pada `#1E3A8A`

Expected: semua PASS untuk teks normal.

- [ ] **Step 6: Audit manual**

Di 320, 375, 768, 1024, dan 1440 piksel, light dan dark:

- Tidak ada overflow horizontal.
- Sidebar mobile bisa dibuka, ditutup, dan ditutup dengan Escape.
- Fokus keyboard terlihat di semua kontrol.
- Form login dan pengajuan bisa diselesaikan dengan keyboard.
- Chart punya teks alternatif.
- QR tetap putih di dark mode.
- Zoom 200% di 375px tidak memotong teks.

- [ ] **Step 7: Tulis Delivery Gate report**

Sertakan satu baris per item Hard Gate, Purpose-Gate, Liveliness, dan Craftsmanship. Setiap PASS harus menyebut bukti konkret: perintah yang dijalankan dan hasilnya, atau elemen yang diklik dan hasilnya. Jika ada FAIL, perbaiki lalu ulangi gate. Jangan menyerahkan report dengan FAIL.

- [ ] **Step 8: Ringkas hasil**

Tulis ringkasan singkat: file yang diubah, perintah yang lulus, yang `NOT RUN` beserta alasan, dan follow-up (dialog browser, logo final, help links).
