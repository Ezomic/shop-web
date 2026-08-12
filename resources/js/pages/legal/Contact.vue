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

defineProps<{ supplier: Supplier }>()
</script>

<template>
    <article class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-bold">Contact</h1>

        <p class="text-sm text-muted-foreground">
            Questions about an order, a file that will not open, or anything else: email us and we
            will get back to you.
        </p>

        <div class="rounded-lg border p-4 text-sm">
            <div class="font-medium">{{ supplier.name }}</div>
            <div v-if="supplier.address">{{ supplier.address }}</div>
            <div v-if="supplier.postcode_city">{{ supplier.postcode_city }}</div>
            <div v-if="supplier.country">{{ supplier.country }}</div>
            <div v-if="supplier.email" class="mt-2">
                <a :href="'mailto:' + supplier.email" class="underline">{{ supplier.email }}</a>
            </div>
            <div v-if="supplier.vat_number" class="mt-2 text-muted-foreground">
                VAT number: {{ supplier.vat_number }}
            </div>
            <div v-if="supplier.coc_number" class="text-muted-foreground">
                Chamber of Commerce: {{ supplier.coc_number }}
            </div>
        </div>
    </article>
</template>
