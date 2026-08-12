<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { router, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

defineOptions({ layout: AdminLayout })

interface ProductFile {
    id: number
    original_filename: string
    size: number
}

interface Product {
    id: number
    name_en: string
    name_nl: string
    description_en: string
    description_nl: string
    price: number
    status: string
    cover_url: string | null
    sample_filename: string | null
    files: ProductFile[]
}

const props = defineProps<{ product: Product }>()

const form = useForm({
    name_en: props.product.name_en,
    name_nl: props.product.name_nl,
    description_en: props.product.description_en,
    description_nl: props.product.description_nl,
    price: props.product.price,
    status: props.product.status,
    cover: null as File | null,
    sample: null as File | null,
    files: [] as File[],
    _method: 'PUT',
})

function onSample(e: Event) {
    form.sample = (e.target as HTMLInputElement).files?.[0] ?? null
}

function removeSample() {
    if (confirm('Remove the sample?')) {
        router.delete(route('admin.products.sample.destroy', props.product.id), { preserveScroll: true })
    }
}

function onCover(e: Event) {
    form.cover = (e.target as HTMLInputElement).files?.[0] ?? null
}

function formatSize(bytes: number) {
    return bytes > 1048576
        ? (bytes / 1048576).toFixed(1) + ' MB'
        : Math.round(bytes / 1024) + ' KB'
}

function removeFile(file: ProductFile) {
    if (confirm('Remove ' + file.original_filename + '?')) {
        router.delete(
            route('admin.products.files.destroy', { product: props.product.id, file: file.id }),
            { preserveScroll: true },
        )
    }
}

function onFile(e: Event) {
    form.files = Array.from((e.target as HTMLInputElement).files ?? [])
}

function submit() {
    form.post(route('admin.products.update', props.product.id), { forceFormData: true })
}
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="mb-6 text-2xl font-bold">Edit product</h1>
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
                <Label>Cover image</Label>
                <img
                    v-if="product.cover_url"
                    :src="product.cover_url"
                    alt=""
                    class="mb-2 rounded border"
                    style="max-height: 160px;"
                />
                <Input type="file" accept="image/*" class="mt-1" @change="onCover" />
                <p class="mt-1 text-xs text-muted-foreground">Uploading replaces the current cover.</p>
                <p v-if="form.errors.cover" class="mt-1 text-sm text-destructive">{{ form.errors.cover }}</p>
            </div>
            <div>
                <Label>Sample</Label>
                <div v-if="product.sample_filename" class="mb-2 flex items-center justify-between rounded border px-3 py-2 text-sm">
                    <span>{{ product.sample_filename }}</span>
                    <button type="button" class="text-xs underline" @click="removeSample">Remove</button>
                </div>
                <p v-else class="mb-2 text-sm text-muted-foreground">No sample yet.</p>
                <Input type="file" class="mt-1" @change="onSample" />
                <p v-if="form.errors.sample" class="mt-1 text-sm text-destructive">{{ form.errors.sample }}</p>
            </div>
            <div>
                <Label>Product files</Label>

                <ul v-if="product.files.length" class="mb-2 space-y-1">
                    <li
                        v-for="file in product.files"
                        :key="file.id"
                        class="flex items-center justify-between rounded border px-3 py-2 text-sm"
                    >
                        <span>{{ file.original_filename }}</span>
                        <span class="flex items-center gap-3 text-muted-foreground">
                            <span class="text-xs">{{ formatSize(file.size) }}</span>
                            <button type="button" class="text-xs underline" @click="removeFile(file)">
                                Remove
                            </button>
                        </span>
                    </li>
                </ul>
                <p v-else class="mb-2 text-sm text-muted-foreground">No files attached yet.</p>

                <Input type="file" multiple class="mt-1" @change="onFile" />
                <p class="mt-1 text-xs text-muted-foreground">
                    Uploading adds to the list. Removing a file that has already been bought keeps
                    it working for those buyers.
                </p>
                <p v-if="form.errors.files" class="mt-1 text-sm text-destructive">{{ form.errors.files }}</p>
            </div>
            <Button type="submit" :disabled="form.processing">Save changes</Button>
        </form>
    </div>
</template>
