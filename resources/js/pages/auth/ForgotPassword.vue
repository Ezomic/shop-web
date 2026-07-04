<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineOptions({ layout: AuthLayout })

defineProps<{ status?: string }>()

const form = useForm({ email: '' })

function submit() {
    form.post(route('password.email'))
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <h1 class="text-xl font-semibold">Reset password</h1>
        <p v-if="status" class="text-sm text-green-600">{{ status }}</p>
        <div>
            <Label for="email">Email</Label>
            <Input id="email" v-model="form.email" type="email" autocomplete="email" class="mt-1" />
            <p v-if="form.errors.email" class="mt-1 text-sm text-destructive">{{ form.errors.email }}</p>
        </div>
        <Button type="submit" class="w-full" :disabled="form.processing">Send reset link</Button>
    </form>
</template>
