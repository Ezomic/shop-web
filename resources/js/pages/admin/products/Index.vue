<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

defineOptions({ layout: AdminLayout })

interface Product {
    id: number
    name: string
    slug: string
    price_formatted: string
    status: string
    sort_order: number
}

defineProps<{ products: Product[] }>()

function destroy(id: number) {
    if (confirm('Delete this product?')) {
        router.delete(route('admin.products.destroy', id))
    }
}
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Products</h1>
            <Button as-child>
                <Link :href="route('admin.products.create')">+ New product</Link>
            </Button>
        </div>
        <div class="rounded-lg border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="p-3 text-left">Name</th>
                        <th class="p-3 text-left">Price</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in products" :key="product.id" class="border-b last:border-0">
                        <td class="p-3">{{ product.name }}</td>
                        <td class="p-3">{{ product.price_formatted }}</td>
                        <td class="p-3">
                            <Badge :variant="product.status === 'published' ? 'default' : 'secondary'">
                                {{ product.status }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <Link :href="route('admin.products.edit', product.id)" class="mr-3 underline">Edit</Link>
                            <button class="text-destructive underline" @click="destroy(product.id)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
