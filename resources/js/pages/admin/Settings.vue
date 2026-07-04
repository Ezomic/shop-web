<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    settings: {
        stripe_key: string | null
        mollie_key: string | null
    }
}>()

const form = useForm({
    stripe_secret: '',
    stripe_webhook_secret: '',
    mollie_key: '',
    _method: 'PUT',
})

function submit() {
    form.post(route('admin.settings.update'))
}
</script>

<template>
    <div class="max-w-md">
        <h1 class="mb-6 text-2xl font-bold">Settings</h1>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <Label>Stripe secret key</Label>
                <Input v-model="form.stripe_secret" type="password" class="mt-1" placeholder="sk_…" />
            </div>
            <div>
                <Label>Stripe webhook secret</Label>
                <Input v-model="form.stripe_webhook_secret" type="password" class="mt-1" placeholder="whsec_…" />
            </div>
            <div>
                <Label>Mollie API key</Label>
                <Input v-model="form.mollie_key" type="password" class="mt-1" placeholder="test_…" />
            </div>
            <Button type="submit" :disabled="form.processing">Save settings</Button>
        </form>
    </div>
</template>
