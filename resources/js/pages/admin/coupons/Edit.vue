<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

defineOptions({ layout: AdminLayout })

interface Coupon {
    id: number
    code: string
    type: string
    amount: number
    max_uses: number | null
    expires_at: string | null
    active: boolean
}

const props = defineProps<{ coupon: Coupon }>()

const form = useForm({
    code: props.coupon.code,
    type: props.coupon.type,
    amount: props.coupon.amount,
    max_uses: props.coupon.max_uses?.toString() ?? '',
    expires_at: props.coupon.expires_at ?? '',
    active: props.coupon.active,
    _method: 'PUT',
})

function submit() {
    form.post(route('admin.coupons.update', props.coupon.id))
}
</script>

<template>
    <div class="max-w-md">
        <h1 class="mb-6 text-2xl font-bold">Edit coupon</h1>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <Label>Code</Label>
                <Input v-model="form.code" class="mt-1 uppercase" />
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
            <div class="flex items-center gap-2">
                <input id="active" v-model="form.active" type="checkbox" class="accent-primary" />
                <Label for="active">Active</Label>
            </div>
            <Button type="submit" :disabled="form.processing">Save changes</Button>
        </form>
    </div>
</template>
