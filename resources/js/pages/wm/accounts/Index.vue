<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Role = 'user' | 'admin_korlab' | 'wm';

type Account = {
    id: number;
    username: string;
    name: string;
    status: string;
    must_change_password: boolean;
    member?: {
        id: number;
        nrp: string;
        name: string;
        division?: { name: string };
    };
    roles: { id: number; name: Role }[];
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

defineProps<{
    accounts: Paginated<Account>;
    members: { id: number; name: string; nrp: string }[];
    roles: Role[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Akun', href: '/wm/accounts' }],
    },
});

const form = useForm({
    username: '',
    member_id: null as number | null,
    role: 'user' as Role,
    password: '',
});

const resetPasswords = reactive<Record<number, string>>({});

function createAccount(): void {
    form.post('/wm/accounts', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function changeRole(account: Account, event: Event): void {
    const role = (event.target as HTMLSelectElement).value;
    router.put(
        `/wm/accounts/${account.id}/roles`,
        { role },
        { preserveScroll: true },
    );
}

function toggleStatus(account: Account): void {
    router.put(
        `/wm/accounts/${account.id}/status`,
        { status: account.status === 'active' ? 'inactive' : 'active' },
        { preserveScroll: true },
    );
}

function resetPassword(account: Account): void {
    router.put(
        `/wm/accounts/${account.id}/password`,
        { password: resetPasswords[account.id] ?? '' },
        {
            preserveScroll: true,
            onSuccess: () => {
                resetPasswords[account.id] = '';
            },
        },
    );
}
</script>

<template>
    <Head title="Akun" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Akun</h1>
            <p class="text-sm text-muted-foreground">
                Kelola akun login, role, status, dan reset password.
            </p>
        </div>

        <form
            class="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
            @submit.prevent="createAccount"
        >
            <div class="md:col-span-2">
                <h2 class="font-medium">Tambah akun</h2>
            </div>

            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input id="username" v-model="form.username" required />
                <InputError :message="form.errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="member_id">Anggota</Label>
                <select
                    id="member_id"
                    v-model="form.member_id"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                    required
                >
                    <option :value="null" disabled>Pilih anggota</option>
                    <option
                        v-for="member in members"
                        :key="member.id"
                        :value="member.id"
                    >
                        {{ member.name }} ({{ member.nrp }})
                    </option>
                </select>
                <InputError :message="form.errors.member_id" />
            </div>

            <div class="grid gap-2">
                <Label for="role">Role</Label>
                <select
                    id="role"
                    v-model="form.role"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option v-for="role in roles" :key="role" :value="role">
                        {{ role }}
                    </option>
                </select>
                <InputError :message="form.errors.role" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password awal</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="md:col-span-2">
                <Button type="submit" :disabled="form.processing"
                    >Tambah akun</Button
                >
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Username</th>
                        <th class="p-3">Anggota</th>
                        <th class="p-3">Role</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Reset password</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="account in accounts.data"
                        :key="account.id"
                        class="border-t align-top"
                    >
                        <td class="p-3 font-mono">{{ account.username }}</td>
                        <td class="p-3">
                            {{ account.member?.name }}
                            <span class="block text-xs text-muted-foreground">
                                {{ account.member?.nrp }}
                            </span>
                        </td>
                        <td class="p-3">
                            <select
                                :value="account.roles[0]?.name"
                                class="h-8 rounded-md border bg-transparent px-2 text-sm"
                                @change="changeRole(account, $event)"
                            >
                                <option
                                    v-for="role in roles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ role }}
                                </option>
                            </select>
                        </td>
                        <td class="p-3">
                            <Button
                                size="sm"
                                :variant="
                                    account.status === 'active'
                                        ? 'outline'
                                        : 'default'
                                "
                                @click="toggleStatus(account)"
                            >
                                {{
                                    account.status === 'active'
                                        ? 'Nonaktifkan'
                                        : 'Aktifkan'
                                }}
                            </Button>
                        </td>
                        <td class="p-3">
                            <div class="flex gap-2">
                                <Input
                                    v-model="resetPasswords[account.id]"
                                    type="password"
                                    placeholder="Password baru"
                                    class="h-8"
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="resetPassword(account)"
                                >
                                    Reset
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="accounts.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="5">
                            Belum ada akun.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in accounts.links" :key="link.label">
                <Button
                    v-if="link.url"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    as-child
                >
                    <a :href="link.url" v-html="link.label" />
                </Button>
                <span
                    v-else
                    class="px-2 py-1 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
