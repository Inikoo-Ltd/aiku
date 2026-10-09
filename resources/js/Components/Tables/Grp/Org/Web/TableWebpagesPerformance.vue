<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router, usePage } from "@inertiajs/vue3"
import Popover from "primevue/popover"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import SeoExportButton from "@/Components/Seo/SeoExportButton.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSignIn, faHome, faNewspaper, faBrowser, faUfoBeam, faShapes } from "@fal"

library.add(faSignIn, faHome, faNewspaper, faBrowser, faUfoBeam, faShapes)

type WebpagePerformanceRow = {
    code: string
    title: string | null
    typeIcon: object
    route: routeType
    visitors: number
    page_views: number
    entrances: number
    avg_time_on_page: number
    add_to_baskets: number
    conversion_rate: number
    checkouts: number
    purchases: number
    search_clicks: number
    search_impressions: number
    search_position: number | null
    search_queries: number
    backlinks: number
    referring_domains: number
    previous: {
        visitors: number | null
        page_views: number | null
        search_clicks: number | null
        search_impressions: number | null
        search_position: number | null
    }
}

type Period = { from: string, to: string, previous_from: string, previous_to: string }

const props = defineProps<{
    data: { periods?: { traffic: Period | null, search: Period | null }, min_for_percent?: number }
    tab: string
}>()

const locale = useLocaleStore()
const page = usePage()

const periods = computed(() => props.data.periods ?? { traffic: null, search: null })

const minForPercent = computed(() => props.data.min_for_percent ?? 20)

const change = (current: number, previous: number | null, period: Period | null) => {
    if (previous === null || !period) {
        return null
    }

    const difference = current - previous
    const title = ctrans(":previous from :from to :to", { previous: locale.number(previous), from: useFormatTime(period.previous_from), to: useFormatTime(period.previous_to) })

    if (difference === 0) {
        return null
    }

    if (previous === 0) {
        return { text: ctrans("New"), class: "text-green-700", title }
    }

    const text = Math.max(current, previous) < minForPercent.value
        ? `${difference > 0 ? "+" : ""}${locale.number(difference)}`
        : `${difference > 0 ? "+" : ""}${locale.number(Math.round(difference / previous * 1000) / 10)}%`

    return { text, class: difference > 0 ? "text-green-700" : "text-red-700", title: `${title} (${difference > 0 ? "+" : ""}${locale.number(difference)})` }
}

const positionChange = (webpage: WebpagePerformanceRow) => {
    const previous = webpage.previous.search_position

    if (previous === null || webpage.search_position === null || !periods.value.search) {
        return null
    }

    const difference = Math.round((previous - webpage.search_position) * 10) / 10

    if (difference === 0) {
        return null
    }

    return {
        text: `${difference > 0 ? "+" : ""}${locale.number(difference)}`,
        class: difference > 0 ? "text-green-700" : "text-red-700",
        title: ctrans("Position :previous from :from to :to", { previous: locale.number(previous), from: useFormatTime(periods.value.search.previous_from), to: useFormatTime(periods.value.search.previous_to) }),
    }
}

const trendParam = computed(() => `${props.tab}_filter[trend]`)

const trendOptions = [
    { value: "all", label: ctrans("All") },
    { value: "growing", label: ctrans("Growing") },
    { value: "dropping", label: ctrans("Dropping") },
    { value: "new", label: ctrans("New") },
    { value: "lost", label: ctrans("Lost") },
]

const trendSorts: Record<string, string | null> = {
    all: null,
    growing: "-visitors_change",
    dropping: "visitors_change",
    new: "-visitors",
    lost: "visitors_change",
}

const trend = computed({
    get: () => new URL(page.url, window.location.origin).searchParams.get(trendParam.value) ?? "all",
    set: (value: string) => {
        const url = new URL(page.url, window.location.origin)

        url.searchParams.delete(`${props.tab}Page`)

        if (value === "all") {
            url.searchParams.delete(trendParam.value)
        } else {
            url.searchParams.set(trendParam.value, value)
        }

        if (trendSorts[value]) {
            url.searchParams.set(`${props.tab}_sort`, trendSorts[value]!)
        } else {
            url.searchParams.delete(`${props.tab}_sort`)
        }

        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, only: [props.tab] })
    },
})

const funnelPopover = ref<InstanceType<typeof Popover> | null>(null)
const funnelWebpage = ref<WebpagePerformanceRow | null>(null)

const showFunnel = (event: Event, webpage: WebpagePerformanceRow) => {
    funnelWebpage.value = webpage
    funnelPopover.value?.show(event)
}

const hideFunnel = () => funnelPopover.value?.hide()

const ratePer100 = (part: number, whole: number) => whole > 0 ? `${locale.number(Math.round(part / whole * 10000) / 100)}%` : "-"

const funnelSteps = (webpage: WebpagePerformanceRow) => [
    { label: ctrans("Entrances"), count: webpage.entrances, rate: null, hint: ctrans("Visitors whose visit started on this page") },
    { label: ctrans("Added to basket on this page"), count: webpage.add_to_baskets, rate: ratePer100(webpage.add_to_baskets, webpage.visitors), hint: ctrans("Per 100 visitors of the page") },
    { label: ctrans("Checkouts after landing here"), count: webpage.checkouts, rate: ratePer100(webpage.checkouts, webpage.entrances), hint: ctrans("Per 100 entrances") },
    { label: ctrans("Purchases after landing here"), count: webpage.purchases, rate: ratePer100(webpage.purchases, webpage.entrances), hint: ctrans("Per 100 entrances") },
]

const formatDuration = (totalSeconds: number) => {
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60

    if (minutes > 0) {
        return `${minutes}m ${String(seconds).padStart(2, "0")}s`
    }

    return `${seconds}s`
}
</script>

<template>
    <div v-if="periods.traffic" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 pb-3 pt-1">
        <SegmentedToggle v-model="trend" :options="trendOptions" :ariaLabel="ctrans('Visitors against the previous period')" />
        <span class="text-xs text-gray-500">
            {{ ctrans("Visitors against :from to :to", { from: useFormatTime(periods.traffic.previous_from), to: useFormatTime(periods.traffic.previous_to) }) }}
        </span>
        <SeoExportButton class="ml-auto" table="top_pages" />
    </div>
    <div v-else class="flex flex-wrap items-center gap-3 px-4 pb-3 pt-1">
        <p class="text-xs text-gray-500">{{ ctrans("Pick an interval above to compare each page with the period before.") }}</p>
        <SeoExportButton class="ml-auto" table="top_pages" />
    </div>

    <Table :resource="data" :name="tab">
        <template #cell(type)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <Icon :data="webpage.typeIcon" class="px-1" />
        </template>

        <template #cell(code)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <Link :href="route(webpage.route.name, webpage.route.parameters)" class="primaryLink">
                {{ webpage.code }}
            </Link>
        </template>

        <template #cell(visitors)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ locale.number(webpage.visitors) }}</span>
            <span v-if="change(webpage.visitors, webpage.previous.visitors, periods.traffic)" class="block text-xs tabular-nums" :class="change(webpage.visitors, webpage.previous.visitors, periods.traffic)!.class" v-tooltip="change(webpage.visitors, webpage.previous.visitors, periods.traffic)!.title">
                {{ change(webpage.visitors, webpage.previous.visitors, periods.traffic)!.text }}
            </span>
        </template>

        <template #cell(page_views)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ locale.number(webpage.page_views) }}</span>
            <span v-if="change(webpage.page_views, webpage.previous.page_views, periods.traffic)" class="block text-xs tabular-nums" :class="change(webpage.page_views, webpage.previous.page_views, periods.traffic)!.class" v-tooltip="change(webpage.page_views, webpage.previous.page_views, periods.traffic)!.title">
                {{ change(webpage.page_views, webpage.previous.page_views, periods.traffic)!.text }}
            </span>
        </template>

        <template #cell(avg_time_on_page)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ formatDuration(webpage.avg_time_on_page) }}</span>
        </template>

        <template #cell(conversion_rate)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <button
                type="button"
                class="tabular-nums underline decoration-dotted underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]"
                :aria-label="ctrans('Conversion funnel of :page', { page: webpage.code })"
                @mouseenter="showFunnel($event, webpage)"
                @mouseleave="hideFunnel"
                @focus="showFunnel($event, webpage)"
                @blur="hideFunnel">
                {{ locale.number(webpage.conversion_rate) }}%
            </button>
        </template>

        <template #cell(search_clicks)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ locale.number(webpage.search_clicks) }}</span>
            <span v-if="change(webpage.search_clicks, webpage.previous.search_clicks, periods.search)" class="block text-xs tabular-nums" :class="change(webpage.search_clicks, webpage.previous.search_clicks, periods.search)!.class" v-tooltip="change(webpage.search_clicks, webpage.previous.search_clicks, periods.search)!.title">
                {{ change(webpage.search_clicks, webpage.previous.search_clicks, periods.search)!.text }}
            </span>
        </template>

        <template #cell(search_impressions)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ locale.number(webpage.search_impressions) }}</span>
            <span v-if="change(webpage.search_impressions, webpage.previous.search_impressions, periods.search)" class="block text-xs tabular-nums" :class="change(webpage.search_impressions, webpage.previous.search_impressions, periods.search)!.class" v-tooltip="change(webpage.search_impressions, webpage.previous.search_impressions, periods.search)!.title">
                {{ change(webpage.search_impressions, webpage.previous.search_impressions, periods.search)!.text }}
            </span>
        </template>

        <template #cell(search_position)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span v-if="webpage.search_position !== null" class="tabular-nums">{{ locale.number(webpage.search_position) }}</span>
            <span v-else class="text-gray-400">-</span>
            <span v-if="positionChange(webpage)" class="block text-xs tabular-nums" :class="positionChange(webpage)!.class" v-tooltip="positionChange(webpage)!.title">
                {{ positionChange(webpage)!.text }}
            </span>
        </template>

        <template #cell(search_queries)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums" :class="webpage.search_queries ? '' : 'text-gray-400'">{{ locale.number(webpage.search_queries) }}</span>
        </template>

        <template #cell(referring_domains)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums" :class="webpage.referring_domains ? '' : 'text-gray-400'" v-tooltip="webpage.backlinks ? ctrans(':count links in our list', { count: locale.number(webpage.backlinks) }) : undefined">{{ locale.number(webpage.referring_domains) }}</span>
        </template>
    </Table>

    <Popover ref="funnelPopover">
        <dl v-if="funnelWebpage" class="w-72 space-y-2 text-sm">
            <div v-for="step in funnelSteps(funnelWebpage)" :key="step.label" class="flex items-baseline gap-3">
                <div class="min-w-0">
                    <dt class="text-gray-700">{{ step.label }}</dt>
                    <dd class="text-xs text-gray-500">{{ step.hint }}</dd>
                </div>
                <dd class="ml-auto text-right tabular-nums text-gray-900">
                    {{ locale.number(step.count) }}
                    <span v-if="step.rate" class="ml-1.5 inline-block w-14 text-gray-500">{{ step.rate }}</span>
                </dd>
            </div>
        </dl>
    </Popover>
</template>
