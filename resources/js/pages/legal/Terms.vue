<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'

defineOptions({ layout: ShopLayout })

interface Supplier {
    name: string | null
    address: string | null
    postcode_city: string | null
    country: string | null
    vat_number: string | null
    coc_number: string | null
    email: string | null
}

defineProps<{ supplier: Supplier; withdrawalConsentText: string }>()
</script>

<template>
    <article class="prose mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-bold">Terms and conditions</h1>

        <section>
            <h2 class="mb-1 font-semibold">Who you are buying from</h2>
            <p class="text-sm text-muted-foreground">
                {{ supplier.name }}<br />
                <template v-if="supplier.address">{{ supplier.address }}<br /></template>
                <template v-if="supplier.postcode_city">{{ supplier.postcode_city }}<br /></template>
                <template v-if="supplier.country">{{ supplier.country }}<br /></template>
                <template v-if="supplier.vat_number">VAT number: {{ supplier.vat_number }}<br /></template>
                <template v-if="supplier.coc_number">Chamber of Commerce: {{ supplier.coc_number }}<br /></template>
                <template v-if="supplier.email">{{ supplier.email }}</template>
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">What you are buying</h2>
            <p class="text-sm text-muted-foreground">
                Digital scripts, delivered as downloadable files. Nothing is shipped. Prices shown
                include VAT.
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">Delivery</h2>
            <p class="text-sm text-muted-foreground">
                Download links are emailed as soon as the payment is confirmed, and are also
                available on your order page. Emailed links expire after a while; the link on your
                order page is always current, and you can ask for a fresh one at any time.
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">Right of withdrawal</h2>
            <p class="text-sm text-muted-foreground">
                You normally have 14 days to withdraw from an online purchase. For downloadable
                content that right ends once the download starts, and only if you have agreed to
                it. That is what you confirm at checkout:
            </p>
            <p class="mt-2 rounded border p-3 text-sm">{{ withdrawalConsentText }}</p>
            <p class="mt-2 text-sm text-muted-foreground">
                Until you download, the 14 day right still stands. Email us and we will refund you.
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">Payment</h2>
            <p class="text-sm text-muted-foreground">
                Payments are handled by Stripe and Mollie. We never see or store your card details.
            </p>
        </section>

        <section>
            <h2 class="mb-1 font-semibold">Problems</h2>
            <p class="text-sm text-muted-foreground">
                If a file is wrong, broken or will not download, email us and we will fix it or
                refund you.
            </p>
        </section>
    </article>
</template>
