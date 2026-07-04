<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

defineOptions({ layout: AdminLayout })

const form = useForm({
    code: '',
    type: 'percent',
    amount: 10,
    max_uses: '',
    expires_at: '',
    active: true,
})

function submit() {
    form.post(route('admin.coupons.store'))
}
</script>

<template>
    <div class="max-w-md">
        <h1 class="mb-6 text-2xl font-bold">New coupon</h1>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <Label>Code</Label>
                <Input v-model="form.code" class="mt-1 uppercase" placeholder="SAVE20" />
                <p v-if="form.errors.code" class="mt-1 text-sm text-destructive">{{ form.errors.code }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <Label>Type</Label>
                    <Select v-model="form.type">
                        <SelectTrigger class="mt-1"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="percent">Percentage</SelectItem>
                            <SelectItem value="fixed">Fixed (cents)</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label>Amount</Label>
                    <Input v-model.number="form.amount" type="number" min="1" class="mt-1" />
                    <p v-if="form.errors.amount" class="mt-1 text-sm text-destructive">{{ form.errors.amount }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <Label>Max uses</Label>
                    <Input v-model="form.max_uses" type="number" class="mt-1" placeholder="Unlimited" />
                </div>
                <div>
                    <Label>Expires at</Label>
                    <Input v-model="form.expires_at" type="date" class="mt-1" />
                </div>
            </div>
            <Button type="submit" :disabled="form.processing">Create coupon</Button>
        </form>
    </div>
</template>
