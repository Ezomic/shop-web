<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-background">
        <div class="w-full max-w-md space-y-6 rounded-lg border p-8">
            <div class="text-center">
                <Link :href="route('shop.index')" class="text-xl font-bold">Shop</Link>
            </div>
            <slot />
        </div>
    </div>
</template>
