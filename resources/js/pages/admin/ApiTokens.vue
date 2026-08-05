<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface ApiToken {
    id: number
    name: string
    createdAtDiff: string | null
    lastUsedAtDiff: string | null
}

defineProps<{
    tokens: ApiToken[]
    createdToken: { name: string; plainText: string } | null
}>()

defineOptions({ layout: AdminLayout })

const form = useForm({ name: '' })
const copied = ref(false)

function create() {
    form.post(route('admin.api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    })
}

function revoke(token: ApiToken) {
    if (!window.confirm(`Revoke the token "${token.name}"? Anything using it will stop working.`)) {
        return
    }

    router.delete(route('admin.api-tokens.destroy', token.id), { preserveScroll: true })
}

async function copyToken(value: string) {
    await navigator.clipboard.writeText(value)
    copied.value = true
    window.setTimeout(() => (copied.value = false), 2000)
}
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="mb-1 text-2xl font-bold">API tokens</h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Personal access tokens authenticate requests to the API. Treat them like passwords.
        </p>

        <div v-if="createdToken" class="mb-6 rounded-lg border border-border bg-muted/40 p-4">
            <p class="font-medium">Token "{{ createdToken.name }}" created</p>
            <p class="mt-1 mb-2 text-sm text-muted-foreground">
                Copy it now. You will not be able to see it again.
            </p>
            <div class="flex items-center gap-2">
                <code class="min-w-0 flex-1 truncate rounded-md bg-background px-3 py-2 font-mono text-xs">{{
                    createdToken.plainText
                }}</code>
                <Button type="button" variant="outline" size="sm" @click="copyToken(createdToken.plainText)">
                    {{ copied ? 'Copied' : 'Copy' }}
                </Button>
            </div>
        </div>

        <form @submit.prevent="create" class="mb-6 flex items-end gap-3">
            <div class="flex-1">
                <Label for="token-name">Token name</Label>
                <Input id="token-name" v-model="form.name" class="mt-1" placeholder="e.g. CI pipeline" autocomplete="off" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
            <Button type="submit" :disabled="form.processing">Create token</Button>
        </form>

        <div class="rounded-lg border border-border">
            <template v-if="tokens.length">
                <div
                    v-for="token in tokens"
                    :key="token.id"
                    class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 last:border-b-0"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ token.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            Created {{ token.createdAtDiff }} ·
                            {{ token.lastUsedAtDiff ? `last used ${token.lastUsedAtDiff}` : 'never used' }}
                        </p>
                    </div>
                    <Button type="button" variant="ghost" size="sm" class="text-destructive" @click="revoke(token)">
                        Revoke
                    </Button>
                </div>
            </template>
            <p v-else class="p-6 text-center text-sm text-muted-foreground">
                No API tokens yet. Create one to access the API programmatically.
            </p>
        </div>
    </div>
</template>
