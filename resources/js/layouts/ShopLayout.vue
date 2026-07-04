<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b">
            <nav class="container mx-auto flex items-center justify-between px-4 py-4">
                <Link :href="route('shop.index')" class="text-xl font-bold">Shop</Link>
                <div class="flex items-center gap-4">
                    <Link :href="route('checkout.index')" class="text-sm">Checkout</Link>
                    <Link :href="route('orders.index')" class="text-sm">My orders</Link>
                </div>
            </nav>
        </header>
        <main class="container mx-auto px-4 py-8">
            <slot />
        </main>
    </div>
</template>
