<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

defineOptions({ layout: ShopLayout })

interface OrderItem {
    id: number
    product_name: string
    price: number
    download_url: string | null
}

interface Order {
    id: number
    status: string
    subtotal: number
    discount: number
    total: number
    total_formatted: string
    paid_at: string | null
    items: OrderItem[]
}

defineProps<{ order: Order }>()

function formatCents(cents: number) {
    return '€ ' + (cents / 100).toFixed(2).replace('.', ',')
}
</script>

<template>
    <div class="mx-auto max-w-lg">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Order #{{ order.id }}</h1>
            <Badge :variant="order.status === 'paid' ? 'default' : 'secondary'">{{ order.status }}</Badge>
        </div>

        <div class="mb-6 rounded-lg border p-4 space-y-3">
            <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between">
                <span class="font-medium">{{ item.product_name }}</span>
                <div class="flex items-center gap-3">
                    <span class="text-sm">{{ formatCents(item.price) }}</span>
                    <Button v-if="item.download_url" size="sm" as-child>
                        <a :href="item.download_url">Download</a>
                    </Button>
                </div>
            </div>
            <hr>
            <div v-if="order.discount > 0" class="flex justify-between text-sm text-green-600">
                <span>Discount</span>
                <span>- {{ formatCents(order.discount) }}</span>
            </div>
            <div class="flex justify-between font-bold">
                <span>Total</span>
                <span>{{ order.total_formatted }}</span>
            </div>
        </div>

        <p v-if="order.paid_at" class="text-sm text-muted-foreground">Paid on {{ order.paid_at }}</p>
    </div>
</template>
