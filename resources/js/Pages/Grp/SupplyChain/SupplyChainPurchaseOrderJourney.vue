<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref, watch } from "vue"
import { Popover } from "primevue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import PurchaseOrderJourneyRibbon, { type JourneyRibbon, type JourneySegment } from "@/Components/SupplyChain/PurchaseOrderJourneyRibbon.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRoute, faExclamationTriangle, faStopwatch, faHourglassHalf, faCoins, faCalendarCheck, faSearch } from "@fal"

library.add(faRoute, faExclamationTriangle, faStopwatch, faHourglassHalf, faCoins, faCalendarCheck, faSearch)

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
    stages: { key: string; label: string; description: string }[]
    filters: Record<FilterGroup, FacetOption[]>
    active: Record<FilterGroup, string | null> & { problems_only: boolean; search: string | null }
    summary: {
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
    blockages: { stage: string; label: string; count: number; max_days_overdue: number }[]
    quickStats: {
        average_lead_days: number | null
        oldest_open_days: number | null
        open_value: number
        due_30_days: number
        due_30_days_value: number
    }
    ribbons: JourneyRibbon[]
    pagination: { page: number; last_page: number; total: number; per_page: number }
}>()

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

const stageKeys = computed(() => props.stages.map((stage) => stage.key))

const search = ref(props.active.search ?? "")

function visit(changes: Record<string, string | number | boolean | null>): void {
    const params: Record<string, string | number | boolean> = {}
    const merged = { ...props.active, view: props.view === "supplier_orders" ? null : props.view, page: null, ...changes }
    for (const [key, value] of Object.entries(merged)) {
        if (value !== null && value !== "" && value !== false) {
            params[key] = value
        }
    }
    router.get(route("grp.supply-chain.dashboard"), params, { preserveState: true, preserveScroll: true })
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

const kpis = computed(() => [
    { label: ctrans("Open orders"), value: props.summary.open.toLocaleString(), note: "", class: "text-gray-900", filter: { status: null, problems_only: false } },
    { label: ctrans("Total PO value"), value: money(props.summary.open_value, true), note: ctrans("open orders"), class: "text-gray-900", filter: null },
    { label: ctrans("On track"), value: props.summary.on_track, note: `${props.summary.on_track_percent}% ${ctrans("of open orders")}`, class: "text-emerald-600", filter: { status: "on_track" } },
    { label: ctrans("At risk"), value: props.summary.at_risk, note: `${props.summary.at_risk_percent}% ${ctrans("of open orders")}`, class: "text-amber-500", filter: { status: "at_risk" } },
    { label: ctrans("Overdue"), value: props.summary.overdue, note: `${props.summary.overdue_percent}% ${ctrans("of open orders")}`, class: "text-red-600", filter: { status: "overdue" } },
    { label: ctrans("Completed"), value: props.summary.completed, note: ctrans("last 60 days"), class: "text-blue-600", filter: { status: "completed" } }
])

const legend = [
    { label: ctrans("Completed"), class: "bg-emerald-200" },
    { label: ctrans("Done, date not recorded"), class: "bg-emerald-100" },
    { label: ctrans("Current stage"), class: "bg-blue-500" },
    { label: ctrans("At risk (due in 3 days)"), class: "bg-amber-400" },
    { label: ctrans("Overdue"), class: "bg-red-500" },
    { label: ctrans("Planned"), class: "bg-gray-100 border border-gray-200" },
    { label: ctrans("Not recorded"), class: "bg-slate-50 border border-slate-200" }
]

const markPopover = ref()
const marking = ref<{ ribbon: JourneyRibbon; segment: JourneySegment } | null>(null)
const markDate = ref("")
const markProcessing = ref(false)

function showFilter(group: FilterGroup): boolean {
    if (props.active[group] !== null) {
        return true
    }
    if (group === "country" && props.active.agent !== null) {
        return false
    }
    return props.filters[group].length > 1
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

    <div class="mx-4 mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <button
            v-for="kpi in kpis"
            :key="kpi.label"
            type="button"
            class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-left transition"
            :class="kpi.filter ? 'hover:border-gray-300 hover:shadow-sm' : 'cursor-default'"
            @click="kpi.filter && visit(kpi.filter)">
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-semibold" :class="kpi.class">{{ kpi.value }}</span>
                <span class="text-sm text-gray-600">{{ kpi.label }}</span>
            </div>
            <div class="text-xs text-gray-400">{{ kpi.note }}</div>
        </button>
    </div>

    <div class="mx-4 mt-3 flex flex-wrap items-center gap-2">
        <div class="inline-flex items-center gap-0.5 rounded-lg border border-gray-200 bg-gray-50 px-1 py-1 text-xs">
            <button
                v-for="option in [{ value: 'supplier_orders', label: ctrans('Orders to suppliers') }, { value: 'purchase_orders', label: ctrans('Agent POs') }]"
                :key="option.value"
                type="button"
                class="rounded-md px-2 py-0.5 transition duration-200"
                :class="view === option.value ? 'bg-white font-medium text-gray-800 shadow-sm ring-1 ring-gray-200' : 'text-gray-500 hover:bg-white hover:text-gray-800'"
                @click="visit({ view: option.value === 'supplier_orders' ? null : option.value })">
                {{ option.label }}
            </button>
        </div>
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
        <PillFilterBar
            v-if="showFilter(filter.group)"
            dropdown
            :label="filter.label"
            :options="filters[filter.group]"
            :selected="active[filter.group]"
            @select="(value) => visit({ [filter.group]: value })" />
        </template>
        <label class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs" :class="active.problems_only ? 'border-red-300 bg-red-50 text-red-700' : 'border-gray-200 bg-white text-gray-600'">
            <input type="checkbox" class="h-3.5 w-3.5 rounded border-gray-300 text-red-600" :checked="active.problems_only" @change="visit({ problems_only: !active.problems_only })" />
            {{ ctrans("Problems only") }}
        </label>
        <div class="flex min-w-[14rem] flex-1 items-center gap-2 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs sm:max-w-xs">
            <FontAwesomeIcon icon="fal fa-search" class="text-gray-400" fixed-width aria-hidden="true" />
            <input v-model="search" type="search" class="w-full border-0 p-0 text-xs focus:ring-0" :placeholder="ctrans('Search PO, supplier, agent, buyer')" />
        </div>
    </div>

    <div class="mx-4 mb-6 mt-3 grid gap-4 2xl:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="min-w-0 rounded-lg border border-gray-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
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
                            :stage-keys="stageKeys"
                            :group-currency="groupCurrency"
                            :can-mark="canMark"
                            @mark="openMark" />
                    </tbody>
                </table>
            </div>
            <div v-if="!ribbons.length" class="py-10 text-center text-sm text-gray-500">
                {{ ctrans("No purchase orders match these filters.") }}
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-3 py-2 text-xs text-gray-500">
                <div class="flex flex-wrap items-center gap-3">
                    <span v-for="item in legend" :key="item.label" class="flex items-center gap-1.5">
                        <span class="inline-block h-3 w-3 rounded-sm" :class="item.class" />
                        {{ item.label }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span>{{ ctrans("Page :page of :last · :total orders", { page: pagination.page, last: pagination.last_page, total: pagination.total }) }}</span>
                    <button type="button" class="rounded border border-gray-200 px-2 py-0.5 disabled:opacity-40" :disabled="pagination.page <= 1" @click="visit({ page: pagination.page - 1 })">‹</button>
                    <button type="button" class="rounded border border-gray-200 px-2 py-0.5 disabled:opacity-40" :disabled="pagination.page >= pagination.last_page" @click="visit({ page: pagination.page + 1 })">›</button>
                </div>
            </div>
        </div>

        <div class="order-first grid gap-4 md:grid-cols-2 2xl:order-none 2xl:grid-cols-1 2xl:content-start">
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
                        <span class="block truncate text-sm text-gray-800">{{ ctrans("stuck at :stage", { stage: blockage.label }) }}</span>
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
