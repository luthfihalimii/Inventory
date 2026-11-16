<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    ClipboardCheck,
    Clock,
    Megaphone,
    Package,
    ScanLine,
    Undo2,
    Users,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBars from '@/components/StatusBars.vue';
import TrendChart from '@/components/TrendChart.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { StatusTone } from '@/types';

type Stat = {
    label: string;
    value: number;
    href: string | null;
};

type Announcement = {
    id: number;
    title: string;
    content: string;
    published_at: string | null;
};

type TrendPoint = {
    label: string;
    value: number;
};

type StatusBreakdown = {
    status: string;
    value: number;
};

const props = defineProps<{
    stats: Stat[];
    announcements: Announcement[];
    trend: TrendPoint[];
    statusBreakdown: StatusBreakdown[];
    scope: 'personal' | 'all';
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const roles = computed(() => page.props.auth.user?.roles ?? []);
const canManage = computed(
    () => roles.value.includes('admin_korlab') || roles.value.includes('wm'),
);
const isMember = computed(() => roles.value.includes('user'));
const hasTrend = computed(() => props.trend.some((point) => point.value > 0));
const trendTitle = computed(() =>
    props.scope === 'all'
        ? 'Pengajuan peminjaman, 6 bulan terakhir'
        : 'Pengajuan saya, 6 bulan terakhir',
);

const statIcons: Record<string, Component> = {
    'Menunggu approval': ClipboardCheck,
    'Siap diserahkan': ScanLine,
    'Menunggu verifikasi kembali': Undo2,
    'Laporan kerusakan terbuka': Wrench,
    'Pinjaman terlambat': AlertTriangle,
    'Anggota terblokir': AlertTriangle,
    'Anggota aktif': Users,
    'Pengajuan diproses': ClipboardCheck,
    'Pinjaman aktif': Package,
    Terlambat: Clock,
    'Riwayat selesai': Package,
};

const statusMeta: Record<string, { label: string; tone: StatusTone }> = {
    pending_approval: { label: 'Menunggu', tone: 'warning' },
    approved: { label: 'Disetujui', tone: 'success' },
    rejected: { label: 'Ditolak', tone: 'danger' },
    cancelled: { label: 'Dibatalkan', tone: 'neutral' },
};
const statusItems = computed(() =>
    props.statusBreakdown.map(({ status, value }) => ({
        ...(statusMeta[status] ?? { label: status, tone: 'neutral' as const }),
        value,
    })),
);

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        dateStyle: 'medium',
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Dashboard"
            description="Ringkasan inventaris dan peminjaman."
        >
            <template #actions>
                <Button v-if="isMember" as-child>
                    <Link href="/peminjaman/baru">Ajukan peminjaman</Link>
                </Button>
                <Button v-else-if="canManage" as-child>
                    <Link href="/admin/approval">Buka approval</Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="stats.length > 0"
            class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5"
        >
            <StatCard
                v-for="stat in stats"
                :key="stat.label"
                :label="stat.label"
                :value="stat.value"
                :href="stat.href"
                :icon="statIcons[stat.label] ?? Package"
            />
        </div>
        <EmptyState
            v-else
            title="Belum ada ringkasan untuk akun ini."
            description="Hubungi WM jika akun belum terhubung ke anggota."
        />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="min-w-0">
                <TrendChart
                    v-if="hasTrend"
                    :points="trend"
                    :title="trendTitle"
                    description="Jumlah pengajuan yang masuk per bulan."
                />
                <EmptyState
                    v-else
                    title="Belum ada pengajuan peminjaman."
                    description="Pengajuan dalam 6 bulan terakhir akan muncul di sini."
                >
                    <template #actions>
                        <Button variant="outline" as-child>
                            <Link href="/inventory">Lihat inventaris</Link>
                        </Button>
                    </template>
                </EmptyState>
            </div>

            <section class="min-w-0 rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <Megaphone class="size-5 text-primary" aria-hidden="true" />
                    <h2 class="text-base font-semibold">Pengumuman</h2>
                </div>

                <ul v-if="announcements.length > 0" class="mt-4 grid gap-4">
                    <li
                        v-for="announcement in announcements"
                        :key="announcement.id"
                        class="border-b pb-4 last:border-b-0 last:pb-0"
                    >
                        <h3 class="font-medium">{{ announcement.title }}</h3>
                        <time
                            v-if="announcement.published_at"
                            :datetime="announcement.published_at"
                            class="mt-1 block text-xs text-muted-foreground"
                        >
                            {{ formatDate(announcement.published_at) }}
                        </time>
                        <p
                            class="mt-2 line-clamp-3 text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ announcement.content }}
                        </p>
                    </li>
                </ul>
                <EmptyState v-else class="mt-4" title="Belum ada pengumuman." />
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <StatusBars
                :items="statusItems"
                title="Status pengajuan saat ini"
                description="Jumlah pengajuan berdasarkan status."
            />

            <section class="rounded-2xl border bg-card p-5">
                <h2 class="text-base font-semibold">Aksi cepat</h2>
                <nav class="mt-4 grid gap-1" aria-label="Aksi cepat">
                    <Link
                        href="/inventory"
                        class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 text-sm font-medium transition-colors hover:bg-muted"
                    >
                        Lihat inventaris
                        <ArrowRight
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </Link>
                    <Link
                        v-if="isMember"
                        href="/peminjaman/baru"
                        class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 text-sm font-medium transition-colors hover:bg-muted"
                    >
                        Ajukan peminjaman
                        <ArrowRight
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </Link>
                    <Link
                        v-if="canManage"
                        href="/admin/approval"
                        class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 text-sm font-medium transition-colors hover:bg-muted"
                    >
                        Buka approval
                        <ArrowRight
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </Link>
                    <Link
                        v-if="canManage"
                        href="/admin/checkout"
                        class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 text-sm font-medium transition-colors hover:bg-muted"
                    >
                        Checkout
                        <ArrowRight
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </Link>
                </nav>
            </section>
        </div>
    </div>
</template>
