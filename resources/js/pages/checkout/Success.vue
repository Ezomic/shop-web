<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

defineOptions({ layout: ShopLayout })
</script>

<template>
    <div class="mx-auto max-w-md text-center">
        <div class="mb-4 text-5xl">✓</div>
        <h1 class="mb-2 text-2xl font-bold">Thank you for your purchase!</h1>
        <p class="mb-6 text-muted-foreground">
            You will receive a confirmation email with your download links shortly.
        </p>
        <Button as-child>
            <Link :href="route('orders.index')">View my orders</Link>
        </Button>
    </div>
</template>
