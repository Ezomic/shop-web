<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

defineOptions({ layout: AdminLayout })

interface DownloadRow {
    id: number
    filename: string | null
    count: number
}

interface OrderItem {
    id: number
    product_name: string
    price: number
    downloads: DownloadRow[]
}

interface Order {
    id: number
    status: string
    customer: { name: string; email: string }
    subtotal: number
    discount: number
    total: number
    total_formatted: string
    invoice_number: string | null
    country: string | null
    country_source: string | null
    vat_rate: number
    vat_amount: number
    net_total: number
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

function resend() {
    router.post(route('admin.orders.resend', props.order.id), {}, { preserveScroll: true })
}

function reissue(downloadId: number) {
    if (confirm('Regenerate this link? The link already sent to the customer stops working.')) {
        router.post(
            route('admin.orders.downloads.reissue', { order: props.order.id, download: downloadId }),
            {},
            { preserveScroll: true },
        )
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
            <div v-if="order.country">
                <strong>Country:</strong> {{ order.country }}
                <span class="text-muted-foreground">({{ order.country_source }})</span>
            </div>
        </div>

        <div class="mb-4 rounded-lg border p-4">
            <div v-for="item in order.items" :key="item.id" class="py-1 text-sm">
                <div class="flex items-center justify-between">
                    <span>{{ item.product_name }}</span>
                    <span class="text-muted-foreground">{{ formatCents(item.price) }}</span>
                </div>
                <div
                    v-for="download in item.downloads"
                    :key="download.id"
                    class="flex items-center justify-between pl-4 text-xs text-muted-foreground"
                >
                    <span>{{ download.filename ?? 'file' }} · {{ download.count }} downloads</span>
                    <button type="button" class="underline" @click="reissue(download.id)">
                        Regenerate link
                    </button>
                </div>
            </div>
            <hr class="my-2">
            <div v-if="order.discount > 0" class="flex justify-between text-sm text-green-600">
                <span>Discount</span><span>- {{ formatCents(order.discount) }}</span>
            </div>
            <div class="flex justify-between font-bold">
                <span>Total</span><span>{{ order.total_formatted }}</span>
            </div>
            <div v-if="order.vat_rate > 0" class="flex justify-between text-xs text-muted-foreground">
                <span>Net {{ formatCents(order.net_total) }} · {{ order.vat_rate }}% VAT</span>
                <span>{{ formatCents(order.vat_amount) }}</span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button v-if="order.invoice_number" variant="outline" as-child>
                <a :href="route('admin.orders.invoice', order.id)">Invoice {{ order.invoice_number }}</a>
            </Button>
            <Button v-if="order.status === 'paid'" variant="outline" @click="resend">
                Resend order email
            </Button>
            <Button v-if="order.status === 'paid'" variant="destructive" @click="refund">
                Refund order
            </Button>
        </div>
    </div>
</template>
