<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 24 Sep 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import OrgStockDiscontinuePreviewModal from "@/Components/Warehouse/Inventory/OrgStockDiscontinuePreviewModal.vue"
import ProductDetailDrawer from "@/Components/Goods/ProductDetailDrawer.vue"
import GoodsViewToggle from "@/Components/Goods/GoodsViewToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSlidersH, faWarehouse, faBan, faExclamationTriangle, faBoxes, faPlug, faDownload } from "@fal"
library.add(faSlidersH, faWarehouse, faBan, faExclamationTriangle, faBoxes, faPlug, faDownload)

type Condition = "ok" | "low" | "oos" | "ns" | "over" | "off" | "sell" | "hold" | "ret" | "dead"
type Period = "30d" | "90d" | "quarter" | "year"

interface Cell {
    org_stock_id: number
    state: string
    on_hand: number
    available: number
    allocated: number
    inbound: number
    next_expected_at: string | null
    has_po: boolean
    condition: Condition
    stock_value: number | null
    commercial_value: number | null
}

interface Row {
    id: number
    slug: string
    code: string
    name: string | null
    family_code: string | null
    sales: number
    trend: number | null
    cover_weeks: number | null
    state: string
    organisations: Record<string, Cell>
    action: { org_stock_id: number; organisation: string; warehouse: string | null } | null
}

interface OrganisationMeta {
    code: string
    name: string
    slug: string
    warehouse_slug: string | null
}

interface RateInfo {
    organisation: string
    currency: string
    rate: number | null
    date: string | null
    source: string | null
}

interface Filters {
    organisation: string | null
    family: string | null
    condition: string | null
    state: string | null
    search: string | null
    sort: string
    period: Period
}

const props = defineProps<{
    title: string
    pageHead: object
    built_at: string
    currency_code: string
    can_edit: boolean
    organisations: OrganisationMeta[]
    families: string[]
    missing_rates: string[]
    rates: RateInfo[]
    period: Period
    periods: Period[]
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
    editable_organisations: string[]
    can_change_group: boolean
}>()

const conditions: Record<Condition, { label: string; tooltip: string; class: string }> = {
    ok: { label: ctrans("OK"), tooltip: ctrans("Covered"), class: "bg-green-50 text-green-700" },
    low: { label: ctrans("LOW"), tooltip: ctrans("Runs out within two lead times"), class: "bg-amber-50 text-amber-700" },
    oos: { label: ctrans("OOS"), tooltip: ctrans("Out of stock"), class: "bg-red-50 text-red-700" },
    ns: { label: ctrans("NS"), tooltip: ctrans("Not stocked here: no stock, no sales in 180 days, nothing on order"), class: "bg-gray-100 text-gray-500" },
    over: { label: ctrans("OVER"), tooltip: ctrans("More than 120 days of stock"), class: "bg-purple-50 text-purple-700" },
    dead: { label: ctrans("DEAD"), tooltip: ctrans("Stock held, no sales"), class: "bg-gray-100 text-gray-600" },
    off: { label: ctrans("OFF"), tooltip: ctrans("Stock held but no shop sells it"), class: "bg-pink-50 text-pink-700" },
    sell: { label: ctrans("SELL"), tooltip: ctrans("Sell through: no more purchasing"), class: "bg-blue-50 text-blue-700" },
    hold: { label: ctrans("HOLD"), tooltip: ctrans("On hold: no purchasing"), class: "bg-orange-50 text-orange-700" },
    ret: { label: ctrans("RET"), tooltip: ctrans("Retired in this organisation"), class: "bg-gray-100 text-gray-400" },
}

const statuses: Record<string, { label: string; pill: string; class: string }> = {
    active: { label: ctrans("Active"), pill: ctrans("ACTIVE"), class: "bg-green-50 text-green-700" },
    suspended: { label: ctrans("Hold"), pill: ctrans("HOLD"), class: "bg-orange-50 text-orange-700" },
    discontinuing: { label: ctrans("Discontinued"), pill: ctrans("DISC."), class: "bg-blue-50 text-blue-700" },
    discontinued: { label: ctrans("Retired"), pill: ctrans("RETIRED"), class: "bg-purple-50 text-purple-700" },
}

const periodLabels: Record<Period, string> = {
    "30d": ctrans("Last 30 days"),
    "90d": ctrans("Last 90 days"),
    quarter: ctrans("This quarter"),
    year: ctrans("Last 12 months"),
}

const conditionFilters = computed(() => [
    { value: "oos", label: ctrans("Out of stock") },
    { value: "oos_no_po", label: ctrans("Out of stock, no PO") },
    { value: "ns", label: ctrans("Not stocked") },
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

watch(
    () => props.filters,
    (newFilters) => {
        Object.assign(filterState, newFilters)
    },
    { deep: true }
)

const loading = ref(false)
let stopStart: (() => void) | undefined
let stopFinish: (() => void) | undefined

onMounted(() => {
    stopStart = router.on("start", () => {
        loading.value = true
    })
    stopFinish = router.on("finish", () => {
        loading.value = false
    })
})

onUnmounted(() => {
    stopStart?.()
    stopFinish?.()
})

function load(changes: Partial<Filters> = {}, page = 1): void {
    Object.assign(filterState, changes)
    const params: Record<string, string | number> = {}
    for (const [key, value] of Object.entries(filterState)) {
        if (value !== null && value !== "" && !(key === "sort" && value === "-sales") && !(key === "period" && value === "90d")) {
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
    load({ organisation: null, family: null, condition: null, state: null, search: null, sort: "-sales", period: "90d" })
}

function toggleSort(key: string): void {
    load({ sort: filterState.sort === `-${key}` ? key : `-${key}` })
}

function sortArrow(key: string): string {
    if (filterState.sort === key) return "▲"
    if (filterState.sort === `-${key}`) return "▼"
    return ""
}

const hasFilters = computed(() =>
    ["organisation", "family", "condition", "state", "search"].some((key) => props.filters[key as keyof Filters])
)

function exportCsv(): void {
    const params: Record<string, string> = {}
    for (const [key, value] of Object.entries(filterState)) {
        if (value !== null && value !== "") {
            params[key] = value as string
        }
    }
    window.location.href = route("grp.goods.export", params)
}

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

const shortDate = (date: string | null): string =>
    date ? new Date(date).toLocaleDateString("en-GB", { day: "numeric", month: "short" }) : ""

const sourceLabels: Record<string, string> = { CB: "CurrencyBeacon", F: "Frankfurter", M: "Manual" }

const rateNotes = computed(() => {
    const seen = new Set<string>()
    return props.rates
        .filter((rate) => rate.rate !== null && rate.currency !== props.currency_code && !seen.has(rate.currency) && seen.add(rate.currency))
        .map((rate) => `${rate.currency}→${props.currency_code} ${(rate.rate as number).toFixed(4)}, ${sourceLabels[rate.source ?? ""] ?? rate.source}, ${shortDate(rate.date)}`)
        .join(" · ")
})

function cellLabel(cell: Cell): string {
    return cell.inbound > 0 ? `${quantity(cell.available)}+${quantity(cell.inbound)}` : quantity(cell.available)
}

function cellTooltip(cell: Cell): string {
    const base = ctrans("On hand :on_hand · allocated :allocated · available :available", {
        on_hand: quantity(cell.on_hand),
        allocated: quantity(cell.allocated),
        available: quantity(cell.available),
    })

    if (cell.inbound > 0) {
        return (
            base +
            " · " +
            ctrans(":inbound inbound, expected :expected", {
                inbound: quantity(cell.inbound),
                expected: cell.next_expected_at ? shortDate(cell.next_expected_at) : ctrans("unknown"),
            })
        )
    }

    return base
}

const kpiCards = computed(() => [
    {
        key: null,
        icon: "warehouse",
        label: ctrans("Group Stock Value"),
        value: money(props.kpis.stock_value, true),
        detail: ctrans("≈ :amount potential sales at current prices, ex VAT", { amount: money(props.kpis.commercial_value, true) }),
        class: "text-gray-900",
    },
    {
        key: "oos",
        icon: "ban",
        label: ctrans("Out of Stock"),
        value: quantity(props.kpis.out_of_stock),
        detail: ctrans(":count without PO", { count: quantity(props.kpis.out_of_stock_without_po) }),
        detailKey: "oos_no_po",
        class: "text-red-600",
    },
    {
        key: "low",
        icon: "exclamation-triangle",
        label: ctrans("Understocked"),
        value: quantity(props.kpis.low),
        detail: ctrans(":count without PO", { count: quantity(props.kpis.low_without_po) }),
        detailKey: "low_no_po",
        class: "text-amber-600",
    },
    {
        key: "over",
        icon: "boxes",
        label: ctrans("Overstock Value"),
        value: money(props.kpis.overstock_value, true),
        detail: ctrans(":count products", { count: quantity(props.kpis.overstock) }),
        class: "text-purple-600",
    },
    {
        key: "off",
        icon: "plug",
        label: ctrans("Offline with Stock"),
        value: quantity(props.kpis.offline),
        detail: ctrans(":amount stock value", { amount: money(props.kpis.offline_value, true) }),
        class: "text-pink-600",
    },
])

const newStates = reactive<Record<number, string>>({})
const modal = ref<{ row: Row; state: string } | null>(null)

const actionRoute = (row: Row, name: string): routeType => ({
    name: `grp.org.warehouses.show.inventory.org_stocks.${name}`,
    parameters: { organisation: row.action!.organisation, warehouse: row.action!.warehouse as string },
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

const drawerSlug = ref<string | null>(null)
const drawerOpen = ref(false)

function openDrawer(row: Row): void {
    drawerSlug.value = row.slug
    drawerOpen.value = true
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <GoodsViewToggle active="command" :organisation="filters.organisation" :family="filters.family" :search="filters.search" />

    <div class="mx-4 mt-1 text-sm text-gray-500">{{ ctrans("Every product, every organisation, one operational view") }}</div>

    <div class="mx-4 mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
        <span>{{ ctrans("Figures as of :time, refreshed every 10 minutes.", { time: builtAt }) }}<template v-if="rateNotes"> · {{ rateNotes }}</template></span>
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
                :aria-label="card.label"
                @click="card.key && load({ condition: filters.condition === card.key ? null : card.key })"
            >
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <FontAwesomeIcon :icon="['fal', card.icon]" fixed-width />
                    {{ card.label }}
                </div>
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
            :aria-label="ctrans('Family')"
            :disabled="loading"
            @change="load()"
        />
        <datalist id="product-control-families">
            <option v-for="family in families" :key="family" :value="family" />
        </datalist>
        <select v-model="filterState.organisation" class="rounded-md border-gray-300 text-sm" :aria-label="ctrans('Organisation')" :disabled="loading" @change="load()">
            <option :value="null">{{ ctrans("All organisations") }}</option>
            <option v-for="organisation in organisations" :key="organisation.code" :value="organisation.code">{{ organisation.name }}</option>
        </select>
        <select v-model="filterState.condition" class="rounded-md border-gray-300 text-sm" :aria-label="ctrans('Stock condition')" :disabled="loading" @change="load()">
            <option :value="null">{{ ctrans("Any stock condition") }}</option>
            <option v-for="condition in conditionFilters" :key="condition.value" :value="condition.value">{{ condition.label }}</option>
        </select>
        <select v-model="filterState.state" class="rounded-md border-gray-300 text-sm" :aria-label="ctrans('Status')" :disabled="loading" @change="load()">
            <option :value="null">{{ ctrans("Any status") }}</option>
            <option v-for="(status, value) in statuses" :key="value" :value="value">{{ status.label }}</option>
        </select>
        <input
            v-model="filterState.search"
            type="search"
            class="min-w-64 flex-1 rounded-md border-gray-300 text-sm"
            :placeholder="ctrans('Search code, description or family')"
            :aria-label="ctrans('Search code, description or family')"
            :disabled="loading"
            @keyup.enter="load()"
            @search="load()"
        />
        <button v-if="hasFilters" type="button" class="text-sm text-indigo-600 hover:underline" @click="reset">{{ ctrans("Reset") }}</button>
    </div>

    <div class="mx-4 mb-2 flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm font-semibold text-gray-900">{{ ctrans("All products") }} <span class="font-normal text-gray-500">{{ quantity(pagination.total) }}</span></div>
        <div class="flex items-center gap-2">
            <select v-model="filterState.period" class="rounded-md border-gray-300 text-xs" :aria-label="ctrans('Period')" :disabled="loading" @change="load()">
                <option v-for="p in periods" :key="p" :value="p">{{ periodLabels[p] }}</option>
            </select>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                :aria-label="ctrans('Export')"
                @click="exportCsv"
            >
                <FontAwesomeIcon :icon="['fal', 'download']" fixed-width />
                {{ ctrans("Export") }}
            </button>
        </div>
    </div>

    <div class="mx-4 mb-6 overflow-x-auto rounded-lg border border-gray-200" :class="{ 'opacity-60': loading }">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-left uppercase text-gray-600">
                <tr>
                    <th class="px-1.5 py-2">
                        <button type="button" class="uppercase" @click="toggleSort('code')">{{ ctrans("Code — description") }} {{ sortArrow("code") }}</button>
                    </th>
                    <th class="px-1.5 py-2 text-right">
                        <button type="button" class="uppercase" @click="toggleSort('sales')">{{ ctrans("Sales") }} {{ sortArrow("sales") }}</button>
                    </th>
                    <th v-for="organisation in organisations" :key="organisation.code" class="px-1.5 py-2" :title="organisation.name">
                        {{ organisation.code }}
                    </th>
                    <th class="px-1.5 py-2 text-right">
                        <button type="button" class="uppercase" @click="toggleSort('cover')">{{ ctrans("Cover") }} {{ sortArrow("cover") }}</button>
                    </th>
                    <th class="px-1.5 py-2">{{ ctrans("Group product status") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
                    <td class="max-w-[170px] truncate px-1.5 py-1.5" :title="`${row.code} — ${row.name ?? ''}${row.family_code ? ' (' + row.family_code + ')' : ''}`">
                        <button type="button" class="text-left hover:underline" @click="openDrawer(row)">
                            <span class="font-medium text-gray-900">{{ row.code }}</span>
                            <span> — {{ row.name }}</span>
                        </button>
                    </td>
                    <td class="whitespace-nowrap px-1.5 py-1.5 text-right tabular-nums">
                        {{ money(row.sales) }}
                        <span v-if="row.trend !== null" class="ml-1" :class="row.trend >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ row.trend >= 0 ? "↑" : "↓" }}{{ Math.abs(row.trend) }}%
                        </span>
                    </td>
                    <td v-for="organisation in organisations" :key="organisation.code" class="whitespace-nowrap px-1.5 py-1.5 tabular-nums">
                        <template v-if="row.organisations[organisation.code]">
                            {{ cellLabel(row.organisations[organisation.code]) }}
                            <span
                                v-tooltip="cellTooltip(row.organisations[organisation.code])"
                                class="ml-0.5 rounded px-1 py-0.5 font-semibold"
                                :class="conditions[row.organisations[organisation.code].condition].class"
                            >
                                {{ conditions[row.organisations[organisation.code].condition].label }}
                            </span>
                        </template>
                        <span v-else class="text-gray-300">—</span>
                    </td>
                    <td class="whitespace-nowrap px-1.5 py-1.5 text-right tabular-nums">
                        <span v-if="row.cover_weeks !== null">{{ row.cover_weeks }}{{ ctrans("w") }}</span>
                        <span v-else v-tooltip="ctrans('No sales to measure cover')" class="text-gray-400">{{ ctrans("no sales") }}</span>
                    </td>
                    <td class="whitespace-nowrap px-1.5 py-1.5">
                        <div class="flex items-center gap-1">
                            <span class="w-14 rounded px-1 py-0.5 text-center font-semibold" :class="statuses[row.state]?.class">
                                {{ statuses[row.state]?.pill ?? row.state }}
                            </span>
                            <template v-if="row.action">
                                <select v-model="newStates[row.id]" class="w-20 rounded-md border-gray-300 py-0.5 text-xs" :aria-label="ctrans('Select new status')">
                                    <option :value="undefined">{{ ctrans("Select new") }}</option>
                                    <option v-for="(status, value) in statuses" :key="value" :value="value" :disabled="value === row.state">{{ status.label }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-1 py-0.5 font-semibold text-white disabled:opacity-40"
                                    :disabled="!newStates[row.id] || newStates[row.id] === row.state"
                                    :aria-label="ctrans('Set')"
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
        :orgStockIds="[modal.row.action!.org_stock_id]"
        :initialState="modal.state"
        :previewRoute="actionRoute(modal.row, 'discontinue_preview')"
        :discontinueRoute="actionRoute(modal.row, 'discontinue')"
        @onClose="modal = null"
        @onDone="onSetDone"
    />

    <ProductDetailDrawer :isOpen="drawerOpen" :stockSlug="drawerSlug" @onClose="drawerOpen = false" />
</template>
