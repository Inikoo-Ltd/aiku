<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 09 Aug 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { routeType } from '@/types/route'

import { library } from '@fortawesome/fontawesome-svg-core'
import { faRadar } from '@fal'
library.add(faRadar)

interface AspoRow {
    slug: string
    reference: string
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
    agent_code: string | null
    agent_slug: string | null
    supplier_code: string | null
    deposit_amount: number
    deposit_paid_at: string
    currency_code: string
    days_since: number
}

interface PoRow {
    slug: string
    reference: string
    organisation_slug: string
    organisation_name: string
    agent_code: string | null
    agent_slug: string | null
    submitted_at: string
    days_waiting: number
}

interface ScorecardRow {
    id: number
    code: string
    slug: string
    open_aspos: number
    oldest_stalled_days: number | null
    total_aspos: number
    delivered_aspos: number
    delivered_ratio: number | null
    deposits_outstanding: { amount: number, currency: string, has_more: boolean } | null
}

const props = defineProps<{
    title: string
    pageHead: {}
    stalled_aspos: { rows: AspoRow[], total: number, buckets: { '60_180': number, '180_365': number, over_1y: number } }
    deposits_at_risk: { rows: DepositRow[], total: number, exposure: { currency_code: string, total: number }[] }
    pos_without_action: { rows: PoRow[], total: number }
    agent_scorecard: { rows: ScorecardRow[] }
}>()

function aspoRoute(slug: string): routeType {
    return { name: 'grp.supply-chain.agent_supplier_purchase_orders.show', parameters: { agentSupplierPurchaseOrder: slug } }
}

function agentRoute(slug: string): routeType {
    return { name: 'grp.supply-chain.agents.show', parameters: { agent: slug } }
}

function poRoute(row: PoRow): routeType {
    return { name: 'grp.org.procurement.purchase_orders.show', parameters: { organisation: row.organisation_slug, purchaseOrder: row.slug } }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 my-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ __('Stalled ASPOs') }}</div>
            <div class="text-2xl font-semibold">{{ stalled_aspos.total }}</div>
        </div>
        <div v-for="exp in deposits_at_risk.exposure" :key="exp.currency_code" class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ __('Deposit exposure') }} ({{ exp.currency_code }})</div>
            <div class="text-2xl font-semibold">{{ exp.total }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ __('POs without agent action') }}</div>
            <div class="text-2xl font-semibold">{{ pos_without_action.total }}</div>
        </div>
    </div>

    <div class="mx-4 my-6">
        <h2 class="mb-2 text-base font-semibold">{{ __('Stalled agent buys') }}</h2>
        <div class="mb-2 flex gap-4 text-xs text-gray-500">
            <span>{{ __('60-180d') }}: {{ stalled_aspos.buckets['60_180'] }}</span>
            <span>{{ __('180-365d') }}: {{ stalled_aspos.buckets['180_365'] }}</span>
            <span class="font-semibold text-red-600">{{ __('>1y') }}: {{ stalled_aspos.buckets.over_1y }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500">
                        <th class="py-1 pr-3">{{ __('Reference') }}</th>
                        <th class="py-1 pr-3">{{ __('Agent') }}</th>
                        <th class="py-1 pr-3">{{ __('Supplier') }}</th>
                        <th class="py-1 pr-3">{{ __('Date') }}</th>
                        <th class="py-1 pr-3">{{ __('Estimated') }}</th>
                        <th class="py-1 pr-3">{{ __('Days stalled') }}</th>
                        <th class="py-1 pr-3">{{ __('Cost') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in stalled_aspos.rows" :key="row.slug" :class="row.days_stalled > 365 ? 'bg-red-50 text-red-700' : ''">
                        <td class="py-1 pr-3"><Link :href="route(aspoRoute(row.slug).name, aspoRoute(row.slug).parameters)" class="text-blue-600">{{ row.reference }}</Link></td>
                        <td class="py-1 pr-3">{{ row.agent_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ row.supplier_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ row.date }}</td>
                        <td class="py-1 pr-3">{{ row.estimated_received_at ?? '-' }}</td>
                        <td class="py-1 pr-3 font-semibold">{{ row.days_stalled }}</td>
                        <td class="py-1 pr-3">{{ row.cost_total }} {{ row.currency_code }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mx-4 my-6">
        <h2 class="mb-2 text-base font-semibold">{{ __('Deposits at risk') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500">
                        <th class="py-1 pr-3">{{ __('Reference') }}</th>
                        <th class="py-1 pr-3">{{ __('Agent') }}</th>
                        <th class="py-1 pr-3">{{ __('Supplier') }}</th>
                        <th class="py-1 pr-3">{{ __('Deposit') }}</th>
                        <th class="py-1 pr-3">{{ __('Paid at') }}</th>
                        <th class="py-1 pr-3">{{ __('Days since') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in deposits_at_risk.rows" :key="row.slug">
                        <td class="py-1 pr-3"><Link :href="route(aspoRoute(row.slug).name, aspoRoute(row.slug).parameters)" class="text-blue-600">{{ row.reference }}</Link></td>
                        <td class="py-1 pr-3">{{ row.agent_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ row.supplier_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ row.deposit_amount }} {{ row.currency_code }}</td>
                        <td class="py-1 pr-3">{{ row.deposit_paid_at }}</td>
                        <td class="py-1 pr-3">{{ row.days_since }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mx-4 my-6">
        <h2 class="mb-2 text-base font-semibold">{{ __('Purchase orders without agent action') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500">
                        <th class="py-1 pr-3">{{ __('Reference') }}</th>
                        <th class="py-1 pr-3">{{ __('Organisation') }}</th>
                        <th class="py-1 pr-3">{{ __('Agent') }}</th>
                        <th class="py-1 pr-3">{{ __('Submitted') }}</th>
                        <th class="py-1 pr-3">{{ __('Days waiting') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in pos_without_action.rows" :key="row.slug">
                        <td class="py-1 pr-3"><Link :href="route(poRoute(row).name, poRoute(row).parameters)" class="text-blue-600">{{ row.reference }}</Link></td>
                        <td class="py-1 pr-3">{{ row.organisation_name }}</td>
                        <td class="py-1 pr-3">{{ row.agent_code ?? '-' }}</td>
                        <td class="py-1 pr-3">{{ row.submitted_at }}</td>
                        <td class="py-1 pr-3">{{ row.days_waiting }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mx-4 my-6">
        <h2 class="mb-2 text-base font-semibold">{{ __('Agent scorecard') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500">
                        <th class="py-1 pr-3">{{ __('Agent') }}</th>
                        <th class="py-1 pr-3">{{ __('Open ASPOs') }}</th>
                        <th class="py-1 pr-3">{{ __('Oldest stalled') }}</th>
                        <th class="py-1 pr-3">{{ __('Deposits outstanding') }}</th>
                        <th class="py-1 pr-3">{{ __('Delivered / total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in agent_scorecard.rows" :key="row.id">
                        <td class="py-1 pr-3"><Link :href="route(agentRoute(row.slug).name, agentRoute(row.slug).parameters)" class="text-blue-600">{{ row.code }}</Link></td>
                        <td class="py-1 pr-3">{{ row.open_aspos }}</td>
                        <td class="py-1 pr-3">{{ row.oldest_stalled_days ?? '-' }}</td>
                        <td class="py-1 pr-3">
                            <template v-if="row.deposits_outstanding">
                                {{ row.deposits_outstanding.amount }} {{ row.deposits_outstanding.currency }}<span v-if="row.deposits_outstanding.has_more">+</span>
                            </template>
                            <template v-else>-</template>
                        </td>
                        <td class="py-1 pr-3">{{ row.delivered_aspos }} / {{ row.total_aspos }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
