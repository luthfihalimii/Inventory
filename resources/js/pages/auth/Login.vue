<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Masuk ke akun',
        description:
            'Masukkan NRP atau username dan password untuk melanjutkan.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Masuk" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="username" class="text-[13px] font-medium"
                    >NRP / Username</Label
                >
                <Input
                    id="username"
                    class="h-11 rounded-xl"
                    type="text"
                    name="username"
                    required
                    v-focus
                    autocomplete="username"
                    placeholder="NRP atau username"
                />
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-[13px] font-medium"
                    >Password</Label
                >
                <PasswordInput
                    id="password"
                    class="h-11 rounded-xl"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex min-h-11 items-center justify-between">
                <Label for="remember" class="flex min-h-11 items-center gap-3">
                    <Checkbox
                        id="remember"
                        name="remember"
                        class="relative before:absolute before:-inset-3.5 before:content-['']"
                    />
                    <span>Ingat saya</span>
                </Label>
                <a
                    href="/forgot-password"
                    class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline"
                >
                    Lupa password?
                </a>
            </div>

            <Button
                type="submit"
                class="mt-2 h-11 w-full rounded-xl"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Masuk
            </Button>
        </div>
    </Form>

    <p class="mt-6 text-center text-xs text-muted-foreground">
        Akun anggota dibuat oleh WM.
    </p>
</template>
