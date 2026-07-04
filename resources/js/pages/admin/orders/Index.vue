<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Link } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'

defineOptions({ layout: AdminLayout })

interface Order {
    id: number
    customer_name: string
    customer_email: string
    status: string
    total_formatted: string
    payment_provider: string
    paid_at: string | null
}

interface PaginatedOrders {
    data: Order[]
    current_page: number
    last_page: number
}

defineProps<{ orders: PaginatedOrders }>()
</script>

<template>
    <div>
        <h1 class="mb-6 text-2xl font-bold">Orders</h1>
        <div class="rounded-lg border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="p-3 text-left">#</th>
                        <th class="p-3 text-left">Customer</th>
                        <th class="p-3 text-left">Total</th>
                        <th class="p-3 text-left">Provider</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-left">Date</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in orders.data" :key="order.id" class="border-b last:border-0">
                        <td class="p-3">{{ order.id }}</td>
                        <td class="p-3">
                            <div>{{ order.customer_name }}</div>
                            <div class="text-muted-foreground">{{ order.customer_email }}</div>
                        </td>
                        <td class="p-3">{{ order.total_formatted }}</td>
                        <td class="p-3">{{ order.payment_provider }}</td>
                        <td class="p-3">
                            <Badge :variant="order.status === 'paid' ? 'default' : 'secondary'">{{ order.status }}</Badge>
                        </td>
                        <td class="p-3">{{ order.paid_at }}</td>
                        <td class="p-3 text-right">
                            <Link :href="route('admin.orders.show', order.id)" class="underline">View</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
