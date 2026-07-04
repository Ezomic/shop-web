<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Link, router } from '@inertiajs/vue3'
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

const props = defineProps<{ products: Product[] }>()

function addToCart(product: Product) {
    router.post(route('cart.add'), { product_id: product.id })
}

function removeFromCart(product: Product) {
    router.post(route('cart.remove'), { product_id: product.id })
}
</script>

<template>
    <div>
        <h1 class="mb-8 text-3xl font-bold">Scripts</h1>
        <div v-if="products.length === 0" class="text-muted-foreground">No products yet.</div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="product in products" :key="product.id" class="rounded-lg border p-6">
                <img
                    v-if="product.preview_url"
                    :src="product.preview_url"
                    :alt="product.name"
                    class="mb-4 w-full rounded object-cover"
                    style="height: 160px;"
                />
                <h2 class="mb-2 text-lg font-semibold">
                    <Link :href="route('shop.show', product.slug)">{{ product.name }}</Link>
                </h2>
                <p class="mb-4 text-sm text-muted-foreground line-clamp-3">{{ product.description }}</p>
                <div class="flex items-center justify-between">
                    <span class="font-bold">{{ product.price_formatted }}</span>
                    <Badge v-if="product.in_cart" variant="secondary">In cart</Badge>
                    <Button v-else size="sm" @click="addToCart(product)">Add to cart</Button>
                </div>
            </div>
        </div>
    </div>
</template>
