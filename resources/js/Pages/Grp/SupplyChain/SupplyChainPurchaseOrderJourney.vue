<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref, shallowRef, watch } from "vue"
import { useScrollArrows } from "@/Composables/useScrollArrows"
import { useElementSize } from "@vueuse/core"
import ScrollFadeArrow from "@/Components/Utils/ScrollFadeArrow.vue"
import { Popover, Select, SelectButton, Checkbox, IconField, InputIcon, InputText } from "primevue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import PurchaseOrderJourneyRibbon, { type JourneyRibbon, type JourneySegment } from "@/Components/SupplyChain/PurchaseOrderJourneyRibbon.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRoute, faExclamationTriangle, faStopwatch, faHourglassHalf, faCoins, faCalendarCheck, faSearch, faExpandAlt, faCompressAlt, faChevronDown, faSpinner } from "@fal"

library.add(faRoute, faExclamationTriangle, faStopwatch, faHourglassHalf, faCoins, faCalendarCheck, faSearch, faExpandAlt, faCompressAlt, faChevronDown, faSpinner)

interface FacetOption {
    value: string
    label: string
    count: number
}

type FilterGroup = "organisation" | "journey" | "agent" | "supplier" | "buyer" | "type" | "country" | "stage" | "status"

const props = defineProps<{
    view: "supplier_orders" | "purchase_orders"
    title: string
    pageHead: object
    groupCurrency: string
    canMark: boolean
    stages: { key: string; label: string; description: string; markable: boolean }[]
    filters?: Record<FilterGroup, FacetOption[]>
    active?: Record<FilterGroup, string | null> & { problems_only: boolean; search: string | null }
    summary?: {
        open: number
        open_value: number
        on_track: number
        on_track_percent: number
        at_risk: number
        at_risk_percent: number
        overdue: number
        overdue_percent: number
        completed: number
    }
    blockages?: { stage: string; label: string; count: number; max_days_overdue: number }[]
    quickStats?: {
        average_lead_days: number | null
        oldest_open_days: number | null
        open_value: number
        due_30_days: number
        due_30_days_value: number
    }
    ribbons?: JourneyRibbon[]
    pagination?: { page: number; last_page: number; total: number; per_page: number }
}>()

const isTableExpanded = ref(false)
const tableScroller = ref<HTMLElement | null>(null)
const { canScrollUp, canScrollDown, scrollVerticallyBy } = useScrollArrows(tableScroller)
const tableHead = ref<HTMLElement | null>(null)
const { height: tableHeadHeight } = useElementSize(tableHead, undefined, { box: "border-box" })

function lastLoaded<T>(source: () => T | undefined) {
    const value = shallowRef<T | undefined>(source())
    watch(source, (incoming) => {
        if (incoming !== undefined) {
            value.value = incoming
        }
    })
    return value
}

const filters = lastLoaded(() => props.filters)
const active = lastLoaded(() => props.active)
const summary = lastLoaded(() => props.summary)
const blockages = lastLoaded(() => props.blockages)
const quickStats = lastLoaded(() => props.quickStats)
const ribbons = lastLoaded(() => props.ribbons)
const pagination = lastLoaded(() => props.pagination)

const isLoaded = computed(() => !!summary.value && !!filters.value && !!active.value)

const viewOptions = computed(() => [
    { value: "supplier_orders", label: ctrans("Orders to suppliers") },
    { value: "purchase_orders", label: ctrans("Agent POs") }
])

const dropdownOptions = (group: FilterGroup) => [
    { value: null, label: ctrans("All") },
    ...(filters.value?.[group] ?? []).map((option) => ({ value: option.value, label: `${option.label} (${option.count})` }))
]

const inlineFilters = computed<{ group: FilterGroup; label: string }[]>(() => [
    { group: "organisation", label: ctrans("AW company") },
    { group: "journey", label: ctrans("Journey") },
    { group: "type", label: ctrans("PO type") },
    { group: "status", label: ctrans("Status") }
])

const dropdownFilters = computed<{ group: FilterGroup; label: string }[]>(() => [
    { group: "agent", label: ctrans("Agent") },
    { group: "buyer", label: ctrans("PO creator / buyer") },
    { group: "supplier", label: ctrans("Supplier") },
    { group: "country", label: ctrans("Country") },
    { group: "stage", label: ctrans("Current stage") }
])

const search = ref(active.value?.search ?? "")

watch(
    () => active.value?.search,
    (value) => {
        if ((value ?? "") !== search.value.trim()) {
            search.value = value ?? ""
        }
    }
)

const isChangingPage = ref(false)

function visit(changes: Record<string, string | number | boolean | null>): void {
    const params: Record<string, string | number | boolean> = {}
    const merged = { ...active.value, view: props.view === "supplier_orders" ? null : props.view, page: null, ...changes }
    for (const [key, value] of Object.entries(merged)) {
        if (value !== null && value !== "" && value !== false) {
            params[key] = value
        }
    }
    router.get(route("grp.supply-chain.dashboard"), params, {
        only: ["view", "filters", "active", "summary", "blockages", "quickStats", "ribbons", "pagination"],
        preserveState: true,
        preserveScroll: true,
        onStart: () => (isChangingPage.value = true),
        onFinish: () => (isChangingPage.value = false)
    })
}

function changePage(page: number): void {
    const params = new URLSearchParams(window.location.search)
    params.set("page", String(page))
    router.get(route("grp.supply-chain.dashboard"), Object.fromEntries(params), {
        only: ["ribbons", "pagination"],
        preserveState: true,
        preserveScroll: true,
        onStart: () => (isChangingPage.value = true),
        onFinish: () => {
            isChangingPage.value = false
            tableScroller.value?.scrollTo({ top: 0 })
        }
    })
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => visit({ search: value.trim() || null }), 400)
})

function money(value: number, compact = false): string {
    return new Intl.NumberFormat("en-GB", {
        style: "currency",
        currency: props.groupCurrency,
        maximumFractionDigits: compact ? 2 : 0,
        notation: compact ? "compact" : "standard"
    }).format(value)
}

const kpis = computed(() => !summary.value ? [] : [
    { label: ctrans("Open orders"), value: summary.value.open.toLocaleString(), note: "", class: "text-gray-900", filter: { status: null, problems_only: false } },
    { label: ctrans("Total PO value"), value: money(summary.value.open_value, true), note: ctrans("open orders"), class: "text-gray-900", filter: null },
    { label: ctrans("On track"), value: summary.value.on_track, note: `${summary.value.on_track_percent}% ${ctrans("of open orders")}`, class: "text-emerald-600", filter: { status: "on_track" } },
    { label: ctrans("At risk"), value: summary.value.at_risk, note: `${summary.value.at_risk_percent}% ${ctrans("of open orders")}`, class: "text-amber-500", filter: { status: "at_risk" } },
    { label: ctrans("Overdue"), value: summary.value.overdue, note: `${summary.value.overdue_percent}% ${ctrans("of open orders")}`, class: "text-red-600", filter: { status: "overdue" } },
    { label: ctrans("Completed"), value: summary.value.completed, note: ctrans("last 60 days"), class: "text-blue-600", filter: { status: "completed" } }
])

const legend = [
    { label: ctrans("Completed"), class: "bg-emerald-200" },
    { label: ctrans("Done, date not recorded"), class: "bg-emerald-100" },
    { label: ctrans("Current stage"), class: "bg-blue-500" },
    { label: ctrans("At risk (due in 3 days)"), class: "bg-amber-400" },
    { label: ctrans("Overdue"), class: "bg-red-500" },
    { label: ctrans("Planned (struck through once the plan date has passed)"), class: "bg-gray-100 border border-gray-200" },
    { label: ctrans("Not recorded"), class: "bg-slate-50 border border-slate-200" }
]

const isKpiSelected = (filter: Record<string, string | boolean | null> | null) => !!filter?.status && filter.status === active.value?.status

const markPopover = ref()
const marking = ref<{ ribbon: JourneyRibbon; segment: JourneySegment } | null>(null)
const markDate = ref("")
const markProcessing = ref(false)

function showFilter(group: FilterGroup): boolean {
    if (!active.value || !filters.value) {
        return false
    }
    if (active.value[group] !== null) {
        return true
    }
    if (group === "country" && active.value.agent !== null) {
        return false
    }
    return filters.value[group].length > 1
}

function openMark(ribbon: JourneyRibbon, segment: JourneySegment, event: MouseEvent): void {
    marking.value = { ribbon, segment }
    markDate.value = segment.done_at ?? new Date().toISOString().slice(0, 10)
    markPopover.value?.toggle(event)
}

function saveMark(date: string | null): void {
    if (!marking.value) {
        return
    }
    router.patch(
        route(marking.value.ribbon.mark_route.name, marking.value.ribbon.mark_route.parameters),
        { stage: marking.value.segment.key, date },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (markProcessing.value = true),
            onFinish: () => {
                markProcessing.value = false
                markPopover.value?.hide()
            }
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <template v-if="isLoaded && summary && filters && active && blockages && quickStats && ribbons && pagination">
    <div class="mx-4 mt-3 grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 xl:grid-cols-6" :class="{ 'opacity-60': isChangingPage }">
        <button
            v-for="kpi in kpis"
            :key="kpi.label"
            type="button"
            class="rounded-lg border bg-white px-3 py-2 text-left transition"
            :class="[
                isKpiSelected(kpi.filter) ? 'border-[--app-accent] bg-[--app-accent-soft] ring-1 ring-[--app-accent]' : 'border-gray-200',
                !kpi.filter ? 'cursor-default' : !isKpiSelected(kpi.filter) && 'hover:border-gray-300 hover:shadow-sm'
            ]"
            :aria-pressed="isKpiSelected(kpi.filter)"
            @click="kpi.filter && visit(kpi.filter)">
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-semibold" :class="kpi.class">{{ kpi.value }}</span>
                <span class="text-sm text-gray-600">{{ kpi.label }}</span>
            </div>
            <div class="text-xs text-gray-400">{{ kpi.note }}</div>
        </button>
    </div>

    <div class="mx-4 mt-3 flex flex-wrap items-center gap-2">
        <SelectButton
            :modelValue="view"
            :options="viewOptions"
            optionLabel="label"
            optionValue="value"
            :allowEmpty="false"
            size="small"
            class="journey-view-toggle"
            @update:modelValue="(value) => visit({ view: value === 'supplier_orders' ? null : value })" />
        <template v-for="filter in inlineFilters" :key="filter.group">
        <PillFilterBar
            v-if="showFilter(filter.group)"
            :label="filter.label"
            :options="filters[filter.group]"
            :selected="active[filter.group]"
            @select="(value) => visit({ [filter.group]: value })" />
        </template>
    </div>

    <div class="mx-4 mt-2 flex flex-wrap items-center gap-2">
        <template v-for="filter in dropdownFilters" :key="filter.group">
            <div v-if="showFilter(filter.group)" class="flex items-center gap-1.5">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ filter.label }}</span>
                <Select
                    :modelValue="active[filter.group]"
                    :options="dropdownOptions(filter.group)"
                    optionLabel="label"
                    optionValue="value"
                    :filter="filters[filter.group].length > 8"
                    size="small"
                    class="journey-select min-w-[9rem]"
                    :class="{ 'is-active': active[filter.group] !== null }"
                    @update:modelValue="(value) => visit({ [filter.group]: value })" />
            </div>
        </template>
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-2.5 py-1 text-xs" :class="active.problems_only ? 'border-red-300 bg-red-50 text-red-700' : 'border-gray-200 bg-white text-gray-600'">
            <Checkbox :modelValue="active.problems_only" binary size="small" class="journey-checkbox" @update:modelValue="(value) => visit({ problems_only: value })" />
            {{ ctrans("Problems only") }}
        </label>
        <IconField class="min-w-[14rem] flex-1 sm:max-w-xs">
            <InputIcon>
                <FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" />
            </InputIcon>
            <InputText v-model="search" size="small" class="journey-search w-full" :placeholder="ctrans('Search PO, supplier, agent, buyer')" />
        </IconField>
    </div>

    <div class="mx-4 mb-6 mt-3 grid gap-4 2xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 rounded-lg border border-gray-200 bg-white">
            <div class="flex items-stretch border-b border-gray-200 text-xs text-gray-500">
                <div class="flex min-w-0 flex-1 flex-wrap content-center items-center gap-x-4 gap-y-1.5 px-3 py-2">
                    <span v-for="item in legend" :key="item.label" class="flex items-center gap-1.5">
                        <span class="inline-block h-3 w-3 shrink-0 rounded-sm" :class="item.class" />
                        {{ item.label }}
                    </span>
                </div>
                <div class="flex shrink-0 flex-col items-end justify-center gap-1.5 border-l border-gray-200 px-3 py-2">
                <button
                    type="button"
                    v-tooltip="isTableExpanded ? ctrans('Limit table height') : ctrans('Show the whole table')"
                    class="flex items-center gap-1.5 rounded border border-gray-200 px-2 py-0.5 hover:border-[--app-accent] hover:text-[--app-accent]"
                    @click="isTableExpanded = !isTableExpanded">
                    <FontAwesomeIcon :icon="isTableExpanded ? 'fal fa-compress-alt' : 'fal fa-expand-alt'" fixed-width aria-hidden="true" />
                    {{ isTableExpanded ? ctrans("Collapse") : ctrans("Expand") }}
                </button>
                <div class="flex items-center gap-2 whitespace-nowrap">
                    <FontAwesomeIcon v-if="isChangingPage" icon="fal fa-spinner" spin class="text-[--app-accent]" fixed-width />
                    <span>{{ ctrans("Page :page of :last · :total orders", { page: pagination.page, last: pagination.last_page, total: pagination.total }) }}</span>
                    <button type="button" class="rounded border border-gray-200 px-2 py-0.5 hover:border-[--app-accent] disabled:opacity-40" :disabled="isChangingPage || pagination.page <= 1" @click="changePage(pagination.page - 1)">‹</button>
                    <button type="button" class="rounded border border-gray-200 px-2 py-0.5 hover:border-[--app-accent] disabled:opacity-40" :disabled="isChangingPage || pagination.page >= pagination.last_page" @click="changePage(pagination.page + 1)">›</button>
                </div>
                </div>
            </div>
            <div class="relative isolate">
            <div ref="tableScroller" class="journey-table-scroller overflow-auto transition-opacity" :class="{ 'max-h-[80vh]': !isTableExpanded, 'pointer-events-none opacity-50': isChangingPage }">
                <table class="journey-table w-full min-w-[72rem] border-collapse">
                    <thead ref="tableHead" class="sticky top-0 z-[2] bg-white">
                        <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500">
                            <th class="py-2 pl-3 pr-4">{{ ctrans("PO details") }}</th>
                            <th v-for="stage in stages" :key="stage.key" class="px-1 py-2 text-center" :title="stage.description">{{ stage.label }}</th>
                            <th class="py-2 pl-4 pr-3">{{ ctrans("Status") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <PurchaseOrderJourneyRibbon
                            v-for="ribbon in ribbons"
                            :key="ribbon.key"
                            :ribbon="ribbon"
                            :stages="stages"
                            :group-currency="groupCurrency"
                            :can-mark="canMark"
                            @mark="openMark" />
                    </tbody>
                </table>
            </div>
            <div v-if="isChangingPage" class="pointer-events-none absolute inset-0 z-[3] flex items-start justify-center pt-24">
                <span class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-600 shadow-md">
                    <FontAwesomeIcon icon="fal fa-spinner" spin class="text-[--app-accent]" fixed-width />
                    {{ ctrans("Loading…") }}
                </span>
            </div>
            <div v-if="!isTableExpanded" class="pointer-events-none absolute inset-x-0 bottom-0" :style="{ top: tableHeadHeight + 'px' }">
                <ScrollFadeArrow direction="up" :visible="canScrollUp" @click="scrollVerticallyBy(-1)" />
                <ScrollFadeArrow direction="down" :visible="canScrollDown" @click="scrollVerticallyBy(1)" />
            </div>
            </div>
            <div v-if="!ribbons.length" class="py-10 text-center text-sm text-gray-500">
                {{ ctrans("No purchase orders match these filters.") }}
            </div>
        </div>

        <div class="order-first grid gap-4 transition-opacity md:grid-cols-2 2xl:order-none 2xl:grid-cols-1 2xl:content-start" :class="{ 'opacity-60': isChangingPage }">
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-800">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-500" fixed-width aria-hidden="true" />
                    {{ ctrans("Urgent blockages") }}
                </div>
                <button
                    v-for="blockage in blockages"
                    :key="blockage.stage"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-md px-1 py-1.5 text-left hover:bg-gray-50"
                    @click="visit({ stage: blockage.stage, status: 'overdue' })">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-red-50 text-sm font-semibold text-red-600">{{ blockage.count }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm text-gray-800">{{ blockage.label }}</span>
                        <span class="block text-xs text-gray-500">{{ ctrans("up to :days days overdue", { days: blockage.max_days_overdue }) }}</span>
                    </span>
                </button>
                <div v-if="!blockages.length" class="text-sm text-gray-500">{{ ctrans("Nothing overdue") }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm">
                <div class="mb-2 font-semibold text-gray-800">{{ ctrans("Quick stats") }}</div>
                <div class="flex items-center gap-3 py-1.5">
                    <FontAwesomeIcon icon="fal fa-stopwatch" class="text-gray-400" fixed-width aria-hidden="true" />
                    <div>
                        <div class="text-xs text-gray-500">{{ ctrans("Average lead time, created to placed") }}</div>
                        <div class="font-semibold">{{ quickStats.average_lead_days !== null ? ctrans(":days days", { days: quickStats.average_lead_days }) : "—" }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 py-1.5">
                    <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-gray-400" fixed-width aria-hidden="true" />
                    <div>
                        <div class="text-xs text-gray-500">{{ ctrans("Oldest open order") }}</div>
                        <div class="font-semibold">{{ quickStats.oldest_open_days !== null ? ctrans(":days days", { days: quickStats.oldest_open_days }) : "—" }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 py-1.5">
                    <FontAwesomeIcon icon="fal fa-coins" class="text-gray-400" fixed-width aria-hidden="true" />
                    <div>
                        <div class="text-xs text-gray-500">{{ ctrans("Total on order") }}</div>
                        <div class="font-semibold">{{ money(quickStats.open_value) }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 py-1.5">
                    <FontAwesomeIcon icon="fal fa-calendar-check" class="text-gray-400" fixed-width aria-hidden="true" />
                    <div>
                        <div class="text-xs text-gray-500">{{ ctrans("Expected to finish in 30 days") }}</div>
                        <div class="font-semibold">{{ ctrans(":count orders", { count: quickStats.due_30_days }) }} · {{ money(quickStats.due_30_days_value) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </template>

    <div v-else class="animate-pulse">
        <div class="mx-4 mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <div v-for="skeletonKpi in 6" :key="skeletonKpi" class="h-[3.75rem] rounded-lg border border-gray-200 bg-white px-3 py-2">
                <div class="h-5 w-2/3 rounded bg-gray-200" />
                <div class="mt-2 h-3 w-1/2 rounded bg-gray-100" />
            </div>
        </div>
        <div class="mx-4 mt-3 flex flex-wrap gap-2">
            <div v-for="skeletonPill in [10, 16, 12, 9, 14]" :key="skeletonPill" class="h-8 rounded-md bg-gray-200" :style="{ width: skeletonPill + 'rem' }" />
        </div>
        <div class="mx-4 mt-2 flex flex-wrap gap-2">
            <div v-for="skeletonFilter in [8, 9, 8, 10, 7, 18]" :key="skeletonFilter" class="h-8 rounded-md bg-gray-200" :style="{ width: skeletonFilter + 'rem' }" />
        </div>
        <div class="mx-4 mb-6 mt-3 grid gap-4 2xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-lg border border-gray-200 bg-white">
                <div class="h-9 border-b border-gray-200 bg-gray-50" />
                <div v-for="skeletonRow in 8" :key="skeletonRow" class="flex items-center gap-3 border-b border-gray-100 px-3 py-3">
                    <div class="w-48 space-y-1.5">
                        <div class="h-3.5 w-3/4 rounded bg-gray-200" />
                        <div class="h-3 w-1/2 rounded bg-gray-100" />
                    </div>
                    <div class="h-5 flex-1 rounded bg-gray-100" />
                    <div class="h-5 w-16 rounded-full bg-gray-200" />
                </div>
            </div>
            <div class="order-first grid gap-4 md:grid-cols-2 2xl:order-none 2xl:grid-cols-1 2xl:content-start">
                <div v-for="skeletonCard in 2" :key="skeletonCard" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                    <div class="h-4 w-1/3 rounded bg-gray-200" />
                    <div v-for="skeletonLine in 4" :key="skeletonLine" class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-md bg-gray-200" />
                        <div class="flex-1 space-y-1.5">
                            <div class="h-3.5 w-2/3 rounded bg-gray-200" />
                            <div class="h-3 w-1/2 rounded bg-gray-100" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Popover ref="markPopover">
        <div v-if="marking" class="w-64 text-sm">
            <div class="font-semibold text-gray-800">{{ marking.ribbon.reference }} · {{ marking.segment.label }}</div>
            <div class="mb-2 text-xs text-gray-500">{{ marking.segment.description }}</div>
            <div v-if="marking.segment.done_at" class="mb-2 text-xs text-emerald-700">
                {{ ctrans("Done on :date", { date: useFormatTime(marking.segment.done_at) }) }}
            </div>
            <input v-model="markDate" type="date" class="w-full rounded-md border-gray-300 text-sm" />
            <div class="mt-3 flex justify-end gap-2">
                <button
                    v-if="marking.segment.done_at"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-gray-600 hover:bg-gray-100"
                    :disabled="markProcessing"
                    @click="saveMark(null)">
                    {{ ctrans("Clear") }}
                </button>
                <button
                    type="button"
                    class="rounded-md bg-[--app-accent] px-3 py-1.5 text-[--app-accent-text] disabled:opacity-50"
                    :disabled="markProcessing || !markDate"
                    @click="saveMark(markDate)">
                    {{ ctrans("Mark done") }}
                </button>
            </div>
        </div>
    </Popover>
</template>

<style scoped>
.journey-table thead th:first-child,
.journey-table :deep(tbody td:first-child) {
    position: sticky;
    left: 0;
    z-index: 1;
    background-color: white;
    box-shadow: 1px 0 0 #e5e7eb;
}

.journey-table thead th {
    background-color: white;
    box-shadow: inset 0 -2px 0 #d1d5db;
}

.journey-table thead th:first-child {
    z-index: 3;
    box-shadow: inset 0 -2px 0 #d1d5db, 1px 0 0 #e5e7eb;
}

.journey-view-toggle :deep(.p-togglebutton-checked .p-togglebutton-content) {
    background-color: var(--app-accent);
    color: var(--app-accent-text);
}

.journey-select {
    font-size: 0.75rem;
}

.journey-select.is-active {
    border-color: var(--app-accent);
}

.journey-select :deep(.p-select-label) {
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}

.journey-checkbox.p-checkbox-checked :deep(.p-checkbox-box) {
    background-color: var(--app-accent);
    border-color: var(--app-accent);
}

.journey-search {
    font-size: 0.75rem;
}

.journey-search:focus,
.journey-select:deep(.p-focus) {
    border-color: var(--app-accent);
}
</style>
