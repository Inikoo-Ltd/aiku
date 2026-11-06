<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import { Head, Link, router, usePoll } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Select from "primevue/select"
import ProgressBar from "primevue/progressbar"
import Menu from "primevue/menu"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableCrawlPages, { CrawlTableColumn } from "@/Components/Tables/Grp/Org/Web/TableCrawlPages.vue"
import SiteCrawlerUrlDetails from "@/Components/Seo/SiteCrawlerUrlDetails.vue"
import SiteCrawlerIssuesPanel from "@/Components/Seo/SiteCrawlerIssuesPanel.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileExcel, faStopCircle, faFileChartLine, faExchangeAlt, faSitemap, faInfoCircle, faSpider, faListUl } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faSpider)

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
    config: { mode: "spider" | "list", urls_count: number }
}

type Query = {
    tab: string
    filter: string | null
    segment: string | null
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    crawl: CrawlSummary
    tabs: CrawlTab[]
    severities: Record<Severity, string>
    segments: Record<string, string>
    query: Query
    columns: CrawlTableColumn[]
    data: object
    routes: {
        self: routeType
        details: routeType
        export: routeType
        report: routeType
        compare: routeType
        structure: routeType
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

const severityText: Record<Severity, string> = {
    error: "text-red-700",
    warning: "text-amber-800",
    notice: "text-gray-600",
}

const severityRank: Record<Severity, number> = { error: 1, warning: 2, notice: 3 }

const finishReasons: Record<string, string> = {
    completed: ctrans("Completed"),
    max_pages_reached: ctrans("Stopped at the URL limit"),
    interrupted: ctrans("Stopped"),
    failed: ctrans("Failed"),
}

const selectedUrlHash = ref<string | null>(null)
const isShowingHowToFix = ref(false)

const ISSUES_PANEL_KEY = "site-crawler.issues-panel"
const storedIssuesPanel = typeof window === "undefined" ? null : window.localStorage.getItem(ISSUES_PANEL_KEY)
const isShowingIssues = ref(storedIssuesPanel === null ? (typeof window !== "undefined" && window.innerWidth >= 1680) : storedIssuesPanel === "1")

const toggleIssues = () => {
    isShowingIssues.value = !isShowingIssues.value
    window.localStorage.setItem(ISSUES_PANEL_KEY, isShowingIssues.value ? "1" : "0")
}

const issueCount = computed(() => props.tabs.reduce((count, tab) => count + tab.filters.filter((filter) => filter.severity && filter.count > 0).length, 0))

const visit = (changes: Partial<Query>) => {
    const query = { ...props.query, ...changes }

    router.get(route(props.routes.self.name, props.routes.self.parameters), {
        tab: query.tab,
        check: query.filter || undefined,
        segment: query.segment || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const currentTab = computed(() => props.tabs.find((tab) => tab.key === props.query.tab) ?? props.tabs[0])

const selectedFilter = computed(() => currentTab.value.filters.find((filter) => filter.key === props.query.filter) ?? null)

const worstSeverity = (tab: CrawlTab): Severity | null => tab.filters
    .filter((filter) => filter.severity && filter.count > 0)
    .map((filter) => filter.severity as Severity)
    .sort((a, b) => severityRank[a] - severityRank[b])[0] ?? null

const isShowingEmptyFilters = ref(false)

const open = (tabKey: string, filterKey: string | null = null) => {
    if (tabKey === props.query.tab && filterKey === props.query.filter) {
        return
    }

    selectedUrlHash.value = null
    isShowingEmptyFilters.value = false
    isShowingHowToFix.value = false
    visit({ tab: tabKey, filter: filterKey })
}

const tabOptions = computed(() => props.tabs.map((tab) => ({ key: tab.key, label: tab.issues ? `${tab.label} (${locale.number(tab.issues)})` : tab.label })))

const filterOptions = computed(() => [
    { key: null, label: ctrans("All (:count)", { count: locale.number(currentTab.value.total) }) },
    ...currentTab.value.filters.map((filter) => ({ key: filter.key, label: `${filter.label} (${locale.number(filter.count)})` })),
])

const segmentOptions = computed(() => [
    { key: null, label: ctrans("All page types") },
    ...Object.entries(props.segments).map(([key, label]) => ({ key, label })),
])

const isPageSource = computed(() => !["external", "images"].includes(props.query.tab) && props.query.filter !== "blocked_by_robots")

const exportHref = computed(() => route(props.routes.export.name, {
    ...(props.routes.export.parameters as object),
    tab: props.query.tab,
    ...(props.query.filter ? { filter: props.query.filter } : {}),
    ...(props.query.segment ? { segment: props.query.segment } : {}),
}))

const downloadExport = () => window.location.assign(exportHref.value)

const reportMenu = ref<InstanceType<typeof Menu> | null>(null)

const downloadReport = (report: string) => window.location.assign(route(props.routes.report.name, { ...(props.routes.report.parameters as object), report }))

const reportItems = [
    { label: ctrans("Issues overview"), command: () => downloadReport("issues_overview") },
    { label: ctrans("Redirect chains"), command: () => downloadReport("redirect_chains") },
    { label: ctrans("Canonical chains"), command: () => downloadReport("canonical_chains") },
]

const { start: startPolling, stop: stopPolling } = usePoll(5000, { only: ["crawl", "tabs", "data"] }, { autoStart: false })

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
        onError: () => stopError.value = ctrans("The crawl could not be stopped. Try again in a moment."),
        onFinish: () => isStopping.value = false,
    })
}

const progress = computed(() => {
    const crawl = props.crawl
    const target = crawl.max_pages ? Math.min(crawl.max_pages, Math.max(crawl.urls_found, 1)) : Math.max(crawl.urls_found, 1)

    return Math.min(100, Math.round(crawl.urls_processed / target * 100))
})

const duration = computed(() => {
    if (!props.crawl.start_at) {
        return null
    }

    const end = props.crawl.end_at ? new Date(props.crawl.end_at) : new Date()
    const minutes = Math.max(1, Math.round((end.getTime() - new Date(props.crawl.start_at).getTime()) / 60000))

    return minutes < 60 ? ctrans(":minutes min", { minutes }) : ctrans(":hours h :minutes min", { hours: Math.floor(minutes / 60), minutes: minutes % 60 })
})

const healthClass = computed(() => {
    const score = props.crawl.health_score

    if (score === null) {
        return "text-gray-600"
    }

    return score >= 90 ? "text-green-700" : score >= 70 ? "text-amber-700" : "text-red-700"
})

const websiteHost = computed(() => props.crawl.website.replace(/^www\./, ""))

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"

const compactSelectPt = { root: { class: "!h-8" }, label: { class: "!py-1 !text-xs" } }

const workbench = ref<HTMLElement | null>(null)
const paneHeight = ref(280)
const minPaneHeight = 140

const clampPane = (height: number) => {
    const available = (workbench.value?.clientHeight ?? 700) - 160

    return Math.round(Math.max(minPaneHeight, Math.min(height, available)))
}

let dragStart: { y: number, height: number } | null = null

const onDragMove = (event: PointerEvent) => {
    if (dragStart) {
        paneHeight.value = clampPane(dragStart.height + dragStart.y - event.clientY)
    }
}

const stopDrag = () => {
    dragStart = null
    window.removeEventListener("pointermove", onDragMove)
    window.removeEventListener("pointerup", stopDrag)
    document.body.style.userSelect = ""
}

const startDrag = (event: PointerEvent) => {
    dragStart = { y: event.clientY, height: paneHeight.value }
    document.body.style.userSelect = "none"
    window.addEventListener("pointermove", onDragMove)
    window.addEventListener("pointerup", stopDrag)
}

const onResizerKey = (event: KeyboardEvent) => {
    if (event.key === "ArrowUp" || event.key === "ArrowDown") {
        event.preventDefault()
        paneHeight.value = clampPane(paneHeight.value + (event.key === "ArrowUp" ? 32 : -32))
    }
}

onBeforeUnmount(() => {
    stopDrag()
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <div class="flex flex-wrap items-center gap-2">
                <Button v-if="crawl.is_running && routes.stop" type="tertiary" :label="crawl.should_stop ? ctrans('Stopping') : ctrans('Stop crawl')" :loading="isStopping" :disabled="crawl.should_stop" @click="stopCrawl">
                    <template #icon>
                        <FontAwesomeIcon :icon="faStopCircle" class="text-amber-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Link v-if="!crawl.is_running" :href="route(routes.structure.name, routes.structure.parameters)">
                    <Button type="tertiary" :label="ctrans('Site structure')">
                        <template #icon>
                            <FontAwesomeIcon :icon="faSitemap" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </Link>
                <Link v-if="!crawl.is_running" :href="route(routes.compare.name, routes.compare.parameters)">
                    <Button type="tertiary" :label="ctrans('Compare')">
                        <template #icon>
                            <FontAwesomeIcon :icon="faExchangeAlt" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </Link>
                <Button type="tertiary" :label="ctrans('Reports')" aria-haspopup="true" aria-controls="crawl-reports" @click="(event) => reportMenu?.toggle(event)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faFileChartLine" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Menu id="crawl-reports" ref="reportMenu" :model="reportItems" popup class="text-sm" />
                <Button type="tertiary" :label="ctrans('Export')" @click="downloadExport">
                    <template #icon>
                        <FontAwesomeIcon :icon="faFileExcel" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </PageHeading>

    <div class="flex min-h-[34rem] flex-col gap-2 px-3 pb-3 lg:h-[calc(100vh-10.5rem)]">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-gray-700">
            <template v-if="crawl.is_running">
                <span class="flex items-center gap-2 font-medium text-gray-900">
                    {{ crawl.should_stop ? ctrans("Stopping") : ctrans("Crawling") }}
                    <ProgressBar :value="progress" :showValue="false" class="!h-1.5 w-32" :pt="{ value: { class: '!bg-[var(--app-accent)]' } }" />
                </span>
                <span class="tabular-nums">{{ ctrans(":processed of :found URLs", { processed: locale.number(crawl.urls_processed), found: locale.number(Math.max(crawl.urls_found, crawl.urls_processed)) }) }}</span>
            </template>
            <template v-else>
                <span class="flex items-baseline gap-1.5">
                    {{ ctrans("Health") }}
                    <span class="text-base font-semibold leading-none tabular-nums" :class="healthClass">{{ crawl.health_score === null ? "-" : locale.number(crawl.health_score) }}</span>
                </span>
                <span class="flex items-center gap-3 tabular-nums">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500" aria-hidden="true" />{{ ctrans(":count errors", { count: locale.number(crawl.errors) }) }}</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true" />{{ ctrans(":count warnings", { count: locale.number(crawl.warnings) }) }}</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-gray-400" aria-hidden="true" />{{ ctrans(":count notices", { count: locale.number(crawl.notices) }) }}</span>
                </span>
                <span class="tabular-nums">{{ ctrans(":count URLs", { count: locale.number(crawl.urls_processed) }) }}</span>
            </template>
            <span v-if="crawl.start_at">{{ useFormatTime(crawl.start_at, { formatTime: "hm" }) }}<template v-if="duration">, {{ duration }}</template></span>
            <span v-if="crawl.user_name">{{ ctrans("by :name", { name: crawl.user_name }) }}</span>
            <span v-if="!crawl.is_running && crawl.finish_reason">{{ finishReasons[crawl.finish_reason] ?? crawl.finish_reason }}</span>
            <span v-if="crawl.config.mode === 'list'" class="rounded bg-gray-100 px-1.5 py-0.5">{{ ctrans("List of :count URLs", { count: locale.number(crawl.config.urls_count) }) }}</span>
            <span v-if="!crawl.is_full_audit" class="rounded bg-gray-100 px-1.5 py-0.5" :title="ctrans('The health score and page scores keep reading the last full crawl.')">{{ ctrans("Limited crawl") }}</span>
            <span v-if="stopError" class="text-red-600">{{ stopError }}</span>
            <Link :href="route(routes.index.name, routes.index.parameters)" class="primaryLink ml-auto">{{ ctrans("All crawls of :website", { website: crawl.website }) }}</Link>
        </div>

        <div ref="workbench" class="grid min-h-0 flex-1 grid-cols-1 overflow-hidden rounded-md border border-gray-200 bg-white lg:grid-cols-[12.5rem_minmax(0,1fr)]" :class="isShowingIssues ? 'xl:grid-cols-[12.5rem_minmax(0,1fr)_17rem]' : ''">
            <nav :aria-label="ctrans('Crawl tabs')" class="hidden min-h-0 overflow-y-auto border-r border-gray-200 bg-gray-50/70 py-1 lg:block">
                <ul class="text-[13px]">
                    <li v-for="tab in tabs" :key="tab.key">
                        <button
                            type="button"
                            class="flex h-7 w-full cursor-pointer items-center gap-2 px-3 text-left transition-colors duration-150 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                            :class="query.tab === tab.key ? 'bg-white font-semibold text-gray-900 shadow-[inset_2px_0_0_var(--app-accent)]' : 'text-gray-700'"
                            :aria-current="query.tab === tab.key && !query.filter ? 'page' : undefined"
                            @click="open(tab.key)"
                        >
                            <span class="flex-1 truncate">{{ tab.label }}</span>
                            <span v-if="tab.issues" class="text-[11px] font-medium tabular-nums" :class="severityText[worstSeverity(tab) ?? 'notice']">{{ locale.number(tab.issues) }}</span>
                        </button>

                        <ul v-if="query.tab === tab.key && tab.filters.length" class="bg-white pb-1">
                            <li v-for="filter in [...tab.filters.filter((filter) => filter.count > 0), ...(isShowingEmptyFilters ? tab.filters.filter((filter) => filter.count === 0) : [])]" :key="filter.key">
                                <button
                                    type="button"
                                    class="flex min-h-[1.625rem] w-full cursor-pointer items-center gap-2 py-1 pl-5 pr-3 text-left text-xs transition-colors duration-150 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                                    :class="query.filter === filter.key ? 'bg-[var(--app-accent-soft)] font-medium text-[var(--app-accent-deep)]' : filter.count ? 'text-gray-700' : 'text-gray-600'"
                                    :aria-current="query.filter === filter.key ? 'page' : undefined"
                                    @click="open(tab.key, filter.key)"
                                >
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="filter.severity ? severityDots[filter.severity] : 'bg-gray-300'" aria-hidden="true" />
                                    <span class="flex-1 leading-snug">{{ filter.label }}</span>
                                    <span class="tabular-nums">{{ locale.number(filter.count) }}</span>
                                </button>
                            </li>
                            <li v-if="tab.filters.some((filter) => filter.count === 0)">
                                <button
                                    type="button"
                                    class="w-full cursor-pointer py-1 pl-8 pr-3 text-left text-[11px] text-gray-600 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                                    :aria-expanded="isShowingEmptyFilters"
                                    @click="isShowingEmptyFilters = !isShowingEmptyFilters"
                                >
                                    {{ isShowingEmptyFilters ? ctrans("Hide checks without results") : ctrans("+ :count checks without results", { count: tab.filters.filter((filter) => filter.count === 0).length }) }}
                                </button>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>

            <section class="flex min-h-0 min-w-0 flex-col">
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-2 py-1.5">
                    <div class="grid w-full grid-cols-2 gap-2 lg:hidden">
                        <Select :modelValue="query.tab" :options="tabOptions" optionLabel="label" optionValue="key" :aria-label="ctrans('Tab')" :class="fieldFocusClass" fluid @update:modelValue="(tabKey) => open(tabKey)" />
                        <Select :modelValue="query.filter" :options="filterOptions" optionLabel="label" optionValue="key" :aria-label="ctrans('Filter')" :class="fieldFocusClass" fluid @update:modelValue="(filterKey) => open(query.tab, filterKey)" />
                    </div>

                    <h2 class="flex min-w-0 items-center gap-2 text-sm font-semibold text-gray-900">
                        <span v-if="selectedFilter?.severity" class="h-2 w-2 shrink-0 rounded-full" :class="severityDots[selectedFilter.severity]" aria-hidden="true" />
                        <span class="truncate">{{ currentTab.label }}<template v-if="selectedFilter"><span class="font-normal text-gray-500"> / </span>{{ selectedFilter.label }}</template></span>
                    </h2>
                    <button
                        v-if="selectedFilter?.description"
                        type="button"
                        class="flex h-7 cursor-pointer items-center gap-1 rounded px-1.5 text-xs text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--app-accent)]"
                        :aria-expanded="isShowingHowToFix"
                        aria-controls="crawl-issue-help"
                        @click="isShowingHowToFix = !isShowingHowToFix"
                    >
                        <FontAwesomeIcon :icon="faInfoCircle" fixed-width aria-hidden="true" />
                        {{ isShowingHowToFix ? ctrans("Hide help") : ctrans("What is this?") }}
                    </button>

                    <div class="ml-auto flex items-center gap-2">
                        <Select
                            v-if="isPageSource"
                            :modelValue="query.segment"
                            :options="segmentOptions"
                            optionLabel="label"
                            optionValue="key"
                            :placeholder="ctrans('All page types')"
                            :aria-label="ctrans('Page type')"
                            :class="fieldFocusClass"
                            :pt="compactSelectPt"
                            class="w-40"
                            @update:modelValue="(segment) => visit({ segment })"
                        />
                        <button
                            type="button"
                            class="hidden h-8 cursor-pointer items-center gap-1.5 rounded border px-2 text-xs transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--app-accent)] xl:flex"
                            :class="isShowingIssues ? 'border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] text-[var(--app-accent-deep)]' : 'border-gray-300 text-gray-700 hover:bg-gray-100'"
                            :aria-pressed="isShowingIssues"
                            @click="toggleIssues"
                        >
                            <FontAwesomeIcon :icon="faListUl" fixed-width aria-hidden="true" />
                            {{ ctrans("Issues") }}
                            <span class="tabular-nums">{{ locale.number(issueCount) }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="selectedFilter?.description && isShowingHowToFix" id="crawl-issue-help" class="grid gap-3 border-b border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 md:grid-cols-2">
                    <p><span class="font-semibold text-gray-900">{{ ctrans("What it means") }}.</span> {{ selectedFilter.description }}</p>
                    <p><span class="font-semibold text-gray-900">{{ ctrans("How to fix") }}.</span> {{ selectedFilter.how_to_fix }}</p>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <TableCrawlPages
                        :key="`${query.tab}|${query.filter}|${query.segment}`"
                        :data="data"
                        :columns="columns"
                        :selectedHash="selectedUrlHash"
                        :filter="query.filter"
                        :websiteHost="websiteHost"
                        @select="(urlHash) => selectedUrlHash = urlHash"
                    />
                </div>

                <template v-if="selectedUrlHash">
                    <div
                        role="separator"
                        tabindex="0"
                        aria-orientation="horizontal"
                        :aria-label="ctrans('Resize the details pane')"
                        :aria-valuenow="paneHeight"
                        class="group flex h-2 shrink-0 cursor-row-resize items-center justify-center border-t border-gray-200 bg-gray-50 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                        @pointerdown.prevent="startDrag"
                        @keydown="onResizerKey"
                    >
                        <span class="h-0.5 w-10 rounded-full bg-gray-300 group-hover:bg-gray-400" aria-hidden="true" />
                    </div>
                    <SiteCrawlerUrlDetails
                        class="shrink-0"
                        :style="{ height: `${paneHeight}px` }"
                        :detailsRoute="routes.details"
                        :urlHash="selectedUrlHash"
                        :websiteHost="websiteHost"
                        @select="(urlHash) => selectedUrlHash = urlHash"
                        @close="selectedUrlHash = null"
                    />
                </template>
            </section>

            <SiteCrawlerIssuesPanel
                v-if="isShowingIssues"
                class="hidden border-l border-gray-200 xl:flex"
                :tabs="tabs"
                :totalUrls="crawl.urls_processed"
                :activeTab="query.tab"
                :activeFilter="query.filter"
                :isRunning="crawl.is_running"
                @open="open"
            />
        </div>
    </div>
</template>
