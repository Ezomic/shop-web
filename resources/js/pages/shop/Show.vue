<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

defineOptions({ layout: ShopLayout })

interface Product {
    id: number
    slug: string
    name: string
    description: string
    price: number
    price_formatted: string
    preview_url: string | null
    in_cart: boolean
}

const props = defineProps<{ product: Product }>()

function addToCart() {
    router.post(route('cart.add'), { product_id: props.product.id })
}

function goToCheckout() {
    router.visit(route('checkout.index'))
}
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <img
            v-if="product.preview_url"
            :src="product.preview_url"
            :alt="product.name"
            class="mb-8 w-full rounded-lg object-cover"
            style="max-height: 320px;"
        />
        <h1 class="mb-4 text-3xl font-bold">{{ product.name }}</h1>
        <p class="mb-8 whitespace-pre-line text-muted-foreground">{{ product.description }}</p>
        <div class="flex items-center gap-4">
            <span class="text-2xl font-bold">{{ product.price_formatted }}</span>
            <Badge v-if="product.in_cart" variant="secondary">In cart</Badge>
            <Button v-else @click="addToCart">Add to cart</Button>
            <Button v-if="product.in_cart" variant="outline" @click="goToCheckout">Checkout</Button>
        </div>
    </div>
</template>
