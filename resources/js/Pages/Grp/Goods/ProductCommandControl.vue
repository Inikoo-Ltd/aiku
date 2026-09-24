<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 24 Sep 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, reactive, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import OrgStockDiscontinuePreviewModal from "@/Components/Warehouse/Inventory/OrgStockDiscontinuePreviewModal.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faSlidersH } from "@fal"
library.add(faSlidersH)

type Condition = "ok" | "low" | "oos" | "over" | "off" | "sell" | "hold" | "dead"

interface Cell {
    state: string
    available: number
    condition: Condition
    has_po: boolean
    stock_value: number | null
    commercial_value: number | null
}

interface Row {
    id: number
    code: string
    name: string | null
    family_code: string | null
    sales: number
    trend: number | null
    cover_weeks: number | null
    state: string
    organisations: Record<string, Cell>
    action: { org_stock_id: number; organisation: string; warehouse: string | null }
}

interface Filters {
    organisation: string | null
    family: string | null
    condition: string | null
    state: string | null
    search: string | null
    sort: string
}

const props = defineProps<{
    title: string
    pageHead: object
    built_at: string
    currency_code: string
    can_edit: boolean
    organisations: { code: string; name: string }[]
    families: string[]
    missing_rates: string[]
    kpis: {
        stock_value: number
        commercial_value: number
        out_of_stock: number
        out_of_stock_without_po: number
        low: number
        low_without_po: number
        overstock: number
        overstock_value: number
        offline: number
        offline_value: number
    }
    rows: Row[]
    pagination: { page: number; last_page: number; total: number; per_page: number }
    filters: Filters
}>()

const conditions: Record<Condition, { label: string; tooltip: string; class: string }> = {
    ok: { label: "OK", tooltip: ctrans("Covered"), class: "bg-green-50 text-green-700" },
    low: { label: "LOW", tooltip: ctrans("Runs out within two lead times"), class: "bg-amber-50 text-amber-700" },
    oos: { label: "OOS", tooltip: ctrans("Out of stock"), class: "bg-red-50 text-red-700" },
    over: { label: "OVER", tooltip: ctrans("More than 120 days of stock"), class: "bg-purple-50 text-purple-700" },
    dead: { label: "DEAD", tooltip: ctrans("Stock held, no sales"), class: "bg-gray-100 text-gray-600" },
    off: { label: "OFF", tooltip: ctrans("Stock held but no shop sells it"), class: "bg-pink-50 text-pink-700" },
    sell: { label: "SELL", tooltip: ctrans("Sell through: no more purchasing"), class: "bg-blue-50 text-blue-700" },
    hold: { label: "HOLD", tooltip: ctrans("On hold: no purchasing"), class: "bg-orange-50 text-orange-700" },
}

const statuses: Record<string, { label: string; class: string }> = {
    active: { label: ctrans("Active"), class: "bg-green-50 text-green-700" },
    suspended: { label: ctrans("Hold"), class: "bg-orange-50 text-orange-700" },
    discontinuing: { label: ctrans("Sell through"), class: "bg-blue-50 text-blue-700" },
    discontinued: { label: ctrans("Discontinued"), class: "bg-purple-50 text-purple-700" },
}

const conditionFilters = computed(() => [
    { value: "oos", label: ctrans("Out of stock") },
    { value: "oos_no_po", label: ctrans("Out of stock, no PO") },
    { value: "low", label: ctrans("Understocked") },
    { value: "low_no_po", label: ctrans("Understocked, no PO") },
    { value: "over", label: ctrans("Overstocked") },
    { value: "dead", label: ctrans("Stock held, no sales") },
    { value: "off", label: ctrans("Offline with stock") },
    { value: "sell", label: ctrans("Sell through") },
    { value: "hold", label: ctrans("On hold") },
    { value: "ok", label: ctrans("OK") },
])

const filterState = reactive({ ...props.filters })

function load(changes: Partial<Filters> = {}, page = 1): void {
    Object.assign(filterState, changes)
    const params: Record<string, string | number> = {}
    for (const [key, value] of Object.entries(filterState)) {
        if (value !== null && value !== "" && !(key === "sort" && value === "-sales")) {
            params[key] = value
        }
    }
    if (page > 1) {
        params.page = page
    }

    router.get(route("grp.goods.dashboard"), params, {
        preserveState: true,
        preserveScroll: true,
    })
}

function reset(): void {
    load({ organisation: null, family: null, condition: null, state: null, search: null, sort: "-sales" })
}

function toggleSort(key: string): void {
    load({ sort: filterState.sort === `-${key}` ? key : `-${key}` })
}

const hasFilters = computed(() =>
    ["organisation", "family", "condition", "state", "search"].some((key) => props.filters[key as keyof Filters])
)

const money = (amount: number | null, compact = false): string =>
    amount === null
        ? "-"
        : new Intl.NumberFormat("en-GB", {
              style: "currency",
              currency: props.currency_code,
              notation: compact ? "compact" : "standard",
              maximumFractionDigits: compact ? 2 : 0,
          }).format(amount)

const quantity = (value: number): string => new Intl.NumberFormat("en-GB", { maximumFractionDigits: 1 }).format(value)

const builtAt = computed(() => new Date(props.built_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }))

const kpiCards = computed(() => [
    {
        key: null,
        label: ctrans("Group stock value"),
        value: money(props.kpis.stock_value, true),
        detail: ctrans(":amount potential sales", { amount: money(props.kpis.commercial_value, true) }),
        class: "text-gray-900",
    },
    {
        key: "oos",
        label: ctrans("Out of stock"),
        value: quantity(props.kpis.out_of_stock),
        detail: ctrans(":count without PO", { count: quantity(props.kpis.out_of_stock_without_po) }),
        detailKey: "oos_no_po",
        class: "text-red-600",
    },
    {
        key: "low",
        label: ctrans("Understocked"),
        value: quantity(props.kpis.low),
        detail: ctrans(":count without PO", { count: quantity(props.kpis.low_without_po) }),
        detailKey: "low_no_po",
        class: "text-amber-600",
    },
    {
        key: "over",
        label: ctrans("Overstock value"),
        value: money(props.kpis.overstock_value, true),
        detail: ctrans(":count products", { count: quantity(props.kpis.overstock) }),
        class: "text-purple-600",
    },
    {
        key: "off",
        label: ctrans("Offline with stock"),
        value: quantity(props.kpis.offline),
        detail: ctrans(":amount stock value", { amount: money(props.kpis.offline_value, true) }),
        class: "text-pink-600",
    },
])

const newStates = reactive<Record<number, string>>({})
const modal = ref<{ row: Row; state: string } | null>(null)

const actionRoute = (row: Row, name: string): routeType => ({
    name: `grp.org.warehouses.show.inventory.org_stocks.${name}`,
    parameters: { organisation: row.action.organisation, warehouse: row.action.warehouse as string },
})

function openSet(row: Row): void {
    modal.value = { row, state: newStates[row.id] }
}

function onSetDone(): void {
    if (modal.value) {
        delete newStates[modal.value.row.id]
    }
    modal.value = null
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
        <span>{{ ctrans("Figures as of :time, refreshed every 10 minutes. Sales are the last 90 days.", { time: builtAt }) }}</span>
        <span v-if="missing_rates.length" class="text-red-600">
            {{ ctrans("No exchange rate for :organisations, their stock value is left out", { organisations: missing_rates.join(", ") }) }}
        </span>
    </div>

    <div class="mx-4 my-3 grid grid-cols-2 gap-3 md:grid-cols-5">
        <div
            v-for="card in kpiCards"
            :key="card.label"
            class="rounded-lg border p-3"
            :class="card.key && filters.condition === card.key ? 'border-indigo-400 ring-1 ring-indigo-200' : 'border-gray-200'"
        >
            <button
                type="button"
                class="block w-full text-left"
                :class="card.key ? 'cursor-pointer' : 'cursor-default'"
                :disabled="!card.key"
                @click="card.key && load({ condition: filters.condition === card.key ? null : card.key })"
            >
                <div class="text-xs text-gray-500">{{ card.label }}</div>
                <div class="text-2xl font-semibold tabular-nums" :class="card.class">{{ card.value }}</div>
            </button>
            <button
                v-if="card.detailKey"
                type="button"
                class="text-xs hover:underline"
                :class="filters.condition === card.detailKey ? 'font-semibold text-indigo-600' : 'text-gray-500'"
                @click="load({ condition: filters.condition === card.detailKey ? null : card.detailKey })"
            >
                {{ card.detail }}
            </button>
            <div v-else class="text-xs text-gray-500">{{ card.detail }}</div>
        </div>
    </div>

    <div class="mx-4 my-3 flex flex-wrap items-center gap-2">
        <input
            v-model="filterState.family"
            list="product-control-families"
            class="w-36 rounded-md border-gray-300 text-sm"
            :placeholder="ctrans('Family')"
            @change="load()"
        />
        <datalist id="product-control-families">
            <option v-for="family in families" :key="family" :value="family" />
        </datalist>
        <select v-model="filterState.organisation" class="rounded-md border-gray-300 text-sm" @change="load()">
            <option :value="null">{{ ctrans("All organisations") }}</option>
            <option v-for="organisation in organisations" :key="organisation.code" :value="organisation.code">{{ organisation.name }}</option>
        </select>
        <select v-model="filterState.condition" class="rounded-md border-gray-300 text-sm" @change="load()">
            <option :value="null">{{ ctrans("Any stock condition") }}</option>
            <option v-for="condition in conditionFilters" :key="condition.value" :value="condition.value">{{ condition.label }}</option>
        </select>
        <select v-model="filterState.state" class="rounded-md border-gray-300 text-sm" @change="load()">
            <option :value="null">{{ ctrans("Any status") }}</option>
            <option v-for="(status, value) in statuses" :key="value" :value="value">{{ status.label }}</option>
        </select>
        <input
            v-model="filterState.search"
            type="search"
            class="min-w-64 flex-1 rounded-md border-gray-300 text-sm"
            :placeholder="ctrans('Search code, description or family')"
            @keyup.enter="load()"
            @search="load()"
        />
        <button v-if="hasFilters" type="button" class="text-sm text-indigo-600 hover:underline" @click="reset">{{ ctrans("Reset") }}</button>
    </div>

    <div class="mx-4 mb-6 overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2">
                        <button type="button" class="uppercase" @click="toggleSort('code')">{{ ctrans("Code — description") }}</button>
                    </th>
                    <th class="px-3 py-2 text-right">
                        <button type="button" class="uppercase" @click="toggleSort('sales')">{{ ctrans("Sales 90d") }}</button>
                    </th>
                    <th v-for="organisation in organisations" :key="organisation.code" class="px-3 py-2" :title="organisation.name">
                        {{ organisation.code }}
                    </th>
                    <th class="px-3 py-2 text-right">
                        <button type="button" class="uppercase" @click="toggleSort('cover')">{{ ctrans("Cover") }}</button>
                    </th>
                    <th class="px-3 py-2">{{ ctrans("Group product status") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
                    <td class="max-w-md truncate px-3 py-2" :title="row.family_code ? `${ctrans('Family')}: ${row.family_code}` : undefined">
                        <span class="font-semibold">{{ row.code }}</span>
                        <span class="text-gray-600"> — {{ row.name }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                        {{ money(row.sales) }}
                        <span v-if="row.trend !== null" class="ml-1 text-xs" :class="row.trend >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ row.trend >= 0 ? "↑" : "↓" }}{{ Math.abs(row.trend) }}%
                        </span>
                    </td>
                    <td v-for="organisation in organisations" :key="organisation.code" class="whitespace-nowrap px-3 py-2 tabular-nums">
                        <template v-if="row.organisations[organisation.code]">
                            {{ quantity(row.organisations[organisation.code].available) }}
                            <span
                                v-tooltip="conditions[row.organisations[organisation.code].condition].tooltip"
                                class="ml-1 rounded px-1.5 py-0.5 text-xs font-semibold"
                                :class="conditions[row.organisations[organisation.code].condition].class"
                            >
                                {{ conditions[row.organisations[organisation.code].condition].label }}
                            </span>
                            <span v-if="row.organisations[organisation.code].has_po" v-tooltip="ctrans('Purchase order on the way')" class="ml-1 text-xs text-indigo-600">PO</span>
                        </template>
                        <span v-else class="text-gray-300">—</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                        <span v-if="row.cover_weeks !== null">{{ row.cover_weeks }}w</span>
                        <span v-else v-tooltip="ctrans('No sales to measure cover')" class="text-gray-400">{{ ctrans("no sales") }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="w-24 rounded px-1.5 py-0.5 text-center text-xs font-semibold" :class="statuses[row.state]?.class">
                                {{ statuses[row.state]?.label ?? row.state }}
                            </span>
                            <template v-if="can_edit && row.action.warehouse">
                                <select v-model="newStates[row.id]" class="rounded-md border-gray-300 py-1 text-xs">
                                    <option :value="undefined">{{ ctrans("Select new") }}</option>
                                    <option v-for="(status, value) in statuses" :key="value" :value="value" :disabled="value === row.state">{{ status.label }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-semibold text-white disabled:opacity-40"
                                    :disabled="!newStates[row.id] || newStates[row.id] === row.state"
                                    @click="openSet(row)"
                                >
                                    {{ ctrans("Set") }}
                                </button>
                            </template>
                        </div>
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="organisations.length + 4" class="py-8 text-center text-gray-500">{{ ctrans("No products match these filters.") }}</td>
                </tr>
            </tbody>
        </table>
        <div class="flex items-center justify-between border-t border-gray-200 px-3 py-2 text-xs text-gray-500">
            <span>{{ ctrans(":total products", { total: quantity(pagination.total) }) }}</span>
            <div class="flex items-center gap-3">
                <button type="button" class="hover:underline disabled:opacity-40" :disabled="pagination.page <= 1" @click="load({}, pagination.page - 1)">
                    {{ ctrans("Previous") }}
                </button>
                <span>{{ ctrans("Page :page of :pages", { page: pagination.page, pages: pagination.last_page }) }}</span>
                <button type="button" class="hover:underline disabled:opacity-40" :disabled="pagination.page >= pagination.last_page" @click="load({}, pagination.page + 1)">
                    {{ ctrans("Next") }}
                </button>
            </div>
        </div>
    </div>

    <OrgStockDiscontinuePreviewModal
        v-if="modal"
        :isOpen="!!modal"
        :orgStockIds="[modal.row.action.org_stock_id]"
        :initialState="modal.state"
        :previewRoute="actionRoute(modal.row, 'discontinue_preview')"
        :discontinueRoute="actionRoute(modal.row, 'discontinue')"
        @onClose="modal = null"
        @onDone="onSetDone"
    />
</template>
