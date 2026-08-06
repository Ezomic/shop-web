<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface SharedProps {
    auth: { customer: { name: string; email: string; email_verified: boolean } | null }
}

const page = usePage<SharedProps>()

const needsVerification = computed(
    () => page.props.auth.customer !== null && !page.props.auth.customer.email_verified,
)

function resendVerification() {
    router.post(route('verification.send'), {}, { preserveScroll: true })
}
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
        <div v-if="needsVerification" class="border-b bg-muted/50">
            <div class="container mx-auto flex flex-wrap items-center gap-2 px-4 py-3 text-sm">
                <span>Your email address is not verified yet.</span>
                <button type="button" class="underline" @click="resendVerification">
                    Resend the verification link
                </button>
            </div>
        </div>
        <main class="container mx-auto px-4 py-8">
            <slot />
        </main>
    </div>
</template>
