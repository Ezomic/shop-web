<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

defineOptions({ layout: AuthLayout })

defineProps<{ status: string | null }>()

const form = useForm({})

function resend() {
    form.post(route('verification.send'))
}

function logout() {
    form.post(route('logout'))
}
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-xl font-semibold">Verify your email</h1>

        <p class="text-sm text-muted-foreground">
            We sent a verification link to your email address. Open it to confirm the address we
            deliver your download links to.
        </p>

        <p v-if="status === 'verification-link-sent'" class="text-sm font-medium text-green-600">
            A new verification link has been sent.
        </p>

        <div class="flex items-center gap-3">
            <Button type="button" :disabled="form.processing" @click="resend">Resend link</Button>
            <button type="button" class="text-sm underline" @click="logout">Log out</button>
        </div>
    </div>
</template>
