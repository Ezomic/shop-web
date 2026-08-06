<script setup lang="ts">
import ShopLayout from '@/layouts/ShopLayout.vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

defineOptions({ layout: ShopLayout })

defineProps<{ reason: 'expired' | 'exhausted'; signedIn: boolean }>()
</script>

<template>
    <div class="mx-auto max-w-md text-center">
        <h1 class="mb-2 text-2xl font-bold">This download link no longer works</h1>

        <p v-if="reason === 'expired'" class="mb-6 text-muted-foreground">
            Emailed links stop working after a while. Your purchase does not: open the order in your
            account and the link there is always current.
        </p>
        <p v-else class="mb-6 text-muted-foreground">
            This link has been used its maximum number of times. Open the order in your account to
            get a fresh one.
        </p>

        <Button as-child>
            <Link :href="signedIn ? route('orders.index') : route('login')">
                {{ signedIn ? 'Go to my orders' : 'Sign in to my account' }}
            </Link>
        </Button>
    </div>
</template>
