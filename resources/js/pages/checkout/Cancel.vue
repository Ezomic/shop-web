<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

defineOptions({ layout: ShopLayout })
</script>

<template>
    <div class="mx-auto max-w-md text-center">
        <div class="mb-4 text-5xl">✕</div>
        <h1 class="mb-2 text-2xl font-bold">Payment cancelled</h1>
        <p class="mb-6 text-muted-foreground">Your order was not completed. You can try again.</p>
        <Button as-child>
            <Link :href="route('checkout.index')">Back to checkout</Link>
        </Button>
    </div>
</template>
