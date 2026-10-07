<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 24 Sep 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue"
import { useElementSize } from "@vueuse/core"
import { useScrollArrows } from "@/Composables/useScrollArrows"
import ScrollFadeArrow from "@/Components/Utils/ScrollFadeArrow.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import OrgStockDiscontinuePreviewModal from "@/Components/Warehouse/Inventory/OrgStockDiscontinuePreviewModal.vue"
import ProductDetailDrawer from "@/Components/Goods/ProductDetailDrawer.vue"
import GoodsViewToggle from "@/Components/Goods/GoodsViewToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import Select from "primevue/select"
import InputText from "primevue/inputtext"

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
    built_at?: string
    currency_code: string
    can_edit: boolean
    organisations?: OrganisationMeta[]
    families?: string[]
    missing_rates?: string[]
    rates?: RateInfo[]
    period: Period
    periods: Period[]
    kpis?: {
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
    rows?: Row[]
    pagination?: { page: number; last_page: number; total: number; per_page: number }
    filters: Filters
    editable_organisations?: string[]
    can_change_group?: boolean
}>()

const dashboardProps = ["built_at", "organisations", "families", "missing_rates", "rates", "kpis", "rows", "pagination", "editable_organisations", "can_change_group", "filters", "period"]

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

const pagerBar = ref<HTMLElement | null>(null)
const { height: pagerBarHeight } = useElementSize(pagerBar, undefined, { box: "border-box" })
const tableHead = ref<HTMLElement | null>(null)
const { height: tableHeadHeight } = useElementSize(tableHead, undefined, { box: "border-box" })
const tableScroller = ref<HTMLElement | null>(null)
const { canScrollUp, canScrollDown, scrollVerticallyBy } = useScrollArrows(tableScroller)

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
        only: dashboardProps,
        preserveState: true,
        preserveScroll: true,
    })
}

function reset(): void {
    load({ organisation: null, family: null, condition: null, state: null, search: null, sort: "-sales", period: "90d" })
}

function toggleCondition(condition: string): void {
    load({ condition: filterState.condition === condition ? null : condition })
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

const builtAt = computed(() => (props.built_at ? new Date(props.built_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : ""))

const shortDate = (date: string | null): string =>
    date ? new Date(date).toLocaleDateString("en-GB", { day: "numeric", month: "short" }) : ""

const sourceLabels: Record<string, string> = { CB: "CurrencyBeacon", F: "Frankfurter", M: "Manual" }

const rateNotes = computed(() => {
    const seen = new Set<string>()
    return (props.rates ?? [])
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

const kpiCards = computed(() => {
    const kpis = props.kpis
    if (!kpis) {
        return []
    }

    return [
        {
            key: null,
            icon: "warehouse",
            label: ctrans("Group Stock Value"),
            value: money(kpis.stock_value, true),
            detail: ctrans("≈ :amount potential sales at current prices, ex VAT", { amount: money(kpis.commercial_value, true) }),
            class: "text-gray-900",
        },
        {
            key: "oos",
            icon: "ban",
            label: ctrans("Out of Stock"),
            value: quantity(kpis.out_of_stock),
            detail: ctrans(":count without PO", { count: quantity(kpis.out_of_stock_without_po) }),
            detailKey: "oos_no_po",
            class: "text-red-600",
        },
        {
            key: "low",
            icon: "exclamation-triangle",
            label: ctrans("Understocked"),
            value: quantity(kpis.low),
            detail: ctrans(":count without PO", { count: quantity(kpis.low_without_po) }),
            detailKey: "low_no_po",
            class: "text-amber-600",
        },
        {
            key: "over",
            icon: "boxes",
            label: ctrans("Overstock Value"),
            value: money(kpis.overstock_value, true),
            detail: ctrans(":count products", { count: quantity(kpis.overstock) }),
            class: "text-purple-600",
        },
        {
            key: "off",
            icon: "plug",
            label: ctrans("Offline with Stock"),
            value: quantity(kpis.offline),
            detail: ctrans(":amount stock value", { amount: money(kpis.offline_value, true) }),
            class: "text-pink-600",
        },
    ]
})

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
    drawerReloadKey.value++
}

const fieldClass = "[&.p-focus]:!border-[--app-accent] focus:!border-[--app-accent]"

const filterSelectPt = { label: { class: "!text-sm" } }

const rowSelectPt = {
    label: { class: "!text-xs" },
    option: ({ context }: { context: { disabled: boolean } }) => ({
        class: context.disabled ? "!cursor-not-allowed !text-gray-400 !opacity-100" : "",
    }),
}

const statusOptions = computed(() => Object.entries(statuses).map(([value, status]) => ({ value, label: status.label })))

const periodOptions = computed(() => props.periods.map((period) => ({ value: period, label: periodLabels[period] })))

const rowStatusOptions = (row: Row) => statusOptions.value.map((option) => ({ ...option, disabled: option.value === row.state }))

const pagerButtonClass = "rounded-md border border-gray-300 bg-white px-3 py-1.5 font-medium text-gray-700 transition-colors enabled:hover:bg-gray-50 enabled:hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40"

const drawerSlug = ref<string | null>(null)
const drawerReloadKey = ref(0)

const drawerNavigation = computed(() => (props.rows ?? []).map((row) => ({ slug: row.slug, label: row.name ? `${row.code} — ${row.name}` : row.code })))

const drawerRow = computed(() => (props.rows ?? []).find((row) => row.slug === drawerSlug.value) ?? null)
const drawerOpen = ref(false)

function openDrawer(row: Row): void {
    drawerSlug.value = row.slug
    drawerOpen.value = true
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <GoodsViewToggle active="command" :organisation="filters.organisation" :family="filters.family" :search="filters.search" :fetching="!rows || !kpis" />

    <div class="mx-4 mt-4 space-y-1">
        <div class="text-sm text-gray-600">{{ ctrans("Every product, every organisation, one operational view") }}</div>
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
            <span v-if="built_at">{{ ctrans("Figures as of :time, refreshed every 10 minutes.", { time: builtAt }) }}<template v-if="rateNotes"> · {{ rateNotes }}</template></span>
            <span v-else class="h-3 w-80 max-w-full animate-pulse rounded bg-gray-200" />
            <span v-if="missing_rates?.length" class="text-red-600">
                {{ ctrans("No exchange rate for :organisations, their stock value is left out", { organisations: missing_rates.join(", ") }) }}
            </span>
        </div>
    </div>

    <div v-if="!kpis" class="mx-4 mt-4 grid grid-cols-2 gap-4 md:grid-cols-5">
        <div v-for="index in 5" :key="index" class="animate-pulse rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="h-3 w-24 rounded bg-gray-200" />
            <div class="mt-3 h-7 w-20 rounded bg-gray-200" />
            <div class="mt-3 h-3 w-32 rounded bg-gray-100" />
        </div>
    </div>
    <div v-else class="mx-4 mt-4 grid grid-cols-2 gap-4 md:grid-cols-5">
        <div
            v-for="card in kpiCards"
            :key="card.label"
            class="group rounded-lg border bg-white p-4 shadow-sm transition"
            :class="[
                card.key ? 'cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]' : '',
                card.key && filters.condition === card.key
                    ? 'border-[--app-accent] bg-[--app-accent-soft] ring-1 ring-[--app-accent]'
                    : card.key
                      ? 'border-gray-200 hover:-translate-y-0.5 hover:border-[--app-accent] hover:shadow-md'
                      : 'border-gray-200',
            ]"
            :role="card.key ? 'button' : undefined"
            :tabindex="card.key ? 0 : undefined"
            :aria-label="card.key ? card.label : undefined"
            :aria-pressed="card.key ? filters.condition === card.key : undefined"
            @click="card.key && toggleCondition(card.key)"
            @keydown.enter.prevent="card.key && toggleCondition(card.key)"
            @keydown.space.prevent="card.key && toggleCondition(card.key)"
        >
            <div class="flex items-center gap-1.5 text-xs font-medium text-gray-500" :class="{ 'group-hover:text-gray-700': card.key }">
                <FontAwesomeIcon :icon="['fal', card.icon]" fixed-width />
                {{ card.label }}
            </div>
            <div class="mt-1 text-2xl font-semibold tabular-nums" :class="card.class">{{ card.value }}</div>
            <button
                v-if="card.detailKey"
                type="button"
                class="relative mt-1 rounded text-xs transition-colors hover:text-[--app-accent] hover:underline"
                :class="filters.condition === card.detailKey ? 'font-semibold text-[--app-accent]' : 'text-gray-500'"
                @click.stop="toggleCondition(card.detailKey)"
                @keydown.stop
            >
                {{ card.detail }}
            </button>
            <div v-else class="mt-1 text-xs text-gray-500">{{ card.detail }}</div>
        </div>
    </div>

    <div class="mx-4 mt-4 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
        <Select
            v-model="filterState.family"
            :options="families ?? []"
            filter
            showClear
            :virtualScrollerOptions="{ itemSize: 36 }"
            class="w-44"
            :class="fieldClass"
            :pt="filterSelectPt"
            :placeholder="ctrans('Any family')"
            :aria-label="ctrans('Family')"
            :disabled="loading || !families"
            @change="load()"
        />
        <Select
            v-model="filterState.organisation"
            :options="organisations ?? []"
            optionLabel="name"
            optionValue="code"
            showClear
            class="w-52"
            :class="fieldClass"
            :pt="filterSelectPt"
            :placeholder="ctrans('All organisations')"
            :aria-label="ctrans('Organisation')"
            :disabled="loading || !organisations"
            @change="load()"
        />
        <Select
            v-model="filterState.condition"
            :options="conditionFilters"
            optionLabel="label"
            optionValue="value"
            showClear
            class="w-52"
            :class="fieldClass"
            :pt="filterSelectPt"
            :placeholder="ctrans('Any stock condition')"
            :aria-label="ctrans('Stock condition')"
            :disabled="loading"
            @change="load()"
        />
        <Select
            v-model="filterState.state"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            showClear
            class="w-40"
            :class="fieldClass"
            :pt="filterSelectPt"
            :placeholder="ctrans('Any status')"
            :aria-label="ctrans('Status')"
            :disabled="loading"
            @change="load()"
        />
        <InputText
            v-model="filterState.search"
            type="search"
            class="min-w-64 flex-1 !text-sm"
            :class="fieldClass"
            :placeholder="ctrans('Search code, description or family')"
            :aria-label="ctrans('Search code, description or family')"
            :disabled="loading"
            @keyup.enter="load()"
            @search="load()"
        />
        <button
            v-if="hasFilters"
            type="button"
            class="rounded-md px-3 py-2 text-sm font-medium text-[--app-accent] transition-colors hover:bg-[--app-accent-soft]"
            @click="reset"
        >
            {{ ctrans("Reset") }}
        </button>
    </div>

    <div class="mx-4 mb-3 mt-6 flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm font-semibold text-gray-900">{{ ctrans("All products") }} <span v-if="pagination" class="ml-1 font-normal text-gray-500">{{ quantity(pagination.total) }}</span></div>
        <div class="flex items-center gap-2">
            <Select
                v-model="filterState.period"
                :options="periodOptions"
                optionLabel="label"
                optionValue="value"
                class="w-44"
                :class="fieldClass"
                :pt="filterSelectPt"
                :aria-label="ctrans('Period')"
                :disabled="loading"
                @change="load()"
            />
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900"
                :aria-label="ctrans('Export')"
                @click="exportCsv"
            >
                <FontAwesomeIcon :icon="['fal', 'download']" fixed-width />
                {{ ctrans("Export") }}
            </button>
        </div>
    </div>

    <div v-if="!rows || !pagination || !organisations" class="mx-4 mb-8 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex gap-6 border-b border-gray-200 bg-gray-50 px-3 py-3">
            <div class="h-3 w-40 animate-pulse rounded bg-gray-200" />
            <div class="h-3 w-16 animate-pulse rounded bg-gray-200" />
            <div class="h-3 flex-1 animate-pulse rounded bg-gray-200" />
            <div class="h-3 w-24 animate-pulse rounded bg-gray-200" />
        </div>
        <div class="divide-y divide-gray-100">
            <div v-for="index in 12" :key="index" class="flex animate-pulse items-center gap-6 px-3 py-3">
                <div class="h-3 w-40 rounded bg-gray-200" />
                <div class="h-3 w-16 rounded bg-gray-100" />
                <div class="h-3 flex-1 rounded bg-gray-100" />
                <div class="h-5 w-24 rounded bg-gray-200" />
            </div>
        </div>
    </div>
    <div v-else class="relative isolate mx-4 mb-8 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
    <div ref="tableScroller" class="max-h-[80vh] overflow-auto transition-opacity" :class="{ 'opacity-60': loading }">
        <div ref="pagerBar" class="sticky left-0 top-0 z-20 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-2 text-xs text-gray-600">
            <span>{{ ctrans(":total products", { total: quantity(pagination.total) }) }}</span>
            <div class="flex items-center gap-3">
                <button type="button" :class="pagerButtonClass" :disabled="loading || pagination.page <= 1" @click="load({}, pagination.page - 1)">
                    {{ ctrans("Previous") }}
                </button>
                <span>{{ ctrans("Page :page of :pages", { page: pagination.page, pages: pagination.last_page }) }}</span>
                <button type="button" :class="pagerButtonClass" :disabled="loading || pagination.page >= pagination.last_page" @click="load({}, pagination.page + 1)">
                    {{ ctrans("Next") }}
                </button>
            </div>
        </div>
        <table class="min-w-full text-xs">
            <thead ref="tableHead" :style="{ top: pagerBarHeight + 'px' }" class="sticky z-10 border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-3 py-2.5">
                        <button type="button" class="uppercase tracking-wide transition-colors hover:text-[--app-accent]" @click="toggleSort('code')">{{ ctrans("Code — description") }} {{ sortArrow("code") }}</button>
                    </th>
                    <th class="px-3 py-2.5 text-right">
                        <button type="button" class="uppercase tracking-wide transition-colors hover:text-[--app-accent]" @click="toggleSort('sales')">{{ ctrans("Sales") }} {{ sortArrow("sales") }}</button>
                    </th>
                    <th v-for="organisation in organisations" :key="organisation.code" class="px-3 py-2.5" :title="organisation.name">
                        {{ organisation.code }}
                    </th>
                    <th class="px-3 py-2.5 text-right">
                        <button type="button" class="uppercase tracking-wide transition-colors hover:text-[--app-accent]" @click="toggleSort('cover')">{{ ctrans("Cover") }} {{ sortArrow("cover") }}</button>
                    </th>
                    <th class="px-3 py-2.5">{{ ctrans("Group product status") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                <tr v-for="row in rows" :key="row.id" class="transition-colors hover:bg-[--app-accent-soft]">
                    <td class="max-w-[220px] truncate px-3 py-2" :title="`${row.code} — ${row.name ?? ''}${row.family_code ? ' (' + row.family_code + ')' : ''}`">
                        <button type="button" class="group block w-full truncate text-left" @click="openDrawer(row)">
                            <span class="font-medium text-gray-900 group-hover:text-[--app-accent] group-hover:underline">{{ row.code }}</span>
                            <span> — {{ row.name }}</span>
                        </button>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                        {{ money(row.sales) }}
                        <span v-if="row.trend !== null" class="ml-1" :class="row.trend >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ row.trend >= 0 ? "↑" : "↓" }}{{ Math.abs(row.trend) }}%
                        </span>
                    </td>
                    <td v-for="organisation in organisations" :key="organisation.code" class="whitespace-nowrap px-3 py-2 tabular-nums">
                        <template v-if="row.organisations[organisation.code]">
                            {{ cellLabel(row.organisations[organisation.code]) }}
                            <span
                                v-tooltip="cellTooltip(row.organisations[organisation.code])"
                                class="ml-1 rounded px-1.5 py-0.5 font-semibold"
                                :class="conditions[row.organisations[organisation.code].condition].class"
                            >
                                {{ conditions[row.organisations[organisation.code].condition].label }}
                            </span>
                        </template>
                        <span v-else class="text-gray-300">—</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                        <span v-if="row.cover_weeks !== null">{{ row.cover_weeks }}{{ ctrans("w") }}</span>
                        <span v-else v-tooltip="ctrans('No sales to measure cover')" class="text-gray-400">{{ ctrans("no sales") }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                        <div class="flex items-center gap-1.5">
                            <span class="w-16 rounded px-1.5 py-0.5 text-center font-semibold" :class="statuses[row.state]?.class">
                                {{ statuses[row.state]?.pill ?? row.state }}
                            </span>
                            <template v-if="row.action">
                                <Select
                                    v-model="newStates[row.id]"
                                    :options="rowStatusOptions(row)"
                                    optionLabel="label"
                                    optionValue="value"
                                    optionDisabled="disabled"
                                    size="small"
                                    class="w-36 text-xs"
                                    :class="fieldClass"
                                    :pt="rowSelectPt"
                                    :placeholder="ctrans('Select new')"
                                    :aria-label="ctrans('Select new status')"
                                >
                                    <template #option="{ option }">
                                        <span class="flex w-full items-center justify-between gap-2 text-xs">
                                            {{ option.label }}
                                            <span v-if="option.disabled" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-400">{{ ctrans("current") }}</span>
                                        </span>
                                    </template>
                                </Select>
                                <button
                                    type="button"
                                    class="rounded-md bg-[--app-accent] px-2.5 py-1 font-semibold text-[--app-accent-text] shadow-sm transition-colors enabled:hover:bg-[--app-accent-strong] disabled:cursor-not-allowed disabled:opacity-40"
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
                    <td :colspan="organisations.length + 4" class="py-12 text-center text-sm text-gray-500">{{ ctrans("No products match these filters.") }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="pointer-events-none absolute inset-x-0 bottom-0" :style="{ top: pagerBarHeight + tableHeadHeight + 'px' }">
        <ScrollFadeArrow direction="up" :visible="canScrollUp" @click="scrollVerticallyBy(-1)" />
        <ScrollFadeArrow direction="down" :visible="canScrollDown" @click="scrollVerticallyBy(1)" />
    </div>
    </div>

    <OrgStockDiscontinuePreviewModal
        v-if="modal"
        :isOpen="!!modal"
        :orgStockIds="[modal.row.action!.org_stock_id]"
        :initialState="modal.state"
        :previewRoute="actionRoute(modal.row, 'discontinue_preview')"
        :discontinueRoute="actionRoute(modal.row, 'discontinue')"
        :zIndex="40"
        @onClose="modal = null"
        @onDone="onSetDone"
    />

    <ProductDetailDrawer
        :isOpen="drawerOpen"
        :stockSlug="drawerSlug"
        :navigation="drawerNavigation"
        :reloadKey="drawerReloadKey"
        @onClose="drawerOpen = false"
        @navigate="(slug: string) => (drawerSlug = slug)"
    >
        <template #action>
            <div v-if="drawerRow?.action" class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs">
                <span class="font-medium text-gray-600">{{ ctrans("Group product status") }}</span>
                <span class="w-16 rounded px-1.5 py-0.5 text-center font-semibold" :class="statuses[drawerRow.state]?.class">
                    {{ statuses[drawerRow.state]?.pill ?? drawerRow.state }}
                </span>
                <Select
                    v-model="newStates[drawerRow.id]"
                    :options="rowStatusOptions(drawerRow)"
                    optionLabel="label"
                    optionValue="value"
                    optionDisabled="disabled"
                    size="small"
                    class="ml-auto w-40 text-xs"
                    :class="fieldClass"
                    :pt="rowSelectPt"
                    :placeholder="ctrans('Select new')"
                    :aria-label="ctrans('Select new status')"
                >
                    <template #option="{ option }">
                        <span class="flex w-full items-center justify-between gap-2 text-xs">
                            {{ option.label }}
                            <span v-if="option.disabled" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-400">{{ ctrans("current") }}</span>
                        </span>
                    </template>
                </Select>
                <button
                    type="button"
                    class="rounded-md bg-[--app-accent] px-3 py-1.5 font-semibold text-[--app-accent-text] shadow-sm transition-colors enabled:hover:bg-[--app-accent-strong] disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="!newStates[drawerRow.id] || newStates[drawerRow.id] === drawerRow.state"
                    @click="openSet(drawerRow)"
                >
                    {{ ctrans("Set") }}
                </button>
            </div>
        </template>
    </ProductDetailDrawer>
</template>
