<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

defineOptions({ layout: AdminLayout })

interface OrderItem {
    id: number
    product_name: string
    price: number
    downloads: number
}

interface Order {
    id: number
    status: string
    customer: { name: string; email: string }
    subtotal: number
    discount: number
    total: number
    total_formatted: string
    payment_provider: string
    payment_method: string | null
    coupon_code: string | null
    paid_at: string | null
    items: OrderItem[]
}

const props = defineProps<{ order: Order }>()

function formatCents(cents: number) {
    return '€ ' + (cents / 100).toFixed(2).replace('.', ',')
}

function refund() {
    if (confirm('Refund this order?')) {
        router.post(route('admin.orders.refund', props.order.id))
    }
}
</script>

<template>
    <div class="max-w-lg">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Order #{{ order.id }}</h1>
            <Badge :variant="order.status === 'paid' ? 'default' : 'secondary'">{{ order.status }}</Badge>
        </div>

        <div class="mb-4 rounded-lg border p-4 text-sm">
            <div><strong>Customer:</strong> {{ order.customer.name }} ({{ order.customer.email }})</div>
            <div><strong>Provider:</strong> {{ order.payment_provider }} / {{ order.payment_method }}</div>
            <div v-if="order.coupon_code"><strong>Coupon:</strong> {{ order.coupon_code }}</div>
            <div v-if="order.paid_at"><strong>Paid:</strong> {{ order.paid_at }}</div>
        </div>

        <div class="mb-4 rounded-lg border p-4">
            <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between py-1 text-sm">
                <span>{{ item.product_name }}</span>
                <span class="text-muted-foreground">{{ formatCents(item.price) }} · {{ item.downloads }} downloads</span>
            </div>
            <hr class="my-2">
            <div v-if="order.discount > 0" class="flex justify-between text-sm text-green-600">
                <span>Discount</span><span>- {{ formatCents(order.discount) }}</span>
            </div>
            <div class="flex justify-between font-bold">
                <span>Total</span><span>{{ order.total_formatted }}</span>
            </div>
        </div>

        <Button v-if="order.status === 'paid'" variant="destructive" @click="refund">Refund order</Button>
    </div>
</template>
