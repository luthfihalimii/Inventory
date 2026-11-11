<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    sessions: {
        id: string;
        device: string;
        ip: string | null;
        last_active_at: string;
        is_current: boolean;
    }[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});

function revokeSession(id: string): void {
    if (
        !window.confirm('Cabut sesi ini? Perangkat tersebut harus login ulang.')
    ) {
        return;
    }

    router.delete(`/settings/sessions/${id}`, { preserveScroll: true });
}

function revokeOthers(): void {
    if (
        !window.confirm('Keluarkan semua perangkat lain? Sesi ini tetap aktif.')
    ) {
        return;
    }

    router.delete('/settings/sessions', { preserveScroll: true });
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Update password"
            description="Ensure your account is using a long, random password to stay secure"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Current password</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Current password"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="New password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Save
                </Button>
            </div>
        </Form>

        <div class="space-y-4 border-t pt-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    variant="small"
                    title="Perangkat & sesi"
                    description="Semua perangkat yang masih login ke akun Anda. Cabut yang tidak Anda kenali."
                />
                <Button
                    v-if="props.sessions.some((session) => !session.is_current)"
                    variant="outline"
                    @click="revokeOthers"
                >
                    Keluarkan semua perangkat lain
                </Button>
            </div>

            <ul class="grid gap-2">
                <li
                    v-for="session in props.sessions"
                    :key="session.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-sm"
                >
                    <div>
                        <p class="font-medium">
                            {{ session.device }}
                            <Badge
                                v-if="session.is_current"
                                variant="secondary"
                                class="ml-2"
                            >
                                Sesi ini
                            </Badge>
                        </p>
                        <p class="text-muted-foreground">
                            {{ session.ip ?? 'IP tidak tercatat' }} · terakhir
                            aktif
                            {{ formatDate(session.last_active_at) }}
                        </p>
                    </div>
                    <Button
                        v-if="!session.is_current"
                        size="sm"
                        variant="destructive"
                        @click="revokeSession(session.id)"
                    >
                        Cabut
                    </Button>
                </li>
                <li
                    v-if="props.sessions.length === 0"
                    class="rounded-lg border p-3 text-sm text-muted-foreground"
                >
                    Tidak ada sesi aktif yang tercatat.
                </li>
            </ul>
        </div>
    </div>
</template>
