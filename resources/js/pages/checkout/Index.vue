<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineOptions({ layout: ShopLayout })

interface Item {
    id: number
    name: string
    price: number
    price_formatted: string
}

interface Coupon {
    code: string
}

const props = defineProps<{
    items: Item[]
    subtotal: number
    discount: number
    total: number
    coupon: Coupon | null
}>()

const form = useForm({ provider: 'stripe' })
const couponCode = ref('')
const couponError = ref('')
const appliedCoupon = ref(props.coupon)
const currentDiscount = ref(props.discount)
const currentTotal = ref(props.total)

function formatCents(cents: number) {
    return '€ ' + (cents / 100).toFixed(2).replace('.', ',')
}

async function applyCoupon() {
    couponError.value = ''
    try {
        const res = await fetch(route('cart.coupon'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ code: couponCode.value }),
        })
        const data = await res.json()
        if (!res.ok) {
            couponError.value = data.error ?? 'Invalid coupon'
        } else {
            appliedCoupon.value = data.coupon
            currentDiscount.value = data.discount
            currentTotal.value = data.total
        }
    } catch {
        couponError.value = 'Something went wrong'
    }
}

function submit() {
    form.post(route('checkout.store'))
}
</script>

<template>
    <div class="mx-auto max-w-lg">
        <h1 class="mb-6 text-2xl font-bold">Checkout</h1>

        <div class="mb-6 rounded-lg border p-4">
            <div v-for="item in items" :key="item.id" class="flex justify-between py-2 text-sm">
                <span>{{ item.name }}</span>
                <span>{{ item.price_formatted }}</span>
            </div>
            <hr class="my-2">
            <div class="flex justify-between text-sm">
                <span>Subtotal</span>
                <span>{{ formatCents(subtotal) }}</span>
            </div>
            <div v-if="currentDiscount > 0" class="flex justify-between text-sm text-green-600">
                <span>Discount <span v-if="appliedCoupon">({{ appliedCoupon.code }})</span></span>
                <span>- {{ formatCents(currentDiscount) }}</span>
            </div>
            <div class="flex justify-between font-bold">
                <span>Total</span>
                <span>{{ formatCents(currentTotal) }}</span>
            </div>
        </div>

        <div class="mb-6">
            <Label for="coupon">Coupon code</Label>
            <div class="mt-1 flex gap-2">
                <Input id="coupon" v-model="couponCode" placeholder="SAVE20" :disabled="!!appliedCoupon" />
                <Button type="button" variant="outline" :disabled="!!appliedCoupon" @click="applyCoupon">Apply</Button>
            </div>
            <p v-if="couponError" class="mt-1 text-sm text-destructive">{{ couponError }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-2">
                <Label>Payment method</Label>
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3">
                    <input type="radio" v-model="form.provider" value="stripe" class="accent-primary" />
                    <span>Card (Stripe)</span>
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3">
                    <input type="radio" v-model="form.provider" value="mollie" class="accent-primary" />
                    <span>iDEAL / card (Mollie)</span>
                </label>
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Redirecting…' : 'Pay ' + formatCents(currentTotal) }}
            </Button>
        </form>
    </div>
</template>
