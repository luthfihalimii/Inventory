<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Lupa password',
        description:
            'Masukkan email akun. Kami kirim tautan untuk membuat password baru.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Lupa password" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        action="/forgot-password"
        method="post"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@contoh.com"
            />
            <InputError :message="errors.email" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Kirim tautan reset
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            <a href="/login" class="underline">Kembali ke halaman masuk</a>
        </p>
    </Form>
</template>
