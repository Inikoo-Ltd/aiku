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
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import Popover from "primevue/popover"
import Table from "@/Components/Table/Table.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSlidersH, faWarehouse, faBan, faExclamationTriangle, faBoxes, faPlug, faDownload, faThumbsDown } from "@fal"
import { faCheckCircle, faPauseCircle, faTimesCircle, faExclamationCircle } from "@fas"
library.add(faSlidersH, faWarehouse, faBan, faExclamationTriangle, faBoxes, faPlug, faDownload, faThumbsDown, faCheckCircle, faPauseCircle, faTimesCircle, faExclamationCircle)

type Condition = "ok" | "low" | "oos" | "ns" | "over" | "off" | "sell" | "hold" | "ret" | "dead"
type Period = "30d" | "90d" | "quarter" | "year"

interface Cell {
    org_stock_id: number
    state: string
    on_hand: number
    available: number
    allocated: number
    inbound: number
    cover_weeks?: number | null
    sales?: number
    trend?: number | null
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
    suggestions: number
    suggestion_people: number
    feedback: number
    feedback_level: FeedbackLevel | null
    orders: number
    complaint_rate: number | null
    complaints: number
    complaint_customers: number
    refunds: number
    replacements: number
    next_order: number
    supplier_claims: number
    suggestion_evidence: {
        sources: { label: string; times: number }[]
        reasons: { label: string; times: number }[]
        notes: { user: string | null; organisation: string; source: string; reasons: string[]; note: string; date: string }[]
    } | null
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
    feedback: FeedbackLevel | null
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
        suggested: number
        suggestions: number
        feedback_levels: Record<FeedbackLevel, number>
    }
    rows?: { data: Row[]; total: number }
    pagination?: { page: number; last_page: number; total: number; per_page: number }
    filters: Filters
    editable_organisations?: string[]
    can_change_group?: boolean
}>()

const dashboardProps = ["queryBuilderProps", "built_at", "organisations", "families", "missing_rates", "rates", "kpis", "rows", "pagination", "editable_organisations", "can_change_group", "filters", "period"]

const conditions: Record<Condition, { label: string; tooltip: string; class: string }> = {
    ok: { label: ctrans("OK"), tooltip: ctrans("Covered"), class: "bg-green-50 text-green-700" },
    low: { label: ctrans("LOW"), tooltip: ctrans("Runs out within two lead times"), class: "bg-amber-50 text-amber-700" },
    oos: { label: ctrans("OOS"), tooltip: ctrans("Out of stock"), class: "bg-red-50 text-red-700" },
    ns: { label: ctrans("NS"), tooltip: ctrans("Not stocked here: no stock, no sales in 180 days, nothing on order"), class: "bg-gray-100 text-gray-500" },
    over: { label: ctrans("OVER"), tooltip: ctrans("More than 120 days of stock"), class: "bg-purple-50 text-purple-700" },
    dead: { label: ctrans("DEAD"), tooltip: ctrans("Stock held, no sales"), class: "bg-gray-100 text-gray-600" },
    off: { label: ctrans("OFF SALE"), tooltip: ctrans("Stock held but no shop sells it"), class: "bg-pink-50 text-pink-700" },
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

const stateIcons: Record<string, { icon: string[]; class: string }> = {
    active: { icon: ["fas", "check-circle"], class: "text-green-500" },
    suspended: { icon: ["fas", "pause-circle"], class: "text-slate-300" },
    discontinuing: { icon: ["fas", "times-circle"], class: "text-amber-500" },
    discontinued: { icon: ["fas", "times-circle"], class: "text-red-500" },
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
    { value: "suggested", label: ctrans("Suggested to discontinue") },
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
        only: dashboardProps,
        preserveState: true,
        preserveScroll: true,
    })
}

function reset(): void {
    load({ organisation: null, family: null, condition: null, state: null, feedback: null, search: null, sort: "-sales", period: "90d" })
}

function toggleCondition(condition: string): void {
    const isOn = filterState.condition !== condition
    load({ condition: isOn ? condition : null, ...(condition === "suggested" ? { sort: isOn ? "-suggestions" : "-sales" } : {}) })
}

type FeedbackLevel = "mild" | "bad" | "severe" | "disaster"

const feedbackLevels: Record<FeedbackLevel, { label: string; class: string }> = {
    mild: { label: ctrans("Mild"), class: "bg-gray-100 text-gray-600" },
    bad: { label: ctrans("Bad"), class: "bg-amber-100 text-amber-700" },
    severe: { label: ctrans("Severe"), class: "bg-orange-100 text-orange-700" },
    disaster: { label: ctrans("Disaster"), class: "bg-red-100 text-red-700" },
}

const feedbackOptions = computed(() =>
    (Object.keys(feedbackLevels) as FeedbackLevel[]).map((level) => ({ value: level, label: feedbackLevels[level].label, count: props.kpis?.feedback_levels[level] }))
)

function selectFeedback(level: string | null): void {
    load({ feedback: level as FeedbackLevel | null, sort: level ? "-feedback" : "-sales" })
}

const moneyInThousands = (amount: number): string =>
    new Intl.NumberFormat("en-GB", { style: "currency", currency: props.currency_code, minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(amount / 1000) + "K"

const conditionDots: Record<Condition, string> = {
    ok: "bg-green-500",
    low: "bg-amber-500",
    oos: "bg-red-500",
    over: "bg-purple-500",
    off: "bg-pink-500",
    sell: "bg-blue-500",
    hold: "bg-orange-500",
    ns: "bg-gray-300",
    dead: "bg-gray-500",
    ret: "bg-gray-300",
}

const salesPopover = ref()
const salesRow = ref<Row | null>(null)

function showSales(event: MouseEvent, row: Row): void {
    salesRow.value = row
    salesPopover.value?.show(event, event.currentTarget)
}

function salesDot(cell: Cell | undefined): string {
    if (!cell) {
        return "bg-transparent ring-1 ring-inset ring-gray-200"
    }
    if (!cell.sales) {
        return "bg-gray-300"
    }
    if (cell.trend === null || cell.trend === undefined) {
        return "bg-gray-500"
    }

    return cell.trend >= 0 ? "bg-green-500" : "bg-red-500"
}

const stockPopover = ref()
const stockRow = ref<Row | null>(null)

function showStock(event: MouseEvent, row: Row): void {
    stockRow.value = row
    stockPopover.value?.show(event, event.currentTarget)
}

function groupStock(row: Row): { available: number; inbound: number } {
    return Object.values(row.organisations).reduce(
        (total, cell) => ({ available: total.available + Math.max(0, cell.available), inbound: total.inbound + cell.inbound }),
        { available: 0, inbound: 0 }
    )
}

const SUGGESTION_PEOPLE_TO_ACT = 3

const suggestionPopover = ref()
const suggestionRow = ref<Row | null>(null)

function showSuggestions(event: MouseEvent, row: Row): void {
    suggestionRow.value = row
    suggestionPopover.value?.show(event, event.currentTarget)
}

function complaintTooltip(row: Row): string {
    return [
        row.complaints ? ctrans(":customers customers compensated in :orders orders that carried it, last 12 months", { customers: quantity(row.complaint_customers), orders: quantity(row.orders) }) : "",
        row.refunds ? ctrans(":count refunds", { count: quantity(row.refunds) }) : "",
        row.replacements ? ctrans(":count replacements", { count: quantity(row.replacements) }) : "",
        row.next_order ? ctrans(":count sent free in a next order", { count: quantity(row.next_order) }) : "",
        row.supplier_claims ? ctrans(":count claims to the supplier", { count: quantity(row.supplier_claims) }) : "",
    ]
        .filter(Boolean)
        .join(" · ")
}

function suggestionSummary(times: number, people: number): string {
    return ctrans(":people staff voted to remove this product, :times reports in all. Click to read why", { times: quantity(times), people: quantity(people) })
}

const hasFilters = computed(() =>
    ["organisation", "family", "condition", "state", "feedback", "search"].some((key) => props.filters[key as keyof Filters])
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

const COVER_CONDITIONS: Condition[] = ["ok", "low", "over"]

function hasCover(cell: Cell): boolean {
    return COVER_CONDITIONS.includes(cell.condition) && cell.cover_weeks !== null && cell.cover_weeks !== undefined
}

function cellTag(cell: Cell): string {
    if (!hasCover(cell)) {
        return conditions[cell.condition].label
    }
    const weeks = cell.cover_weeks as number

    return weeks < 1 ? ctrans("<1w") : ctrans(":weeks w", { weeks: Math.round(weeks) })
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
            detail: ctrans("≈ :amount potential sales", { amount: money(kpis.commercial_value, true) }),
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
        {
            key: "suggested",
            icon: "thumbs-down",
            label: ctrans("Suggested to discontinue"),
            value: quantity(kpis.suggested),
            detail: ctrans(":count reports from staff", { count: quantity(kpis.suggestions) }),
            class: kpis.suggested ? "text-red-600" : "text-gray-400",
        },
    ]
})

const newStates = reactive<Record<number, string>>({})
const modal = ref<{ rows: Row[]; state: string } | null>(null)

const previewRoute: routeType = { name: "grp.goods.org_stocks.discontinue_preview", parameters: {} }
const discontinueRoute: routeType = { name: "grp.goods.org_stocks.discontinue", parameters: {} }

function openSet(row: Row): void {
    modal.value = { rows: [row], state: newStates[row.id] }
}

function openBulkSet(): void {
    if (!bulkState.value || !bulkTargets.value.length) {
        return
    }

    modal.value = { rows: bulkTargets.value, state: bulkState.value }
}

function onSetDone(): void {
    if (modal.value) {
        const doneIds = modal.value.rows.map((row) => row.id)
        doneIds.forEach((id) => delete newStates[id])
        selectedIds.value = selectedIds.value.filter((id) => !doneIds.includes(id))
    }
    if (!selectedIds.value.length) {
        bulkState.value = null
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

const selectedIds = ref<number[]>([])
const tableKey = ref(0)

function onSelectRow(selected: Record<string, boolean>): void {
    selectedIds.value = Object.keys(selected)
        .filter((id) => selected[id])
        .map(Number)
}
const bulkState = ref<string | null>(null)

const selectableRows = computed(() => (props.rows?.data ?? []).filter((row) => row.action))

const selectedRows = computed(() => selectableRows.value.filter((row) => selectedIds.value.includes(row.id)))

const bulkStatusOptions = computed(() =>
    statusOptions.value.map((option) => ({ ...option, disabled: selectedRows.value.every((row) => row.state === option.value) }))
)

const bulkTargets = computed(() => selectedRows.value.filter((row) => row.state !== bulkState.value))

const selectedStateSummary = computed(() =>
    Object.keys(statuses)
        .map((state) => ({ state, count: selectedRows.value.filter((row) => row.state === state).length }))
        .filter((summary) => summary.count > 0)
)

const bulkApplyLabel = computed(() => {
    if (!bulkState.value || !bulkTargets.value.length) {
        return ctrans("Review change")
    }
    return bulkTargets.value.length === 1
        ? ctrans("Review change for 1 product")
        : ctrans("Review change for :count products", { count: bulkTargets.value.length })
})

const bulkHint = computed(() => {
    if (!bulkState.value) {
        return ctrans("Choose the new status, then review what it affects. Nothing changes until you confirm.")
    }

    const notes: string[] = []
    const skipped = selectedRows.value.length - bulkTargets.value.length
    if (skipped > 0) {
        notes.push(ctrans(":count already :status, left as they are.", { count: skipped, status: statuses[bulkState.value].label }))
    }
    notes.push(ctrans("Nothing changes until you confirm in the preview."))

    return notes.join(" ")
})

function clearSelection(): void {
    selectedIds.value = []
    bulkState.value = null
    tableKey.value++
}

watch(selectableRows, (rows) => {
    const ids = rows.map((row) => row.id)
    selectedIds.value = selectedIds.value.filter((id) => ids.includes(id))
    if (!selectedIds.value.length) {
        bulkState.value = null
    }
})

const drawerSlug = ref<string | null>(null)
const drawerReloadKey = ref(0)

const drawerNavigation = computed(() => (props.rows?.data ?? []).map((row) => ({ slug: row.slug, label: row.name ? `${row.code} — ${row.name}` : row.code })))

const drawerRow = computed(() => (props.rows?.data ?? []).find((row) => row.slug === drawerSlug.value) ?? null)
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

    <div class="mx-4 mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs text-gray-500">
        <span v-if="built_at">{{ ctrans("Figures as of :time, refreshed every 10 minutes.", { time: builtAt }) }}<template v-if="rateNotes"> · {{ rateNotes }}</template></span>
        <span v-else class="h-3 w-80 max-w-full animate-pulse rounded bg-gray-200" />
        <span v-if="missing_rates?.length" class="text-red-600">
            {{ ctrans("No exchange rate for :organisations, their stock value is left out", { organisations: missing_rates.join(", ") }) }}
        </span>
    </div>

    <div v-if="!kpis" class="mx-4 mt-2 grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
        <div v-for="index in 6" :key="index" class="animate-pulse rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm">
            <div class="h-3 w-24 rounded bg-gray-200" />
            <div class="mt-2 h-5 w-32 rounded bg-gray-200" />
        </div>
    </div>
    <div v-else class="mx-4 mt-2 grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
        <div
            v-for="card in kpiCards"
            :key="card.label"
            class="group rounded-lg border bg-white px-3 py-2 shadow-sm transition"
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
            <div class="flex flex-wrap items-baseline gap-x-2">
                <span class="text-lg font-semibold tabular-nums" :class="card.class">{{ card.value }}</span>
                <button
                    v-if="card.detailKey"
                    type="button"
                    class="relative rounded text-xs transition-colors hover:text-[--app-accent] hover:underline"
                    :class="filters.condition === card.detailKey ? 'font-semibold text-[--app-accent]' : 'text-gray-500'"
                    @click.stop="toggleCondition(card.detailKey)"
                    @keydown.stop
                >
                    {{ card.detail }}
                </button>
                <span v-else class="text-xs text-gray-500">{{ card.detail }}</span>
            </div>
        </div>
    </div>

    <div class="mx-4 mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
        <PillFilterBar
            v-tooltip="ctrans('Bad feedback against how often the product is ordered: the share of orders in the last 12 months that ended with the customer refunded or sent a replacement. Bad from 2%, severe from 5%, disaster from 10%, with at least 3 customers. What staff reported by hand is shown beside it and has its own card above')"
            :label="ctrans('Bad feedback')"
            :options="feedbackOptions"
            :selected="filters.feedback"
            @select="selectFeedback"
        />
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

    <div class="mx-4 mb-2 mt-3 flex flex-wrap items-center justify-between gap-2">
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

    <div v-if="!rows || !organisations" class="mx-4 mb-8 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="divide-y divide-gray-100">
            <div v-for="index in 12" :key="index" class="flex animate-pulse items-center gap-6 px-3 py-3">
                <div class="h-3 w-40 rounded bg-gray-200" />
                <div class="h-3 w-16 rounded bg-gray-100" />
                <div class="h-3 flex-1 rounded bg-gray-100" />
                <div class="h-5 w-24 rounded bg-gray-200" />
            </div>
        </div>
    </div>
    <div v-else class="compact-table mb-8">
            <div v-if="selectedRows.length" class="mx-4 mb-2 rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] px-4 py-2.5 text-xs text-gray-600" role="region" :aria-label="ctrans('Change status of the selected products')">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-gray-900">
                            {{ selectedRows.length === 1 ? ctrans("1 product selected") : ctrans(":count products selected", { count: selectedRows.length }) }}
                        </span>
                        <span v-for="summary in selectedStateSummary" :key="summary.state" class="rounded px-1.5 py-0.5 font-semibold" :class="statuses[summary.state]?.class">
                            {{ summary.count }} {{ statuses[summary.state]?.label ?? summary.state }}
                        </span>
                    </div>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <span class="font-medium text-gray-700">{{ ctrans("Change status to") }}</span>
                        <Select
                            v-model="bulkState"
                            :options="bulkStatusOptions"
                            optionLabel="label"
                            optionValue="value"
                            optionDisabled="disabled"
                            size="small"
                            class="w-40 text-xs"
                            :class="fieldClass"
                            :pt="rowSelectPt"
                            :placeholder="ctrans('Choose status')"
                            :aria-label="ctrans('Select new status for the selected products')"
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
                            :disabled="!bulkState || !bulkTargets.length"
                            @click="openBulkSet"
                        >
                            {{ bulkApplyLabel }}
                        </button>
                        <button type="button" class="rounded-md px-2 py-1.5 font-medium text-gray-600 transition-colors hover:bg-white hover:text-gray-900" @click="clearSelection">
                            {{ ctrans("Clear selection") }}
                        </button>
                    </div>
                </div>
                <p class="mt-1.5 text-gray-600">
                    {{ bulkHint }}
                </p>
            </div>
        <Table
            :key="tableKey"
            :resource="rows"
            :isCheckBox="selectableRows.length > 0"
            :disabledCheckbox="(row: Row) => !row.action"
            :isParentLoading="loading"
            @onSelectRow="onSelectRow"
        >
            <template #cell(code)="{ item: row }">
                <button type="button" class="group grid w-full max-w-[40rem] grid-cols-[6.5rem_minmax(0,1fr)] items-baseline gap-x-2 text-left" :title="`${row.code} — ${row.name ?? ''}${row.family_code ? ' (' + row.family_code + ')' : ''}`" @click="openDrawer(row)">
                    <span class="truncate font-medium text-gray-900 group-hover:underline">{{ row.code }}</span>
                    <span class="truncate">{{ row.name }}</span>
                </button>
            </template>
            <template #cell(sales)="{ item: row }">
                <span class="flex cursor-help items-center justify-end gap-1 whitespace-nowrap tabular-nums" @mouseenter="showSales($event, row)" @mouseleave="salesPopover?.hide()">
                    <span>{{ moneyInThousands(row.sales) }}</span>
                    <span class="w-12 text-right text-xs" :class="row.trend === null ? '' : row.trend >= 0 ? 'text-green-600' : 'text-red-600'">
                        <template v-if="row.trend !== null">{{ row.trend >= 0 ? "↑" : "↓" }}{{ Math.abs(row.trend) }}%</template>
                    </span>
                    <span class="ml-1 flex gap-0.5">
                        <span v-for="organisation in organisations" :key="organisation.code" class="h-2 w-2 rounded-full" :class="salesDot(row.organisations[organisation.code])" />
                    </span>
                </span>
            </template>
            <template #cell(stock)="{ item: row }">
                <span class="flex cursor-help items-center justify-end gap-2 whitespace-nowrap tabular-nums" @mouseenter="showStock($event, row)" @mouseleave="stockPopover?.hide()">
                    <span>
                        {{ quantity(Math.round(groupStock(row).available)) }}<span v-if="groupStock(row).inbound > 0" class="text-[11px] text-emerald-700">+{{ quantity(Math.round(groupStock(row).inbound)) }}</span>
                    </span>
                    <span class="flex gap-0.5">
                        <span
                            v-for="organisation in organisations"
                            :key="organisation.code"
                            class="h-2 w-2 rounded-full"
                            :class="row.organisations[organisation.code] ? conditionDots[row.organisations[organisation.code].condition] : 'bg-transparent ring-1 ring-inset ring-gray-200'"
                        />
                    </span>
                </span>
            </template>
            <template #cell(feedback)="{ item: row }">
                <div v-if="row.feedback_level || row.suggestions > 0" class="grid w-fit grid-cols-[4rem_3.25rem_2rem_auto_2.75rem_5rem] items-center whitespace-nowrap text-left text-xs tabular-nums">
                    <template v-if="row.feedback_level">
                        <span class="rounded py-0.5 text-center text-[10px] font-semibold uppercase" :class="feedbackLevels[row.feedback_level].class">{{ feedbackLevels[row.feedback_level].label }}</span>
                        <template v-if="row.complaints > 0">
                            <span v-tooltip="complaintTooltip(row)" class="cursor-help text-right font-semibold text-gray-800">{{ row.complaint_rate !== null ? row.complaint_rate.toFixed(1) + "%" : "" }}</span>
                            <span class="text-right text-gray-600">{{ quantity(row.complaint_customers) }}</span>
                            <span class="text-gray-400">/</span>
                            <span class="text-left text-gray-500">{{ quantity(row.orders) }}</span>
                        </template>
                        <span v-else v-tooltip="complaintTooltip(row)" class="col-span-4 cursor-help pl-2 text-gray-500">{{ ctrans(":count supplier claims", { count: quantity(row.supplier_claims) }) }}</span>
                    </template>
                    <span v-else class="col-span-5" />
                    <button
                        v-if="row.suggestions > 0"
                        v-tooltip="suggestionSummary(row.suggestions, row.suggestion_people)"
                        type="button"
                        class="rounded py-0.5 text-center text-[10px] font-semibold uppercase"
                        :class="row.suggestion_people >= SUGGESTION_PEOPLE_TO_ACT ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'"
                        @click="showSuggestions($event, row)"
                    >
                        <FontAwesomeIcon :icon="['fal', 'thumbs-down']" fixed-width aria-hidden="true" />
                        {{ row.suggestion_people === 1 ? ctrans("1 vote") : ctrans(":count votes", { count: quantity(row.suggestion_people) }) }}
                    </button>
                </div>
                <div v-else class="text-left text-gray-300">—</div>
            </template>
            <template #cell(state)="{ item: row }">
                        <div class="flex items-center gap-1.5">
                    <FontAwesomeIcon
                        v-tooltip="statuses[row.state]?.label ?? row.state"
                        :icon="stateIcons[row.state]?.icon ?? ['fas', 'exclamation-circle']"
                        :class="stateIcons[row.state]?.class ?? 'text-red-500'"
                        class="text-base"
                        fixed-width
                        :aria-label="statuses[row.state]?.label ?? row.state"
                    />
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
            </template>
        </Table>
    </div>

    <Popover ref="salesPopover" class="pointer-events-none">
        <table v-if="salesRow" class="text-xs tabular-nums text-gray-700">
            <caption class="pb-1 text-left text-sm font-semibold text-gray-900">{{ salesRow.code }} · {{ periodLabels[period] }}</caption>
            <thead class="text-left text-[10px] uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="pr-4 font-medium">{{ ctrans("Organisation") }}</th>
                    <th class="pr-4 text-right font-medium">{{ ctrans("Sales") }}</th>
                    <th class="text-right font-medium">{{ ctrans("vs period before") }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="organisation in organisations" :key="organisation.code">
                    <td class="py-0.5 pr-4" :title="organisation.name">
                        <span class="mr-1 inline-block h-2 w-2 rounded-full" :class="salesDot(salesRow.organisations[organisation.code])" />{{ organisation.code }}
                    </td>
                    <template v-if="salesRow.organisations[organisation.code]">
                        <td class="pr-4 text-right">{{ money(salesRow.organisations[organisation.code].sales ?? 0) }}</td>
                        <td class="text-right" :class="(salesRow.organisations[organisation.code].trend ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                            <template v-if="salesRow.organisations[organisation.code].trend != null">
                                {{ (salesRow.organisations[organisation.code].trend as number) >= 0 ? "↑" : "↓" }}{{ Math.abs(salesRow.organisations[organisation.code].trend as number) }}%
                            </template>
                        </td>
                    </template>
                    <td v-else colspan="2" class="text-gray-300">{{ ctrans("Not stocked") }}</td>
                </tr>
            </tbody>
        </table>
    </Popover>
    <Popover ref="stockPopover" class="pointer-events-none">
        <table v-if="stockRow" class="text-xs tabular-nums text-gray-700">
            <caption class="pb-1 text-left text-sm font-semibold text-gray-900">{{ stockRow.code }}</caption>
            <thead class="text-left text-[10px] uppercase tracking-wide text-gray-400">
                <tr>
                    <th class="pr-4 font-medium">{{ ctrans("Organisation") }}</th>
                    <th class="pr-4 text-right font-medium">{{ ctrans("Available") }}</th>
                    <th class="pr-4 text-right font-medium">{{ ctrans("On the way") }}</th>
                    <th class="font-medium">{{ ctrans("Cover") }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="organisation in organisations" :key="organisation.code">
                    <td class="pr-4 py-0.5" :title="organisation.name">{{ organisation.code }}</td>
                    <template v-if="stockRow.organisations[organisation.code]">
                        <td class="pr-4 text-right">{{ quantity(Math.round(stockRow.organisations[organisation.code].available)) }}</td>
                        <td class="pr-4 text-right text-emerald-700">
                            <template v-if="stockRow.organisations[organisation.code].inbound > 0">
                                +{{ quantity(Math.round(stockRow.organisations[organisation.code].inbound)) }}
                                <span v-if="stockRow.organisations[organisation.code].next_expected_at" class="text-gray-400">{{ shortDate(stockRow.organisations[organisation.code].next_expected_at as string) }}</span>
                            </template>
                        </td>
                        <td>
                            <span class="inline-block w-14 rounded py-0.5 text-center text-[10px] font-semibold" :class="conditions[stockRow.organisations[organisation.code].condition].class">
                                {{ cellTag(stockRow.organisations[organisation.code]) }}
                            </span>
                        </td>
                    </template>
                    <td v-else colspan="3" class="text-gray-300">{{ ctrans("Not stocked") }}</td>
                </tr>
            </tbody>
        </table>
    </Popover>
    <Popover ref="suggestionPopover">
        <div v-if="suggestionRow?.suggestion_evidence" class="w-96 max-w-[calc(100vw-3rem)] text-xs text-gray-700">
            <div class="text-sm font-semibold text-gray-900">{{ suggestionRow.code }}</div>
            <div class="text-gray-500">{{ suggestionSummary(suggestionRow.suggestions, suggestionRow.suggestion_people) }}</div>
            <div class="mt-1 text-gray-500">
                <span v-for="(source, index) in suggestionRow.suggestion_evidence.sources" :key="source.label">{{ index ? " · " : "" }}{{ source.label }} {{ quantity(source.times) }}</span>
            </div>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <span v-for="reason in suggestionRow.suggestion_evidence.reasons" :key="reason.label" class="rounded bg-gray-100 px-1.5 py-0.5 font-medium">
                    {{ reason.label }} <span class="tabular-nums text-gray-500">{{ quantity(reason.times) }}</span>
                </span>
            </div>
            <div class="mt-2 max-h-72 divide-y divide-gray-100 overflow-y-auto border-t border-gray-100">
                <div v-for="(note, index) in suggestionRow.suggestion_evidence.notes" :key="index" class="py-1.5">
                    <div class="flex items-baseline justify-between gap-2 text-gray-500">
                        <span><span class="font-medium text-gray-800">{{ note.user ?? ctrans("Unknown") }}</span> · {{ note.source }} · {{ note.organisation }}</span>
                        <span class="shrink-0">{{ useFormatTime(note.date) }}</span>
                    </div>
                    <div class="text-gray-500">{{ note.reasons.join(", ") }}</div>
                    <div class="whitespace-pre-line text-gray-800">{{ note.note }}</div>
                </div>
            </div>
        </div>
    </Popover>
    <OrgStockDiscontinuePreviewModal
        v-if="modal"
        :isOpen="!!modal"
        :orgStockIds="modal.rows.map((row) => row.action!.org_stock_id)"
        :initialState="modal.state"
        :previewRoute="previewRoute"
        :discontinueRoute="discontinueRoute"
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

<style scoped>
.compact-table :deep(tbody td) {
    padding: 0.35rem 1rem !important;
}

.compact-table :deep(thead th) {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

.compact-table :deep(tbody td:first-child:has(> svg)),
.compact-table :deep(thead th:first-child:has(svg)) {
    width: 1.75rem;
    padding: 0 0 0 0.4rem !important;
}

.compact-table :deep(tbody td:first-child > svg) {
    padding: 0;
}
</style>
