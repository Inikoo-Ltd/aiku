<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, Link, router, usePoll } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import DataTable, { DataTablePageEvent, DataTableSortEvent } from "primevue/datatable"
import Column from "primevue/column"
import ProgressBar from "primevue/progressbar"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SiteCrawlerUrlDetails from "@/Components/Seo/SiteCrawlerUrlDetails.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { crawlIssueDetailText } from "@/Composables/crawlIssueDetails"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileExcel, faSearch, faStopCircle } from "@fal"

type Severity = "error" | "warning" | "notice"

type TabFilter = {
    key: string
    label: string
    count: number
    severity: Severity | null
    description: string | null
    how_to_fix: string | null
}

type CrawlTab = {
    key: string
    label: string
    total: number
    issues: number
    filters: TabFilter[]
}

type TableColumn = { key: string, label: string, numeric: boolean }

type CrawlSummary = {
    id: number
    name: string | null
    website: string
    state: string
    is_running: boolean
    should_stop: boolean
    finish_reason: string | null
    start_at: string | null
    end_at: string | null
    urls_processed: number
    urls_found: number
    max_pages: number | null
    health_score: number | null
    errors: number
    warnings: number
    notices: number
    user_name: string | null
    can_stop: boolean
    is_full_audit: boolean
}

type Query = {
    tab: string
    filter: string | null
    search: string | null
    sort: string | null
    desc: boolean
    page: number
    per_page: number
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    crawl: CrawlSummary
    tabs: CrawlTab[]
    severities: Record<Severity, string>
    query: Query
    table: {
        columns: TableColumn[]
        rows: Array<Record<string, any>>
        total: number
        page: number
        per_page: number
    }
    routes: {
        self: routeType
        details: routeType
        export: routeType
        stop: (routeType & { method: string }) | null
        index: routeType
    }
}>()

const locale = useLocaleStore()

const severityDots: Record<Severity, string> = {
    error: "bg-red-500",
    warning: "bg-amber-500",
    notice: "bg-gray-400",
}

const finishReasons: Record<string, string> = {
    completed: ctrans("Completed"),
    max_pages_reached: ctrans("Limit reached"),
    interrupted: ctrans("Stopped"),
    failed: ctrans("Failed"),
}

const isLoading = ref(false)
const selectedUrlHash = ref<string | null>(null)
const search = ref(props.query.search ?? "")

const visit = (changes: Partial<Query>) => {
    const query = { ...props.query, ...changes }

    router.get(route(props.routes.self.name, props.routes.self.parameters), {
        tab: query.tab,
        filter: query.filter || undefined,
        search: query.search || undefined,
        sort: query.sort || undefined,
        desc: query.desc ? 1 : undefined,
        page: query.page > 1 ? query.page : undefined,
        per_page: query.per_page,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ["table", "query"],
        onStart: () => isLoading.value = true,
        onFinish: () => isLoading.value = false,
    })
}

const currentTab = computed(() => props.tabs.find((tab) => tab.key === props.query.tab) ?? props.tabs[0])

const filterOptions = computed(() => [
    { key: null, label: ctrans("All"), count: currentTab.value.total, severity: null },
    ...currentTab.value.filters,
])

const selectedFilter = computed(() => currentTab.value.filters.find((filter) => filter.key === props.query.filter) ?? null)

const selectTab = (tabKey: string | number) => {
    if (tabKey !== props.query.tab) {
        selectedUrlHash.value = null
        search.value = ""
        visit({ tab: String(tabKey), filter: null, search: null, sort: null, desc: false, page: 1 })
    }
}

const selectFilter = (filterKey: string | null) => visit({ filter: filterKey, page: 1 })

const openFilter = (tabKey: string, filterKey: string) => {
    selectedUrlHash.value = null
    search.value = ""
    visit({ tab: tabKey, filter: filterKey, search: null, sort: null, desc: false, page: 1 })
}

let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        if ((props.query.search ?? "") !== value) {
            visit({ search: value || null, page: 1 })
        }
    }, 400)
})

const onPage = (event: DataTablePageEvent) => visit({ page: event.page + 1, per_page: event.rows })

const onSort = (event: DataTableSortEvent) => visit({ sort: typeof event.sortField === "string" ? event.sortField : null, desc: event.sortOrder === -1, page: 1 })

const exportHref = computed(() => route(props.routes.export.name, {
    ...(props.routes.export.parameters as object),
    tab: props.query.tab,
    ...(props.query.filter ? { filter: props.query.filter } : {}),
}))

const downloadExport = () => window.location.assign(exportHref.value)

const { start: startPolling, stop: stopPolling } = usePoll(5000, { only: ["crawl", "tabs", "table"] }, { autoStart: false })

watch(() => props.crawl.is_running, (isRunning) => isRunning ? startPolling() : stopPolling(), { immediate: true })

const isStopping = ref(false)
const stopError = ref<string | null>(null)

const stopCrawl = () => {
    if (!props.routes.stop) {
        return
    }

    router.patch(route(props.routes.stop.name, props.routes.stop.parameters), {}, {
        preserveScroll: true,
        only: ["crawl"],
        onStart: () => {
            isStopping.value = true
            stopError.value = null
        },
        onError: () => stopError.value = ctrans("The crawl could not be stopped"),
        onFinish: () => isStopping.value = false,
    })
}

const progress = computed(() => {
    const crawl = props.crawl
    const target = crawl.max_pages ? Math.min(crawl.max_pages, Math.max(crawl.urls_found, 1)) : Math.max(crawl.urls_found, 1)

    return Math.min(100, Math.round(crawl.urls_processed / target * 100))
})

const formatValue = (column: TableColumn, value: any) => {
    if (value === null || value === undefined || value === "") {
        return "-"
    }

    if (column.key === "indexability") {
        return value ? ctrans("Indexable") : ctrans("Non-indexable")
    }

    if (column.key === "is_in_sitemap") {
        return value ? ctrans("Yes") : ctrans("No")
    }

    if (column.key === "details") {
        return crawlIssueDetailText(props.query.filter ?? "", value) || "-"
    }

    return column.numeric ? locale.number(value) : value
}

const cellClass = (column: TableColumn, value: any) => {
    if (column.key === "status_code" && typeof value === "number") {
        return value >= 400 ? "text-red-600" : value >= 300 ? "text-amber-600" : ""
    }

    if (column.key === "indexability" && value === false) {
        return "text-red-600"
    }

    return ""
}

const tabPt = {
    root: { class: "!bg-transparent" },
    activeBar: { class: "!bg-[var(--app-accent)]" },
}

const tabClass = (key: string) => props.query.tab === key ? "!text-[var(--app-accent)] !border-[var(--app-accent)]" : ""

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"

const rowClass = (row: Record<string, any>) => row.url_hash === selectedUrlHash.value ? "!bg-[var(--app-accent-soft)]" : "cursor-pointer"

const overviewTabs = computed(() => props.tabs.filter((tab) => tab.filters.some((filter) => filter.count > 0)))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <div class="flex items-center gap-2">
                <Button v-if="crawl.is_running && routes.stop" type="tertiary" :label="crawl.should_stop ? ctrans('Stopping') : ctrans('Stop')" :loading="isStopping" :disabled="crawl.should_stop" @click="stopCrawl">
                    <template #icon>
                        <FontAwesomeIcon :icon="faStopCircle" class="text-amber-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" :label="ctrans('Export')" @click="downloadExport">
                    <template #icon>
                        <FontAwesomeIcon :icon="faFileExcel" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </PageHeading>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-1 border-b border-gray-200 px-4 py-2 text-xs text-gray-600">
        <span v-if="crawl.is_running" class="flex items-center gap-2">
            <ProgressBar :value="progress" :showValue="false" class="!h-1.5 w-40" :pt="{ value: { class: '!bg-[var(--app-accent)]' } }" />
            {{ ctrans(":processed of :found URLs crawled", { processed: locale.number(crawl.urls_processed), found: locale.number(Math.max(crawl.urls_found, crawl.urls_processed)) }) }}
        </span>
        <span v-else>
            {{ finishReasons[crawl.finish_reason ?? ""] ?? crawl.finish_reason }}:
            {{ ctrans(":processed URLs crawled", { processed: locale.number(crawl.urls_processed) }) }}
        </span>
        <span v-if="crawl.start_at">{{ ctrans("Started :date", { date: useFormatTime(crawl.start_at, { formatTime: "hm" }) }) }}</span>
        <span v-if="crawl.user_name">{{ ctrans("by :name", { name: crawl.user_name }) }}</span>
        <span v-if="!crawl.is_full_audit" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">{{ ctrans("Limited crawl: not used for the health score and page scores") }}</span>
        <span v-if="!crawl.is_running && crawl.health_score !== null">{{ ctrans("Health score :score", { score: locale.number(crawl.health_score) }) }}</span>
        <span v-if="!crawl.is_running" class="tabular-nums">
            <span class="text-red-600">{{ ctrans(":count errors", { count: locale.number(crawl.errors) }) }}</span>,
            <span class="text-amber-600">{{ ctrans(":count warnings", { count: locale.number(crawl.warnings) }) }}</span>,
            {{ ctrans(":count notices", { count: locale.number(crawl.notices) }) }}
        </span>
        <span v-if="crawl.is_running" class="text-gray-500">{{ ctrans("Issues are checked when the crawl finishes.") }}</span>
        <Link :href="route(routes.index.name, routes.index.parameters)" class="primaryLink ml-auto">{{ ctrans("Other crawls of :website", { website: crawl.website }) }}</Link>
        <p v-if="stopError" class="w-full text-red-500">{{ stopError }}</p>
    </div>

    <div class="px-2">
        <Tabs :value="query.tab" scrollable :pt="tabPt" @update:value="selectTab">
            <TabList>
                <Tab v-for="tab in tabs" :key="tab.key" :value="tab.key" class="!px-3 !py-2 !text-sm whitespace-nowrap" :class="tabClass(tab.key)">
                    {{ tab.label }}
                    <span v-if="tab.issues" class="ml-1 rounded-full bg-gray-100 px-1.5 text-xs tabular-nums text-gray-600">{{ locale.number(tab.issues) }}</span>
                </Tab>
            </TabList>
        </Tabs>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="flex min-w-0 flex-col">
            <div class="flex flex-wrap items-center gap-2 px-4 py-2">
                <Select
                    :modelValue="query.filter"
                    :options="filterOptions"
                    optionLabel="label"
                    optionValue="key"
                    :class="fieldFocusClass"
                    class="w-72"
                    @update:modelValue="selectFilter"
                >
                    <template #option="{ option }">
                        <span class="flex w-full items-center gap-2">
                            <span v-if="option.severity" class="h-2 w-2 shrink-0 rounded-full" :class="severityDots[option.severity as Severity]" aria-hidden="true" />
                            <span class="flex-1">{{ option.label }}</span>
                            <span class="tabular-nums text-gray-400">{{ locale.number(option.count) }}</span>
                        </span>
                    </template>
                </Select>
                <IconField>
                    <InputIcon><FontAwesomeIcon :icon="faSearch" class="text-gray-400" aria-hidden="true" /></InputIcon>
                    <InputText v-model="search" :placeholder="ctrans('Search address')" :class="fieldFocusClass" />
                </IconField>
                <span class="ml-auto text-xs tabular-nums text-gray-500">{{ ctrans(":count rows", { count: locale.number(table.total) }) }}</span>
            </div>

            <div v-if="selectedFilter?.description" class="mx-4 mb-2 space-y-1 rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700">
                <p class="flex items-center gap-2 font-medium text-gray-900">
                    <span v-if="selectedFilter.severity" class="h-2 w-2 shrink-0 rounded-full" :class="severityDots[selectedFilter.severity]" aria-hidden="true" />
                    {{ selectedFilter.label }}
                </p>
                <p>{{ selectedFilter.description }}</p>
                <p><span class="font-medium">{{ ctrans("How to fix") }}:</span> {{ selectedFilter.how_to_fix }}</p>
            </div>

            <DataTable
                :value="table.rows"
                lazy
                paginator
                :rows="table.per_page"
                :first="(table.page - 1) * table.per_page"
                :totalRecords="table.total"
                :rowsPerPageOptions="[50, 100, 200]"
                :sortField="query.sort ?? undefined"
                :sortOrder="query.desc ? -1 : 1"
                :loading="isLoading"
                :rowClass="rowClass"
                dataKey="url_hash"
                size="small"
                stripedRows
                scrollable
                scrollHeight="45vh"
                class="border-t border-gray-200 text-sm"
                @page="onPage"
                @sort="onSort"
                @rowClick="(event) => selectedUrlHash = event.data.url_hash"
            >
                <template #empty>
                    <p class="py-6 text-center text-gray-500">{{ ctrans("No URLs match this filter.") }}</p>
                </template>
                <Column field="url" :header="ctrans('Address')" sortable class="min-w-[22rem]">
                    <template #body="{ data }">
                        <span class="break-all font-mono text-xs">{{ data.url }}</span>
                    </template>
                </Column>
                <Column
                    v-for="column in table.columns"
                    :key="column.key"
                    :field="column.key"
                    :header="column.label"
                    :sortable="column.key !== 'details'"
                    :class="column.numeric ? 'text-right tabular-nums' : ''"
                    headerClass="whitespace-nowrap"
                >
                    <template #body="{ data }">
                        <span :class="cellClass(column, data[column.key])" class="line-clamp-2">{{ formatValue(column, data[column.key]) }}</span>
                    </template>
                </Column>
            </DataTable>

            <SiteCrawlerUrlDetails class="h-[40vh]" :detailsRoute="routes.details" :urlHash="selectedUrlHash" @select="(urlHash) => selectedUrlHash = urlHash" />
        </div>

        <aside class="border-l border-gray-200 bg-gray-50/50 p-3 text-sm lg:max-h-[calc(100vh-12rem)] lg:overflow-y-auto">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Overview") }}</h3>
            <p v-if="!overviewTabs.length" class="text-xs text-gray-500">{{ crawl.is_running ? ctrans("Issues are checked when the crawl finishes.") : ctrans("No issues found.") }}</p>
            <div v-for="tab in overviewTabs" :key="tab.key" class="mb-3">
                <p class="mb-1 font-medium text-gray-800">{{ tab.label }}</p>
                <ul class="space-y-0.5">
                    <li v-for="filter in tab.filters.filter((filter) => filter.count > 0)" :key="filter.key">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded px-1.5 py-0.5 text-left text-xs hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--app-accent)]"
                            :class="query.tab === tab.key && query.filter === filter.key ? '!bg-[var(--app-accent-soft)] text-[var(--app-accent-deep)]' : 'text-gray-700'"
                            @click="openFilter(tab.key, filter.key)"
                        >
                            <span class="h-2 w-2 shrink-0 rounded-full" :class="filter.severity ? severityDots[filter.severity] : 'bg-transparent'" aria-hidden="true" />
                            <span class="flex-1">{{ filter.label }}</span>
                            <span class="tabular-nums text-gray-500">{{ locale.number(filter.count) }}</span>
                        </button>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</template>
