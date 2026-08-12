<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'

defineOptions({ layout: ShopLayout })

const props = defineProps<{
    paid: boolean
    claimable: boolean
    orderId: number | null
    email: string | null
}>()

const claim = useForm({ password: '', password_confirmation: '' })

function submitClaim() {
    if (props.orderId !== null) {
        claim.post(route('checkout.claim', props.orderId))
    }
}
</script>

<template>
    <div class="mx-auto max-w-md text-center">
        <template v-if="paid">
            <div class="mb-4 text-5xl">✓</div>
            <h1 class="mb-2 text-2xl font-bold">Thank you for your purchase!</h1>
            <p class="mb-6 text-muted-foreground">
                You will receive a confirmation email with your download links shortly.
            </p>
        </template>
        <template v-else>
            <div class="mb-4 text-5xl">⏳</div>
            <h1 class="mb-2 text-2xl font-bold">We are still confirming your payment</h1>
            <p class="mb-6 text-muted-foreground">
                This can take a moment. Your download links appear on your order as soon as the
                payment is confirmed.
            </p>
        </template>
        <div v-if="claimable" class="mb-6 rounded-lg border p-4 text-left">
            <h2 class="mb-1 font-semibold">Keep your downloads in one place</h2>
            <p class="mb-3 text-sm text-muted-foreground">
                Set a password for {{ email }} and this order joins an account you can come back to.
                Entirely optional, your links work either way.
            </p>
            <form @submit.prevent="submitClaim" class="space-y-3">
                <div>
                    <Label for="password">Password</Label>
                    <Input id="password" v-model="claim.password" type="password" autocomplete="new-password" class="mt-1" />
                    <p v-if="claim.errors.password" class="mt-1 text-sm text-destructive">{{ claim.errors.password }}</p>
                </div>
                <div>
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input id="password_confirmation" v-model="claim.password_confirmation" type="password" autocomplete="new-password" class="mt-1" />
                </div>
                <Button type="submit" :disabled="claim.processing">Create my account</Button>
            </form>
        </div>

        <Button v-if="!claimable" as-child>
            <Link :href="route('orders.index')">View my orders</Link>
        </Button>
    </div>
</template>
