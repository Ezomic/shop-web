<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

defineOptions({ layout: AdminLayout })

const form = useForm({
    name_en: '',
    name_nl: '',
    description_en: '',
    description_nl: '',
    price: 0,
    status: 'draft',
    preview_url: '',
    file: null as File | null,
})

function onFile(e: Event) {
    form.file = (e.target as HTMLInputElement).files?.[0] ?? null
}

function submit() {
    form.post(route('admin.products.store'), { forceFormData: true })
}
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="mb-6 text-2xl font-bold">New product</h1>
        <form @submit.prevent="submit" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <Label>Name (EN)</Label>
                    <Input v-model="form.name_en" class="mt-1" />
                    <p v-if="form.errors.name_en" class="mt-1 text-sm text-destructive">{{ form.errors.name_en }}</p>
                </div>
                <div>
                    <Label>Name (NL)</Label>
                    <Input v-model="form.name_nl" class="mt-1" />
                    <p v-if="form.errors.name_nl" class="mt-1 text-sm text-destructive">{{ form.errors.name_nl }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <Label>Description (EN)</Label>
                    <Textarea v-model="form.description_en" class="mt-1" rows="5" />
                </div>
                <div>
                    <Label>Description (NL)</Label>
                    <Textarea v-model="form.description_nl" class="mt-1" rows="5" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <Label>Price (cents)</Label>
                    <Input v-model.number="form.price" type="number" min="1" class="mt-1" />
                    <p v-if="form.errors.price" class="mt-1 text-sm text-destructive">{{ form.errors.price }}</p>
                </div>
                <div>
                    <Label>Status</Label>
                    <Select v-model="form.status">
                        <SelectTrigger class="mt-1"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div>
                <Label>Preview image URL</Label>
                <Input v-model="form.preview_url" type="url" class="mt-1" placeholder="https://…" />
            </div>
            <div>
                <Label>Product file</Label>
                <Input type="file" class="mt-1" @change="onFile" />
                <p v-if="form.errors.file" class="mt-1 text-sm text-destructive">{{ form.errors.file }}</p>
            </div>
            <Button type="submit" :disabled="form.processing">Create product</Button>
        </form>
    </div>
</template>
