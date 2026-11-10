<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Password baru',
        description: 'Buat password baru untuk akun Anda.',
    },
});

defineProps<{
    email: string;
    token: string;
}>();
</script>

<template>
    <Head title="Password baru" />

    <Form
        action="/reset-password"
        method="post"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input type="hidden" name="token" :value="token" />
        <input type="hidden" name="email" :value="email" />

        <div class="grid gap-2">
            <p class="text-sm text-muted-foreground">{{ email }}</p>

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
            <Spinner v-if="processing" />
            Simpan password
        </Button>
    </Form>
</template>
