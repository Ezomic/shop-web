<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineOptions({ layout: AuthLayout })

const form = useForm({ email: '', password: '', remember: false })

function submit() {
    form.post(route('admin.login'), { onFinish: () => form.reset('password') })
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <h1 class="text-xl font-semibold">Admin login</h1>

        <div>
            <Label for="email">Email</Label>
            <Input id="email" v-model="form.email" type="email" autocomplete="email" class="mt-1" />
            <p v-if="form.errors.email" class="mt-1 text-sm text-destructive">{{ form.errors.email }}</p>
        </div>
        <div>
            <Label for="password">Password</Label>
            <Input id="password" v-model="form.password" type="password" autocomplete="current-password" class="mt-1" />
        </div>
        <Button type="submit" class="w-full" :disabled="form.processing">Sign in</Button>
    </form>
</template>
