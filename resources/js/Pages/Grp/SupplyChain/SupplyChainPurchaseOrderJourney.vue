<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref, shallowRef, watch } from "vue"
import { Popover, Select, IconField, InputIcon, InputText, DatePicker, Textarea } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PurchaseOrderJourneyRibbon, { type JourneyRibbon, type JourneySegment } from "@/Components/SupplyChain/PurchaseOrderJourneyRibbon.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRoute, faExclamationTriangle, faStopwatch, faHourglassHalf, faCoins, faCalendarCheck, faSearch, faCheck, faCircle, faEraser, faGlobe, faRocket, faIndustryAlt, faClipboardCheck, faBox, faShip, faWarehouse, faCheckCircle } from "@fal"

library.add(faRoute, faExclamationTriangle, faStopwatch, faHourglassHalf, faCoins, faCalendarCheck, faSearch, faCheck, faCircle, faEraser, faGlobe, faRocket, faIndustryAlt, faClipboardCheck, faBox, faShip, faWarehouse, faCheckCircle)

interface FacetOption {
    value: string
    label: string
    count: number
}

type FilterGroup = "organisation" | "journey" | "agent" | "supplier" | "buyer" | "type" | "country" | "stage" | "status"
type Tab = "open" | "needs_action" | "import" | "completed"
type Sort = "needs_action" | "newest" | "oldest" | "value" | "lowest_value"

const props = defineProps<{
    title: string
    pageHead: object
    groupCurrency: string
    canMark: boolean
    stages: { key: string; label: string; description: string; markable: boolean }[]
    filters?: Record<FilterGroup, FacetOption[]>
    active?: Record<FilterGroup, string | null> & { tab: Tab; sort: Sort; per_page: number; search: string | null }
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
        needs_action: number
        import: number
    }
    blockages?: { stage: string; label: string; count: number; max_days_overdue: number }[]
    quickStats?: {
        average_lead_days: number | null
        average_online_days?: number | null
        oldest_open_days: number | null
        open_value: number
        due_30_days: number
        due_30_days_value: number
    }
    ribbons?: JourneyRibbon[]
    pagination?: { page: number; last_page: number; total: number; per_page: number }
}>()

const expandAll = ref(false)

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

const dropdownOptions = (group: FilterGroup) => [
    { value: null, label: ctrans("All") },
    ...(filters.value?.[group] ?? []).map((option) => ({ value: option.value, label: `${option.label} (${option.count})` }))
]

const dropdownFilters = computed<{ group: FilterGroup; label: string }[]>(() => [
    { group: "organisation", label: ctrans("Organisation") },
    { group: "buyer", label: ctrans("Buyer") },
    { group: "agent", label: ctrans("Agent") },
    { group: "country", label: ctrans("Country") },
    { group: "stage", label: ctrans("Current stage") },
    { group: "status", label: ctrans("Status") },
    { group: "supplier", label: ctrans("Supplier") },
    { group: "journey", label: ctrans("Journey") },
    { group: "type", label: ctrans("PO type") }
])

const tabs = computed<{ key: Tab; label: string; count: number; tooltip?: string }[]>(() => !summary.value ? [] : [
    { key: "open", label: ctrans("Open"), count: summary.value.open },
    { key: "needs_action", label: ctrans("Needs action"), count: summary.value.needs_action },
    { key: "import", label: ctrans("Import"), count: summary.value.import, tooltip: ctrans("Orders submitted to suppliers outside our customs area (UK, or EU for EU organisations), not yet completed. Orders still in process are left out") },
    { key: "completed", label: ctrans("Completed"), count: summary.value.completed }
])

const sortOptions = computed(() => [
    { value: "needs_action", label: ctrans("Needs action first") },
    { value: "newest", label: ctrans("Newest first") },
    { value: "oldest", label: ctrans("Oldest first") },
    { value: "value", label: ctrans("Highest value first") },
    { value: "lowest_value", label: ctrans("Lowest value first") }
])

const perPageOptions = [10, 25, 50, 100]

const stageIcons: Record<string, string> = {
    submitted: "fal fa-rocket",
    deposit_paid: "fal fa-coins",
    in_production: "fal fa-industry-alt",
    ready: "fal fa-clipboard-check",
    handed_over: "fal fa-box",
    arrived: "fal fa-ship",
    received: "fal fa-warehouse",
    products_online: "fal fa-check-circle"
}

const hasActiveFilters = computed(() => !!active.value && (!!active.value.search || dropdownFilters.value.some((filter) => active.value![filter.group] !== null)))

function resetFilters(): void {
    search.value = ""
    visit(Object.fromEntries([...dropdownFilters.value.map((filter) => [filter.group, null]), ["search", null]]))
}

const shownRange = computed(() => {
    if (!pagination.value || !pagination.value.total) {
        return { from: 0, to: 0 }
    }
    const from = (pagination.value.page - 1) * pagination.value.per_page + 1
    return { from, to: Math.min(pagination.value.total, from + pagination.value.per_page - 1) }
})

const pageNumbers = computed<(number | null)[]>(() => {
    if (!pagination.value) {
        return []
    }
    const { page, last_page: last } = pagination.value
    const wanted = [...new Set([1, page - 1, page, page + 1, last])].filter((number) => number >= 1 && number <= last).sort((a, b) => a - b)
    return wanted.flatMap((number, index) => (index && number - wanted[index - 1] > 1 ? [null, number] : [number]))
})

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
    const merged = { ...active.value, page: null, ...changes }
    const defaults: Record<string, string | number> = { tab: "open", sort: "needs_action", per_page: 25 }
    for (const [key, value] of Object.entries(merged)) {
        if (value !== null && value !== "" && value !== false && defaults[key] !== value) {
            params[key] = value
        }
    }
    router.get(window.location.pathname, params, {
        only: ["filters", "active", "summary", "blockages", "quickStats", "ribbons", "pagination"],
        preserveState: true,
        preserveScroll: true,
        onStart: () => (isChangingPage.value = true),
        onFinish: () => (isChangingPage.value = false)
    })
}

function changePage(page: number, perPage?: number): void {
    const params = new URLSearchParams(window.location.search)
    params.set("page", String(page))
    if (perPage) {
        params.set("per_page", String(perPage))
    }
    router.get(window.location.pathname, Object.fromEntries(params), {
        only: ["ribbons", "pagination"],
        preserveState: true,
        preserveScroll: true,
        onStart: () => (isChangingPage.value = true),
        onFinish: () => (isChangingPage.value = false)
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

const legend = [
    { label: ctrans("Complete"), class: "border-emerald-300 bg-emerald-50" },
    { label: ctrans("Current"), class: "border-2 border-gray-700 bg-white" },
    { label: ctrans("At risk (due in 3 days)"), class: "border-2 border-amber-400 bg-amber-50" },
    { label: ctrans("Target missed"), class: "border-2 border-red-400 bg-red-50" },
    { label: ctrans("Upcoming (struck through once the target has passed)"), class: "border-gray-200 bg-gray-50" },
    { label: ctrans("Not recorded"), class: "border-dashed border-gray-300 bg-white" }
]

const headerScroller = ref<HTMLElement | null>(null)
const bodyScroller = ref<HTMLElement | null>(null)
const followedScrollLeft = new WeakMap<HTMLElement, number>()

function syncScroll(event: Event, other: HTMLElement | null): void {
    const scroller = event.target as HTMLElement
    if (followedScrollLeft.get(scroller) === scroller.scrollLeft) {
        followedScrollLeft.delete(scroller)
        return
    }
    if (other && other.scrollLeft !== scroller.scrollLeft) {
        followedScrollLeft.set(other, scroller.scrollLeft)
        other.scrollLeft = scroller.scrollLeft
    }
}

const markPopover = ref()
const marking = ref<{ ribbon: JourneyRibbon; segment: JourneySegment } | null>(null)
const markDate = ref<Date | null>(null)
const markReason = ref("")
const markErrors = ref<Record<string, string>>({})
const markProcessing = ref(false)

const toIso = (date: Date | null) => date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null

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
    markDate.value = segment.done_at ? new Date(segment.done_at) : new Date()
    markReason.value = ""
    markErrors.value = {}
    markPopover.value?.toggle(event)
}

function saveMark(date: string | null): void {
    if (!marking.value) {
        return
    }
    router.patch(
        route(marking.value.ribbon.mark_route!.name, marking.value.ribbon.mark_route!.parameters),
        { stage: marking.value.segment.key, date, reason: markReason.value.trim() || null },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (markProcessing.value = true),
            onError: (errors) => (markErrors.value = errors),
            onSuccess: () => markPopover.value?.hide(),
            onFinish: () => (markProcessing.value = false)
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <template v-if="isLoaded && summary && filters && active && blockages && quickStats && ribbons && pagination">
    <div class="mx-4 mt-2 flex flex-wrap gap-x-6 border-b border-gray-200" role="tablist">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="active.tab === tab.key"
            v-tooltip="tab.tooltip"
            class="-mb-px flex items-center gap-2 border-b-2 px-1 py-2 text-sm font-semibold"
            :class="active.tab === tab.key ? 'border-gray-800 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
            @click="visit({ tab: tab.key === 'open' ? null : tab.key })">
            {{ tab.label }}
            <span class="rounded-md px-1.5 text-xs" :class="active.tab === tab.key ? 'bg-gray-200 text-gray-800' : 'bg-gray-100 text-gray-600'">{{ tab.count.toLocaleString() }}</span>
        </button>
        <span class="ml-auto self-center text-xs text-gray-500">
            {{ money(summary.open_value, true) }} {{ ctrans("on open orders") }}
        </span>
    </div>

    <div class="mx-4 mt-3 flex flex-wrap items-center gap-2">
        <IconField class="min-w-[14rem] flex-1 sm:max-w-md">
            <InputIcon>
                <FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" />
            </InputIcon>
            <InputText v-model="search" size="small" class="journey-search w-full" :placeholder="ctrans('Search PO, supplier, agent, buyer')" />
        </IconField>
        <button v-if="hasActiveFilters" type="button" class="text-sm text-gray-700 underline hover:text-gray-900" @click="resetFilters">
            {{ ctrans("Reset filters") }}
        </button>
    </div>

    <div class="mx-4 mt-2 flex flex-wrap items-center gap-2">
        <template v-for="filter in dropdownFilters" :key="filter.group">
            <Select
                v-if="showFilter(filter.group)"
                :modelValue="active[filter.group]"
                :options="dropdownOptions(filter.group)"
                optionLabel="label"
                optionValue="value"
                :filter="filters[filter.group].length > 8"
                size="small"
                class="journey-select min-w-[10rem]"
                :class="{ 'is-active': active[filter.group] !== null }"
                @update:modelValue="(value) => visit({ [filter.group]: value })">
                <template #value="{ value }">
                    <span class="whitespace-nowrap">{{ filter.label }}: {{ dropdownOptions(filter.group).find((option) => option.value === value)?.label ?? ctrans("All") }}</span>
                </template>
            </Select>
        </template>
    </div>

    <div class="mx-4 mt-3 grid gap-4 transition-opacity lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]" :class="{ 'opacity-60': isChangingPage }">
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
            <div class="grid gap-x-6 sm:grid-cols-2 xl:grid-cols-3">
                <div class="flex items-center gap-3 py-1.5">
                    <FontAwesomeIcon icon="fal fa-stopwatch" class="text-gray-400" fixed-width aria-hidden="true" />
                    <div>
                        <div class="text-xs text-gray-500">{{ ctrans("Average lead time, created to placed") }}</div>
                        <div class="font-semibold">{{ quickStats.average_lead_days !== null ? ctrans(":days days", { days: quickStats.average_lead_days }) : "—" }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 py-1.5">
                    <FontAwesomeIcon icon="fal fa-globe" class="text-gray-400" fixed-width aria-hidden="true" />
                    <div>
                        <div class="text-xs text-gray-500">{{ ctrans("Average days, placed to online") }}</div>
                        <div class="font-semibold">{{ quickStats.average_online_days != null ? ctrans(":days days", { days: quickStats.average_online_days }) : "—" }}</div>
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

    <div class="mx-4 mb-6 mt-2">
        <div class="sticky top-10 z-[5] bg-white pt-2">
            <div class="rounded-t-lg border border-gray-200 bg-white">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2 text-sm text-gray-600">
                    <span class="flex items-center gap-2">
                        <LoadingIcon v-if="isChangingPage" />
                        {{ ctrans("Showing :from–:to of :total orders", { from: shownRange.from, to: shownRange.to, total: pagination.total }) }}
                    </span>
                    <span class="ml-auto flex items-center gap-2">
                        {{ ctrans("Sort") }}:
                        <Select :modelValue="active.sort" :options="sortOptions" optionLabel="label" optionValue="value" size="small" class="journey-select" @update:modelValue="(value) => visit({ sort: value === 'needs_action' ? null : value })" />
                    </span>
                    <button type="button" class="border-l border-gray-200 pl-3 text-gray-700 underline hover:text-gray-900" @click="expandAll = !expandAll">
                        {{ expandAll ? ctrans("Collapse all") : ctrans("Expand all") }}
                    </button>
                    <span class="flex items-center gap-1 border-l border-gray-200 pl-3 text-xs">
                        <button type="button" class="rounded border border-gray-200 px-2 py-1 hover:border-gray-400 disabled:opacity-40" :disabled="isChangingPage || pagination.page <= 1" :aria-label="ctrans('Previous page')" @click="changePage(pagination.page - 1)">‹</button>
                        <template v-for="(number, index) in pageNumbers" :key="index">
                            <span v-if="number === null" class="px-1">…</span>
                            <button
                                v-else
                                type="button"
                                class="min-w-[1.75rem] rounded px-2 py-1"
                                :class="number === pagination.page ? 'bg-gray-200 font-semibold text-gray-900' : 'hover:bg-gray-100'"
                                :disabled="isChangingPage"
                                @click="changePage(number)">{{ number }}</button>
                        </template>
                        <button type="button" class="rounded border border-gray-200 px-2 py-1 hover:border-gray-400 disabled:opacity-40" :disabled="isChangingPage || pagination.page >= pagination.last_page" :aria-label="ctrans('Next page')" @click="changePage(pagination.page + 1)">›</button>
                        <span class="ml-3 border-l border-gray-200 pl-3">{{ ctrans("Rows per page") }}</span>
                        <Select :modelValue="pagination.per_page" :options="perPageOptions" size="small" class="journey-select" @update:modelValue="(value) => changePage(1, value)" />
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-100 px-3 py-1.5 text-xs text-gray-600">
                    <span v-for="item in legend" :key="item.label" class="flex items-center gap-1.5">
                        <span class="inline-block h-3.5 w-5 shrink-0 rounded border" :class="item.class" />
                        {{ item.label }}
                    </span>
                </div>
                <div ref="headerScroller" class="journey-head-scroller overflow-x-auto border-t border-gray-200" @scroll="syncScroll($event, bodyScroller)">
                    <table class="journey-table w-full min-w-[97rem] table-fixed border-collapse">
                        <colgroup>
                            <col class="w-[20rem]" />
                            <col v-for="stage in stages" :key="stage.key" class="w-[8.5rem]" />
                            <col class="w-[9rem]" />
                        </colgroup>
                        <thead class="whitespace-nowrap">
                            <tr class="border-b border-gray-200 text-xs text-gray-500">
                                <th class="py-2 pl-3 pr-3 text-left text-sm font-semibold text-gray-700">{{ ctrans("PO / Supplier / Key details") }}</th>
                                <th v-for="(stage, index) in stages" :key="stage.key" class="px-1 py-2 text-center font-normal" :title="stage.description">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-600 text-[11px] font-semibold text-white">{{ index + 1 }}</span>
                                        <FontAwesomeIcon :icon="stageIcons[stage.key]" class="text-base text-gray-600" fixed-width aria-hidden="true" />
                                    </div>
                                    <div class="mt-1 whitespace-nowrap text-sm font-semibold text-gray-800">{{ stage.label }}</div>
                                    <div class="whitespace-nowrap">{{ stage.description }}</div>
                                </th>
                                <th class="py-2 pl-3 pr-3 text-left text-sm font-semibold text-gray-700">{{ ctrans("Status") }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <div ref="bodyScroller" class="relative overflow-x-auto rounded-b-lg border-x border-b border-gray-200 bg-white transition-opacity" :class="{ 'pointer-events-none opacity-50': isChangingPage }" @scroll="syncScroll($event, headerScroller)">
            <table class="journey-table w-full min-w-[97rem] table-fixed border-collapse">
                <colgroup>
                    <col class="w-[20rem]" />
                    <col v-for="stage in stages" :key="stage.key" class="w-[8.5rem]" />
                    <col class="w-[9rem]" />
                </colgroup>
                <PurchaseOrderJourneyRibbon
                    v-for="ribbon in ribbons"
                    :key="ribbon.key"
                    :ribbon="ribbon"
                    :stages="stages"
                    :group-currency="groupCurrency"
                    :can-mark="canMark"
                    :expand-all="expandAll"
                    @mark="openMark" />
            </table>
            <div v-if="!ribbons.length" class="py-10 text-center text-sm text-gray-500">
                {{ ctrans("No purchase orders match these filters.") }}
            </div>
        </div>
    </div>

    </template>

    <div v-else class="animate-pulse">
        <div class="mx-4 mt-2 flex gap-6 border-b border-gray-200 py-2">
            <div v-for="skeletonTab in 3" :key="skeletonTab" class="h-5 w-24 rounded bg-gray-200" />
        </div>
        <div class="mx-4 mt-3 flex flex-wrap gap-2">
            <div v-for="skeletonPill in [10, 16, 12, 9, 14]" :key="skeletonPill" class="h-8 rounded-md bg-gray-200" :style="{ width: skeletonPill + 'rem' }" />
        </div>
        <div class="mx-4 mt-2 flex flex-wrap gap-2">
            <div v-for="skeletonFilter in [8, 9, 8, 10, 7, 18]" :key="skeletonFilter" class="h-8 rounded-md bg-gray-200" :style="{ width: skeletonFilter + 'rem' }" />
        </div>
        <div class="mx-4 mt-3 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
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
        <div class="mx-4 mb-6 mt-4">
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
        </div>
    </div>

    <Popover ref="markPopover">
        <div v-if="marking" class="w-64 text-sm">
            <div class="font-semibold text-gray-800">{{ marking.ribbon.reference }} · {{ marking.segment.label }}</div>
            <div class="mb-2 text-xs text-gray-500">{{ marking.segment.description }}</div>
            <div v-if="marking.segment.done_at" class="mb-2 text-xs text-emerald-700">
                {{ ctrans("Done on :date", { date: useFormatTime(marking.segment.done_at) }) }}
            </div>
            <div v-if="marking.segment.checks?.length" class="mb-2 flex flex-wrap gap-1">
                <span
                    v-for="check in marking.segment.checks"
                    :key="check.key"
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs"
                    :class="check.done ? 'border-green-200 bg-green-50 text-green-700' : 'border-gray-200 bg-gray-50 text-gray-500'"
                    :title="check.date ? useFormatTime(check.date) : ctrans('Not done yet')">
                    <FontAwesomeIcon :icon="check.done ? 'fal fa-check' : 'fal fa-circle'" fixed-width aria-hidden="true" />
                    {{ check.label }}
                </span>
            </div>
            <DatePicker v-model="markDate" dateFormat="yy-mm-dd" :maxDate="new Date()" showIcon class="w-full" />
            <template v-if="markErrors.reason || markReason">
                <div class="mt-2 text-xs text-amber-700">{{ markErrors.reason }}</div>
                <Textarea v-model="markReason" rows="2" autoResize class="mt-1 w-full" :placeholder="ctrans('Reason (kept in the order\'s history)')" />
            </template>
            <div v-for="key in ['date', 'field', 'stage']" :key="key">
                <div v-if="markErrors[key]" class="mt-1 text-xs text-red-600">{{ markErrors[key] }}</div>
            </div>
            <div class="mt-3 flex justify-end gap-2">
                <Button
                    v-if="marking.segment.done_at"
                    type="tertiary"
                    :label="ctrans('Clear')"
                    :disabled="markProcessing"
                    @click="saveMark(null)">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-eraser" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button
                    type="tertiary"
                    :label="ctrans('Mark done')"
                    :loading="markProcessing"
                    :disabled="!markDate || (!!markErrors.reason && !markReason.trim())"
                    @click="saveMark(toIso(markDate))">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>
    </Popover>
</template>

<style scoped>
.journey-table thead th:first-child,
.journey-table :deep(tbody tr:first-child td:first-child) {
    position: sticky;
    left: 0;
    z-index: 2;
    background-color: white;
    box-shadow: -1px 0 0 white, 1px 0 0 white, 2px 0 0 #e5e7eb;
}

.journey-table thead th {
    background-color: white;
    box-shadow: inset 0 -2px 0 #d1d5db;
}

.journey-table thead th:first-child {
    z-index: 3;
    box-shadow: inset 0 -2px 0 #d1d5db, -1px 0 0 white, 1px 0 0 white, 2px 0 0 #e5e7eb;
}

.journey-head-scroller {
    scrollbar-width: none;
}

.journey-head-scroller::-webkit-scrollbar {
    display: none;
}

.journey-select {
    font-size: 0.75rem;
}

.journey-select.is-active {
    border-color: #6b7280;
}

.journey-select :deep(.p-select-label) {
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}

.journey-search {
    font-size: 0.75rem;
}

.journey-search:focus,
.journey-select:deep(.p-focus) {
    border-color: #6b7280;
}
</style>
