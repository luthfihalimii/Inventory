<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PasswordChangeController from '@/actions/App/Http/Controllers/Auth/PasswordChangeController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Props = {
    passwordRules: string;
};

defineProps<Props>();

defineOptions({
    layout: {
        title: 'Ganti password',
        description:
            'Akun baru wajib mengganti password sebelum memakai aplikasi',
    },
});
</script>

<template>
    <Head title="Ganti password" />

    <Form
        v-bind="PasswordChangeController.update.form()"
        :reset-on-success="[
            'password',
            'password_confirmation',
            'current_password',
        ]"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="current_password">Password saat ini</Label>
            <PasswordInput
                id="current_password"
                name="current_password"
                required
                autocomplete="current-password"
            />
            <InputError :message="errors.current_password" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Password baru</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Ulangi password baru</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            Simpan password
        </Button>
    </Form>
</template>
