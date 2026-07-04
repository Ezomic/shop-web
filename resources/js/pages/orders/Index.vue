<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Link } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'

defineOptions({ layout: ShopLayout })

interface Order {
    id: number
    status: string
    total_formatted: string
    paid_at: string | null
    items_count: number
}

defineProps<{ orders: Order[] }>()
</script>

<template>
    <div>
        <h1 class="mb-6 text-2xl font-bold">My orders</h1>
        <div v-if="orders.length === 0" class="text-muted-foreground">No orders yet.</div>
        <div class="space-y-3">
            <Link
                v-for="order in orders"
                :key="order.id"
                :href="route('orders.show', order.id)"
                class="flex items-center justify-between rounded-lg border p-4 hover:bg-muted/50"
            >
                <div>
                    <div class="font-medium">Order #{{ order.id }}</div>
                    <div class="text-sm text-muted-foreground">{{ order.items_count }} item(s) · {{ order.paid_at }}</div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-bold">{{ order.total_formatted }}</span>
                    <Badge :variant="order.status === 'paid' ? 'default' : 'secondary'">{{ order.status }}</Badge>
                </div>
            </Link>
        </div>
    </div>
</template>
