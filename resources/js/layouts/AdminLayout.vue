<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'

function logout() {
    router.post(route('admin.logout'))
}
</script>

<template>
    <div class="flex min-h-screen">
        <aside class="w-56 border-r bg-muted/40 p-4">
            <div class="mb-6 text-lg font-bold">Admin</div>
            <nav class="space-y-1 text-sm">
                <Link :href="route('admin.products.index')" class="block rounded px-3 py-2 hover:bg-muted">Products</Link>
                <Link :href="route('admin.orders.index')" class="block rounded px-3 py-2 hover:bg-muted">Orders</Link>
                <Link :href="route('admin.coupons.index')" class="block rounded px-3 py-2 hover:bg-muted">Coupons</Link>
                <Link :href="route('admin.settings.edit')" class="block rounded px-3 py-2 hover:bg-muted">Settings</Link>
            </nav>
            <button
                class="mt-8 w-full rounded px-3 py-2 text-left text-sm text-destructive hover:bg-muted"
                @click="logout"
            >
                Logout
            </button>
        </aside>
        <main class="flex-1 p-8">
            <slot />
        </main>
    </div>
</template>
