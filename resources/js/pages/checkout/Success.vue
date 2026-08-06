<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

defineOptions({ layout: ShopLayout })

defineProps<{ paid: boolean }>()
</script>

<template>
    <div class="mx-auto max-w-md text-center">
        <template v-if="paid">
            <div class="mb-4 text-5xl">✓</div>
            <h1 class="mb-2 text-2xl font-bold">Thank you for your purchase!</h1>
            <p class="mb-6 text-muted-foreground">
                You will receive a confirmation email with your download links shortly.
            </p>
        </template>
        <template v-else>
            <div class="mb-4 text-5xl">⏳</div>
            <h1 class="mb-2 text-2xl font-bold">We are still confirming your payment</h1>
            <p class="mb-6 text-muted-foreground">
                This can take a moment. Your download links appear on your order as soon as the
                payment is confirmed.
            </p>
        </template>
        <Button as-child>
            <Link :href="route('orders.index')">View my orders</Link>
        </Button>
    </div>
</template>
