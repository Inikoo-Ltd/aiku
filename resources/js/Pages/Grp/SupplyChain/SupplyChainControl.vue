<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 09 Aug 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'
import { routeType } from '@/types/route'

import { library } from '@fortawesome/fontawesome-svg-core'
import { faRadar } from '@fal'
library.add(faRadar)

interface PurchaseOrderRow {
    slug: string
    reference: string
    organisation_slug: string
    organisation_code: string
    agent_code: string | null
    agent_slug: string | null
    supplier_code: string | null
    date: string
    estimated_received_at: string | null
    cost_total: number
    currency_code: string
    days_stalled: number
}

interface DepositRow {
    slug: string
    reference: string
    organisation_slug: string
    organisation_code: string
    agent_code: string | null
    agent_slug: string | null
    supplier_code: string | null
    deposit_amount: number
    deposit_paid_at: string
    currency_code: string
    days_since: number
}

interface ScorecardRow {
    id: number
    code: string
    slug: string
    open_purchase_orders: number
    oldest_stalled_days: number | null
    total_purchase_orders: number
    delivered_purchase_orders: number
    delivered_ratio: number | null
    deposits_outstanding: { amount: number, currency: string, has_more: boolean } | null
}

const props = defineProps<{
    title: string
    pageHead: {}
    stalled_purchase_orders: { rows: PurchaseOrderRow[], total: number, buckets: { '60_180': number, '180_365': number, over_1y: number } }
    deposits_at_risk: { rows: DepositRow[], total: number, exposure: { currency_code: string, total: number }[] }
    agent_scorecard: { rows: ScorecardRow[] }
}>()

function purchaseOrderRoute(row: { organisation_slug: string, slug: string }): routeType {
    return { name: 'grp.org.procurement.purchase_orders.show', parameters: { organisation: row.organisation_slug, purchaseOrder: row.slug } }
}

function agentRoute(slug: string): routeType {
    return { name: 'grp.supply-chain.agents.show', parameters: { agent: slug } }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 my-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ ctrans('Stalled agent orders') }}</div>
            <div class="text-2xl font-semibold">{{ stalled_purchase_orders.total }}</div>
        </div>
        <div v-for="exp in deposits_at_risk.exposure" :key="exp.currency_code" class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ ctrans('Deposit exposure') }} ({{ exp.currency_code }})</div>
            <div class="text-2xl font-semibold">{{ exp.total }}</div>
        </div>
    </div>

    <div class="mx-4 my-6">
        <h2 class="mb-2 text-base font-semibold">{{ ctrans('Stalled agent buys') }}</h2>
        <div class="mb-2 flex gap-4 text-xs text-gray-500">
            <span>{{ ctrans('60-180d') }}: {{ stalled_purchase_orders.buckets['60_180'] }}</span>
            <span>{{ ctrans('180-365d') }}: {{ stalled_purchase_orders.buckets['180_365'] }}</span>
            <span class="font-semibold text-red-600">{{ ctrans('>1y') }}: {{ stalled_purchase_orders.buckets.over_1y }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500">
                        <th class="py-1 pr-3">{{ ctrans('Reference') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Agent') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Supplier') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Date') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Estimated') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Days stalled') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Cost') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in stalled_purchase_orders.rows" :key="row.slug" :class="row.days_stalled > 365 ? 'bg-red-50 text-red-700' : ''">
                        <td class="py-1 pr-3"><Link :href="route(purchaseOrderRoute(row).name, purchaseOrderRoute(row).parameters)" class="font-medium text-gray-900 hover:underline">{{ row.reference }}</Link></td>
                        <td class="py-1 pr-3">{{ row.agent_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ row.supplier_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ useFormatTime(row.date) }}</td>
                        <td class="py-1 pr-3">{{ row.estimated_received_at ? useFormatTime(row.estimated_received_at) : '-' }}</td>
                        <td class="py-1 pr-3 font-semibold">{{ row.days_stalled }}</td>
                        <td class="py-1 pr-3">{{ row.cost_total }} {{ row.currency_code }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mx-4 my-6">
        <h2 class="mb-2 text-base font-semibold">{{ ctrans('Deposits at risk') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500">
                        <th class="py-1 pr-3">{{ ctrans('Reference') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Agent') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Supplier') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Deposit') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Paid at') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Days since') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in deposits_at_risk.rows" :key="row.slug">
                        <td class="py-1 pr-3"><Link :href="route(purchaseOrderRoute(row).name, purchaseOrderRoute(row).parameters)" class="font-medium text-gray-900 hover:underline">{{ row.reference }}</Link></td>
                        <td class="py-1 pr-3">{{ row.agent_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ row.supplier_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ row.deposit_amount }} {{ row.currency_code }}</td>
                        <td class="py-1 pr-3">{{ useFormatTime(row.deposit_paid_at) }}</td>
                        <td class="py-1 pr-3">{{ row.days_since }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mx-4 my-6">
        <h2 class="mb-2 text-base font-semibold">{{ ctrans('Agent scorecard') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500">
                        <th class="py-1 pr-3">{{ ctrans('Agent') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Open orders') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Oldest stalled') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Deposits outstanding') }}</th>
                        <th class="py-1 pr-3">{{ ctrans('Delivered / total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in agent_scorecard.rows" :key="row.id">
                        <td class="py-1 pr-3"><Link :href="route(agentRoute(row.slug).name, agentRoute(row.slug).parameters)" class="font-medium text-gray-900 hover:underline">{{ row.code }}</Link></td>
                        <td class="py-1 pr-3">{{ row.open_purchase_orders }}</td>
                        <td class="py-1 pr-3">{{ row.oldest_stalled_days ?? '-' }}</td>
                        <td class="py-1 pr-3">
                            <template v-if="row.deposits_outstanding">
                                {{ row.deposits_outstanding.amount }} {{ row.deposits_outstanding.currency }}<span v-if="row.deposits_outstanding.has_more">+</span>
                            </template>
                            <template v-else>-</template>
                        </td>
                        <td class="py-1 pr-3">{{ row.delivered_purchase_orders }} / {{ row.total_purchase_orders }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
