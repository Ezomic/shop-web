<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineOptions({ layout: AdminLayout })

interface Totals {
    orders: number
    gross: number
    net: number
    vat: number
    average: number
}

interface BestSeller {
    name: string
    sold: number
    gross: number
}

interface QuarterRow {
    quarter: string
    country: string
    orders: number
    net: number
    vat: number
    gross: number
}

const props = defineProps<{
    period: { from: string; to: string }
    month: Totals
    year: Totals
    selection: Totals
    refunded: { orders: number; gross: number }
    bestSellers: BestSeller[]
    quarters: QuarterRow[]
}>()

const from = ref(props.period.from)
const to = ref(props.period.to)

function formatCents(cents: number) {
    return '€ ' + (cents / 100).toFixed(2).replace('.', ',')
}

function apply() {
    router.get(route('admin.reports.index'), { from: from.value, to: to.value }, { preserveState: true })
}
</script>

<template>
    <div class="space-y-8">
        <h1 class="text-2xl font-bold">Reports</h1>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border p-4">
                <div class="text-sm text-muted-foreground">This month</div>
                <div class="text-2xl font-bold">{{ formatCents(month.gross) }}</div>
                <div class="text-xs text-muted-foreground">{{ month.orders }} orders</div>
            </div>
            <div class="rounded-lg border p-4">
                <div class="text-sm text-muted-foreground">This year</div>
                <div class="text-2xl font-bold">{{ formatCents(year.gross) }}</div>
                <div class="text-xs text-muted-foreground">{{ year.orders }} orders</div>
            </div>
        </div>

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <div>
                <Label for="from">From</Label>
                <Input id="from" v-model="from" type="date" class="mt-1" />
            </div>
            <div>
                <Label for="to">To</Label>
                <Input id="to" v-model="to" type="date" class="mt-1" />
            </div>
            <Button type="submit" variant="outline">Apply</Button>
            <Button as-child>
                <a :href="route('admin.reports.export', { from, to })">Export CSV</a>
            </Button>
        </form>

        <div class="rounded-lg border p-4">
            <h2 class="mb-3 font-semibold">Selected period</h2>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between"><dt>Orders</dt><dd>{{ selection.orders }}</dd></div>
                <div class="flex justify-between"><dt>Average order</dt><dd>{{ formatCents(selection.average) }}</dd></div>
                <div class="flex justify-between"><dt>Net</dt><dd>{{ formatCents(selection.net) }}</dd></div>
                <div class="flex justify-between"><dt>VAT</dt><dd>{{ formatCents(selection.vat) }}</dd></div>
                <div class="flex justify-between font-medium"><dt>Gross</dt><dd>{{ formatCents(selection.gross) }}</dd></div>
                <div class="flex justify-between text-muted-foreground">
                    <dt>Refunded ({{ refunded.orders }})</dt>
                    <dd>- {{ formatCents(refunded.gross) }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-muted-foreground">
                Revenue counts paid orders only. Refunds are shown separately rather than quietly
                left in the total.
            </p>
        </div>

        <div class="rounded-lg border p-4">
            <h2 class="mb-3 font-semibold">Best sellers</h2>
            <p v-if="bestSellers.length === 0" class="text-sm text-muted-foreground">Nothing sold yet.</p>
            <div v-for="row in bestSellers" :key="row.name" class="flex justify-between py-1 text-sm">
                <span>{{ row.name }}</span>
                <span class="text-muted-foreground">{{ row.sold }} · {{ formatCents(row.gross) }}</span>
            </div>
        </div>

        <div class="rounded-lg border p-4">
            <h2 class="mb-1 font-semibold">Per quarter and country</h2>
            <p class="mb-3 text-xs text-muted-foreground">The shape an OSS return asks for.</p>
            <p v-if="quarters.length === 0" class="text-sm text-muted-foreground">No paid orders this year.</p>
            <table v-else class="w-full text-sm">
                <thead class="border-b">
                    <tr>
                        <th class="py-1 text-left">Quarter</th>
                        <th class="py-1 text-left">Country</th>
                        <th class="py-1 text-right">Orders</th>
                        <th class="py-1 text-right">Net</th>
                        <th class="py-1 text-right">VAT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in quarters" :key="row.quarter + row.country" class="border-b last:border-0">
                        <td class="py-1">{{ row.quarter }}</td>
                        <td class="py-1">{{ row.country }}</td>
                        <td class="py-1 text-right">{{ row.orders }}</td>
                        <td class="py-1 text-right">{{ formatCents(row.net) }}</td>
                        <td class="py-1 text-right">{{ formatCents(row.vat) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
