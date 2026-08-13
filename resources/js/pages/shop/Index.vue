<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'

defineOptions({ layout: ShopLayout })

interface Product {
    id: number
    slug: string
    name: string
    description: string
    price: number
    price_formatted: string
    cover_url: string | null
    has_sample: boolean
    in_cart: boolean
}

interface Filters {
    q: string
    sort: string
}

const props = defineProps<{ products: Product[]; filters: Filters }>()

const search = ref(props.filters.q)
const sort = ref(props.filters.sort)

// State lives in the URL so a filtered list can be linked and survives a reload.
function apply() {
    router.get(
        route('shop.index'),
        { q: search.value || undefined, sort: sort.value === 'default' ? undefined : sort.value },
        { preserveState: true, replace: true },
    )
}

function clear() {
    search.value = ''
    sort.value = 'default'
    apply()
}

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
        <form class="mb-6 flex flex-wrap items-center gap-3" @submit.prevent="apply">
            <Input
                v-model="search"
                type="search"
                placeholder="Search scripts…"
                class="max-w-xs"
                @keyup.enter="apply"
            />
            <select v-model="sort" class="h-9 rounded-md border bg-background px-3 text-sm" @change="apply">
                <option value="default">Featured</option>
                <option value="newest">Newest</option>
                <option value="price_asc">Price: low to high</option>
                <option value="price_desc">Price: high to low</option>
            </select>
            <Button type="submit" variant="outline">Search</Button>
            <Button
                v-if="filters.q || filters.sort !== 'default'"
                type="button"
                variant="ghost"
                @click="clear"
            >
                Clear
            </Button>
        </form>

        <div v-if="products.length === 0 && filters.q" class="text-muted-foreground">
            Nothing matches “{{ filters.q }}”. Try a different word, or
            <button type="button" class="underline" @click="clear">show everything</button>.
        </div>
        <div v-else-if="products.length === 0" class="text-muted-foreground">No products yet.</div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="product in products" :key="product.id" class="rounded-lg border p-6">
                <img
                    v-if="product.cover_url"
                    :src="product.cover_url"
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
