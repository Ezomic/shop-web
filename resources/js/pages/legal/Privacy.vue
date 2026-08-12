<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'

defineOptions({ layout: ShopLayout })

interface Supplier {
    name: string | null
    email: string | null
}

defineProps<{ supplier: Supplier }>()
</script>

<template>
    <article class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-bold">Privacy policy</h1>

        <section>
            <h2 class="mb-1 font-semibold">What we keep</h2>
            <p class="text-sm text-muted-foreground">
                Your name and email address, your orders, and the country and IP address recorded
                with each order. The country and IP are kept because VAT rules require us to be able
                to show where a sale belonged.
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">What we do not keep</h2>
            <p class="text-sm text-muted-foreground">
                Card details. Payments go through Stripe and Mollie, and the card never reaches us.
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">How long</h2>
            <p class="text-sm text-muted-foreground">
                Orders and invoices are kept for as long as tax rules require. Your account details
                are kept until you ask us to delete them.
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">Who else sees it</h2>
            <p class="text-sm text-muted-foreground">
                Stripe and Mollie, to take the payment. Our email provider, to send your download
                links. Nobody else, and we do not sell anything to anyone.
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">Your rights</h2>
            <p class="text-sm text-muted-foreground">
                You can ask for a copy of what we hold, ask us to correct it, or ask us to delete
                it. Email
                <span v-if="supplier.email">{{ supplier.email }}</span>
                <span v-else>us</span>
                and we will sort it out.
            </p>
        </section>
    </article>
</template>
