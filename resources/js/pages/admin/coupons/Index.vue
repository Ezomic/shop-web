<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

defineOptions({ layout: AdminLayout })

interface Coupon {
    id: number
    code: string
    type: string
    amount: number
    uses_count: number
    max_uses: number | null
    expires_at: string | null
    active: boolean
}

defineProps<{ coupons: Coupon[] }>()

function destroy(id: number) {
    if (confirm('Delete this coupon?')) {
        router.delete(route('admin.coupons.destroy', id))
    }
}
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Coupons</h1>
            <Button as-child>
                <Link :href="route('admin.coupons.create')">+ New coupon</Link>
            </Button>
        </div>
        <div class="rounded-lg border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="p-3 text-left">Code</th>
                        <th class="p-3 text-left">Discount</th>
                        <th class="p-3 text-left">Uses</th>
                        <th class="p-3 text-left">Expires</th>
                        <th class="p-3 text-left">Active</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="coupon in coupons" :key="coupon.id" class="border-b last:border-0">
                        <td class="p-3 font-mono">{{ coupon.code }}</td>
                        <td class="p-3">
                            {{ coupon.type === 'percent' ? coupon.amount + '%' : '€ ' + (coupon.amount / 100).toFixed(2) }}
                        </td>
                        <td class="p-3">{{ coupon.uses_count }} / {{ coupon.max_uses ?? '∞' }}</td>
                        <td class="p-3">{{ coupon.expires_at ?? '—' }}</td>
                        <td class="p-3">
                            <Badge :variant="coupon.active ? 'default' : 'secondary'">{{ coupon.active ? 'Yes' : 'No' }}</Badge>
                        </td>
                        <td class="p-3 text-right">
                            <Link :href="route('admin.coupons.edit', coupon.id)" class="mr-3 underline">Edit</Link>
                            <button class="text-destructive underline" @click="destroy(coupon.id)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
