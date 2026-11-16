<template>
    <aside
        class="relative h-full overflow-hidden rounded-3xl bg-primary p-8 text-primary-foreground lg:p-12"
    >
        <div class="relative z-10 flex h-full flex-col justify-between gap-10">
            <div class="rounded-2xl bg-white/10 p-4" aria-hidden="true">
                <div
                    class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] gap-4"
                >
                    <div class="space-y-2 rounded-xl bg-white/10 p-3">
                        <span
                            v-for="label in [
                                'Dashboard',
                                'Inventaris',
                                'Peminjaman',
                                'Approval',
                                'Checkout',
                                'Pengembalian',
                            ]"
                            :key="label"
                            class="block rounded-lg px-3 py-2 text-xs font-medium"
                            :class="
                                label === 'Inventaris'
                                    ? 'bg-white text-primary'
                                    : 'text-primary-foreground/80'
                            "
                        >
                            {{ label }}
                        </span>
                    </div>

                    <div
                        class="self-start rounded-xl bg-card p-4 text-card-foreground shadow-sm"
                    >
                        <p class="text-sm font-semibold">Status peminjaman</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Pantau setiap pengajuan aset.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span
                                class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800"
                            >
                                Menunggu
                            </span>
                            <span
                                class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800"
                            >
                                Disetujui
                            </span>
                            <span
                                class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-800"
                            >
                                Terlambat
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-3xl leading-tight font-bold">
                    Inventaris rapi, peminjaman tercatat.
                </h2>
                <p class="mt-3 max-w-md text-sm text-primary-foreground/80">
                    Satu tempat untuk katalog aset, pengajuan, persetujuan, dan
                    pengembalian.
                </p>
            </div>
        </div>
        <div
            class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-[3rem] border border-white/10"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -bottom-20 -left-20 size-72 rounded-[3rem] border border-white/10"
            aria-hidden="true"
        />
    </aside>
</template>
