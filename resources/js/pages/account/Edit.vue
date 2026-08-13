<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineOptions({ layout: ShopLayout })

interface Account {
    name: string
    email: string
    email_verified: boolean
    has_password: boolean
}

const props = defineProps<{ customer: Account }>()

const details = useForm({ name: props.customer.name, email: props.customer.email, _method: 'PUT' })
const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
    _method: 'PUT',
})

function saveDetails() {
    details.post(route('account.update'), { preserveScroll: true })
}

function savePassword() {
    password.post(route('account.password'), {
        preserveScroll: true,
        onSuccess: () => password.reset('current_password', 'password', 'password_confirmation'),
    })
}
</script>

<template>
    <div class="mx-auto max-w-lg space-y-8">
        <h1 class="text-2xl font-bold">Account settings</h1>

        <form class="space-y-4 rounded-lg border p-4" @submit.prevent="saveDetails">
            <h2 class="font-semibold">Your details</h2>

            <div>
                <Label for="name">Name</Label>
                <Input id="name" v-model="details.name" autocomplete="name" class="mt-1" />
                <p v-if="details.errors.name" class="mt-1 text-sm text-destructive">{{ details.errors.name }}</p>
            </div>

            <div>
                <Label for="email">Email</Label>
                <Input id="email" v-model="details.email" type="email" autocomplete="email" class="mt-1" />
                <p v-if="details.errors.email" class="mt-1 text-sm text-destructive">{{ details.errors.email }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    <span v-if="!customer.email_verified">Not verified yet. </span>
                    Changing this sends a verification link to the new address.
                </p>
            </div>

            <Button type="submit" :disabled="details.processing">Save details</Button>
        </form>

        <form class="space-y-4 rounded-lg border p-4" @submit.prevent="savePassword">
            <h2 class="font-semibold">{{ customer.has_password ? 'Change password' : 'Set a password' }}</h2>

            <div v-if="customer.has_password">
                <Label for="current_password">Current password</Label>
                <Input
                    id="current_password"
                    v-model="password.current_password"
                    type="password"
                    autocomplete="current-password"
                    class="mt-1"
                />
                <p v-if="password.errors.current_password" class="mt-1 text-sm text-destructive">
                    {{ password.errors.current_password }}
                </p>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                You bought as a guest, so there is no password to confirm against yet.
            </p>

            <div>
                <Label for="password">New password</Label>
                <Input id="password" v-model="password.password" type="password" autocomplete="new-password" class="mt-1" />
                <p v-if="password.errors.password" class="mt-1 text-sm text-destructive">{{ password.errors.password }}</p>
            </div>

            <div>
                <Label for="password_confirmation">Confirm new password</Label>
                <Input
                    id="password_confirmation"
                    v-model="password.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="mt-1"
                />
            </div>

            <Button type="submit" :disabled="password.processing">Save password</Button>
        </form>
    </div>
</template>
