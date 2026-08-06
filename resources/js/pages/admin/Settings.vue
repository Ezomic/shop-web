<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineOptions({ layout: AdminLayout })

defineProps<{
    settings: {
        stripe_key: string | null
        stripe_secret_set: boolean
        stripe_webhook_secret_set: boolean
        mollie_key_set: boolean
    }
}>()

const form = useForm({
    stripe_secret: '',
    stripe_webhook_secret: '',
    mollie_key: '',
    _method: 'PUT',
})

function submit() {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('stripe_secret', 'stripe_webhook_secret', 'mollie_key'),
    })
}
</script>

<template>
    <div class="max-w-md">
        <h1 class="mb-6 text-2xl font-bold">Settings</h1>

        <p class="mb-6 text-sm text-muted-foreground">
            Stored encrypted in the database. Leave a field empty to keep the current value.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <Label>Stripe secret key</Label>
                <Input v-model="form.stripe_secret" type="password" class="mt-1" placeholder="sk_…" />
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ settings.stripe_secret_set ? 'Configured' : 'Not configured' }}
                </p>
            </div>
            <div>
                <Label>Stripe webhook secret</Label>
                <Input v-model="form.stripe_webhook_secret" type="password" class="mt-1" placeholder="whsec_…" />
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ settings.stripe_webhook_secret_set ? 'Configured' : 'Not configured' }}
                </p>
            </div>
            <div>
                <Label>Mollie API key</Label>
                <Input v-model="form.mollie_key" type="password" class="mt-1" placeholder="test_…" />
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ settings.mollie_key_set ? 'Configured' : 'Not configured' }}
                </p>
            </div>
            <Button type="submit" :disabled="form.processing">Save settings</Button>
        </form>
    </div>
</template>
