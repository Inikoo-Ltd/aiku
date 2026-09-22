<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { ctrans } from '@/Composables/useTrans'
import PurchaseOrderJourneyRibbon from '@/Components/SupplyChain/PurchaseOrderJourneyRibbon.vue'

interface Ribbon {
    slug: string
    reference: string
    organisation_code: string
    parent_type: 'agent' | 'supplier'
    parent_code: string
    parent_name: string
    country_code: string
    buyer_name: string
    amount: number
    currency_code: string
    created_at: string
    current_stage: string
    status: 'on_track' | 'at_risk' | 'overdue'
    days_overdue: number | null
    route: { name: string, parameters: object }
    segments: {
        key: string
        label: string
        done_at: string | null
        target_at: string | null
        state: 'done' | 'on_track' | 'at_risk' | 'overdue' | 'future'
        days_overdue: number | null
    }[]
}

const props = defineProps<{
    title: string
    pageHead: {}
    filters: {
        organisations: { slug: string, code: string }[]
        agents: { slug: string, code: string }[]
        suppliers: { slug: string, code: string }[]
        countries: { code: string, name: string }[]
        buyers: { id: number, name: string }[]
        stages: { key: string, label: string }[]
    }
    active: {
        organisation: string | null
        agent: string | null
        supplier: string | null
        country: string | null
        buyer: number | null
        stage: string | null
        status: string | null
        problems_only: boolean
    }
    summary: { total: number, on_track: number, at_risk: number, overdue: number }
    ribbons: Ribbon[]
}>()

const filterState = reactive({
    organisation: props.active.organisation,
    agent: props.active.agent,
    supplier: props.active.supplier,
    country: props.active.country,
    buyer: props.active.buyer,
    stage: props.active.stage,
    status: props.active.status,
    problems_only: props.active.problems_only
})

function applyFilters(): void {
    const params: Record<string, string | number | boolean> = {}
    for (const [key, value] of Object.entries(filterState)) {
        if (value !== null && value !== '' && value !== false) {
            params[key] = value
        }
    }

    router.get(route('grp.supply-chain.po_journey.dashboard'), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['ribbons', 'summary', 'active']
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 my-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ ctrans('Total') }}</div>
            <div class="text-2xl font-semibold">{{ summary.total }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ ctrans('On track') }}</div>
            <div class="text-2xl font-semibold text-blue-600">{{ summary.on_track }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ ctrans('At risk') }}</div>
            <div class="text-2xl font-semibold text-amber-500">{{ summary.at_risk }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">{{ ctrans('Overdue') }}</div>
            <div class="text-2xl font-semibold text-red-600">{{ summary.overdue }}</div>
        </div>
    </div>

    <div class="mx-4 my-4 flex flex-wrap items-center gap-3">
        <select v-model="filterState.organisation" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
            <option :value="null">{{ ctrans('Organisation') }}</option>
            <option v-for="organisation in filters.organisations" :key="organisation.slug" :value="organisation.slug">{{ organisation.code }}</option>
        </select>
        <select v-model="filterState.agent" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
            <option :value="null">{{ ctrans('Agent') }}</option>
            <option v-for="agent in filters.agents" :key="agent.slug" :value="agent.slug">{{ agent.code }}</option>
        </select>
        <select v-model="filterState.supplier" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
            <option :value="null">{{ ctrans('Supplier') }}</option>
            <option v-for="supplier in filters.suppliers" :key="supplier.slug" :value="supplier.slug">{{ supplier.code }}</option>
        </select>
        <select v-model="filterState.country" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
            <option :value="null">{{ ctrans('Country') }}</option>
            <option v-for="country in filters.countries" :key="country.code" :value="country.code">{{ country.name }}</option>
        </select>
        <select v-model="filterState.stage" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
            <option :value="null">{{ ctrans('Stage') }}</option>
            <option v-for="stage in filters.stages" :key="stage.key" :value="stage.key">{{ stage.label }}</option>
        </select>
        <select v-model="filterState.status" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
            <option :value="null">{{ ctrans('Status') }}</option>
            <option value="on_track">{{ ctrans('On track') }}</option>
            <option value="at_risk">{{ ctrans('At risk') }}</option>
            <option value="overdue">{{ ctrans('Overdue') }}</option>
        </select>
        <label class="flex items-center gap-1.5 text-sm">
            <input v-model="filterState.problems_only" type="checkbox" class="rounded border-gray-300" @change="applyFilters">
            {{ ctrans('Problems only') }}
        </label>
    </div>

    <div class="mx-4 my-4 divide-y divide-gray-100">
        <PurchaseOrderJourneyRibbon v-for="ribbon in ribbons" :key="ribbon.slug" :ribbon="ribbon" />
        <div v-if="!ribbons.length" class="py-8 text-center text-sm text-gray-500">
            {{ ctrans('No purchase orders match these filters.') }}
        </div>
    </div>
</template>
