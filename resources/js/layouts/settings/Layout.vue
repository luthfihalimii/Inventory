<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Monitor, ShieldCheck, UserRound } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
        icon: UserRound,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Monitor,
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="flex min-w-0 flex-col gap-8 p-4 py-6">
        <PageHeader
            title="Pengaturan"
            description="Kelola profil dan keamanan akun."
        />

        <div class="flex min-w-0 flex-col gap-6 lg:flex-row lg:gap-12">
            <aside class="min-w-0 lg:w-52 lg:shrink-0">
                <nav
                    class="flex gap-2 overflow-x-auto rounded-2xl border bg-card p-2 lg:flex-col lg:overflow-visible"
                    aria-label="Pengaturan"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="flex h-11 shrink-0 items-center gap-2 rounded-xl px-4 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground"
                        :class="{
                            'bg-primary-soft text-primary':
                                isCurrentOrParentUrl(item.href),
                        }"
                    >
                        <component :is="item.icon" class="size-4" />
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <div class="min-w-0 flex-1 lg:max-w-2xl">
                <section class="space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
