<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    Building2,
    CalendarClock,
    ClipboardCheck,
    ClipboardList,
    Download,
    LayoutGrid,
    MapPin,
    Megaphone,
    Package,
    ScrollText,
    ShieldAlert,
    Tag,
    Undo2,
    ScanLine,
    Users,
    UsersRound,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const roles = computed(
    () => (page.props.auth?.user as { roles?: string[] } | null)?.roles ?? [],
);
const isWm = computed(() => roles.value.includes('wm'));
const isAdmin = computed(() => roles.value.includes('admin_korlab'));
const isMember = computed(() => roles.value.includes('user'));
const isStaff = computed(() => isAdmin.value || isWm.value);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Inventaris',
        href: '/inventory',
        icon: Package,
    },
    ...(isMember.value
        ? [
              {
                  title: 'Peminjaman Saya',
                  href: '/peminjaman',
                  icon: ClipboardList,
              },
          ]
        : []),
]);

const approvalNavItems: NavItem[] = [
    { title: 'Approval', href: '/admin/approval', icon: ClipboardCheck },
    {
        title: 'Perpanjangan',
        href: '/admin/extensions',
        icon: CalendarClock,
    },
];

const reportNavItems: NavItem[] = [
    { title: 'Laporan', href: '/admin/reports', icon: Download },
];

const adminNavItems: NavItem[] = [
    { title: 'Checkout', href: '/admin/checkout', icon: ScanLine },
    { title: 'Pengembalian', href: '/admin/returns', icon: Undo2 },
    { title: 'Kelola barang', href: '/admin/inventory/items', icon: Boxes },
    { title: 'Kategori', href: '/admin/inventory/categories', icon: Tag },
    { title: 'Lokasi', href: '/admin/inventory/locations', icon: MapPin },
    { title: 'Unit aset', href: '/admin/inventory/assets', icon: Boxes },
    {
        title: 'Kerusakan',
        href: '/admin/inventory/damage-reports',
        icon: Wrench,
    },
    { title: 'Pengumuman', href: '/admin/announcements', icon: Megaphone },
];

const wmNavItems: NavItem[] = [
    { title: 'Anggota', href: '/wm/members', icon: Users },
    { title: 'Anggota terblokir', href: '/wm/blocked', icon: ShieldAlert },
    { title: 'Divisi', href: '/wm/divisions', icon: Building2 },
    { title: 'Akun', href: '/wm/accounts', icon: UsersRound },
    { title: 'Audit', href: '/wm/audit', icon: ScrollText },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="isStaff"
                :items="approvalNavItems"
                label="Persetujuan"
            />
            <NavMain v-if="isStaff" :items="reportNavItems" label="Laporan" />
            <NavMain v-if="isStaff" :items="adminNavItems" label="Inventaris" />
            <NavMain v-if="isWm" :items="wmNavItems" label="Manajemen" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
