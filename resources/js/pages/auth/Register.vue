<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineOptions({ layout: AuthLayout })

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

function submit() {
    form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <h1 class="text-xl font-semibold">Create account</h1>

        <div>
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" type="text" autocomplete="name" class="mt-1" />
            <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</p>
        </div>
        <div>
            <Label for="email">Email</Label>
            <Input id="email" v-model="form.email" type="email" autocomplete="email" class="mt-1" />
            <p v-if="form.errors.email" class="mt-1 text-sm text-destructive">{{ form.errors.email }}</p>
        </div>
        <div>
            <Label for="password">Password</Label>
            <Input id="password" v-model="form.password" type="password" autocomplete="new-password" class="mt-1" />
            <p v-if="form.errors.password" class="mt-1 text-sm text-destructive">{{ form.errors.password }}</p>
        </div>
        <div>
            <Label for="password_confirmation">Confirm password</Label>
            <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="mt-1" />
        </div>
        <Button type="submit" class="w-full" :disabled="form.processing">Register</Button>
        <p class="text-center text-sm">
            Already have an account? <a :href="route('login')" class="underline">Sign in</a>
        </p>
    </form>
</template>
