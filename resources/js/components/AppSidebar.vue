<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Boxes,
    Building2,
    ClipboardCheck,
    ClipboardList,
    FolderGit2,
    LayoutGrid,
    MapPin,
    Package,
    ScrollText,
    Tag,
    Users,
    UsersRound,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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

const mainNavItems: NavItem[] = [
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
    {
        title: 'Peminjaman Saya',
        href: '/peminjaman',
        icon: ClipboardList,
    },
];

const approvalNavItems: NavItem[] = [
    { title: 'Approval', href: '/admin/approval', icon: ClipboardCheck },
];

const adminNavItems: NavItem[] = [
    { title: 'Kelola barang', href: '/admin/inventory/items', icon: Boxes },
    { title: 'Kategori', href: '/admin/inventory/categories', icon: Tag },
    { title: 'Lokasi', href: '/admin/inventory/locations', icon: MapPin },
    { title: 'Unit aset', href: '/admin/inventory/assets', icon: Boxes },
    {
        title: 'Kerusakan',
        href: '/admin/inventory/damage-reports',
        icon: Wrench,
    },
];

const wmNavItems: NavItem[] = [
    { title: 'Anggota', href: '/wm/members', icon: Users },
    { title: 'Divisi', href: '/wm/divisions', icon: Building2 },
    { title: 'Akun', href: '/wm/accounts', icon: UsersRound },
    { title: 'Audit', href: '/wm/audit', icon: ScrollText },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
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
                v-if="isAdmin || isWm"
                :items="approvalNavItems"
                label="Persetujuan"
            />
            <NavMain v-if="isAdmin" :items="adminNavItems" label="Inventaris" />
            <NavMain v-if="isWm" :items="wmNavItems" label="Manajemen" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
