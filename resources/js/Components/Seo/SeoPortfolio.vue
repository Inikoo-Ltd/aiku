<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Column from "primevue/column"
import DataTable from "primevue/datatable"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type Period = { from: string, to: string, previous_from: string, previous_to: string }

type WebsiteRow = {
    id: number
    name: string
    domain: string
    shop: string | null
    route: routeType | null
    health: number | null
    previous_health: number | null
    visitors: number
    previous_visitors: number
    clicks: number | null
    previous_clicks: number | null
    position: number | null
    tracked_keywords: number
    checked_keywords: number
    top_10: number
    referring_domains: number | null
    new_referring: number | null
    lost_referring: number | null
    rank: number | null
}

type PortfolioData = {
    days: number
    traffic: Period
    search: Period | null
    websites: WebsiteRow[]
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: PortfolioData | null
}>()

const locale = useLocaleStore()

const MIN_FOR_PERCENT = 20

const change = (current: number | null, previous: number | null) => {
    if (current === null || previous === null || current === previous) {
        return null
    }

    const difference = current - previous

    if (previous === 0) {
        return { text: ctrans("New"), class: "text-green-700" }
    }

    const text = Math.max(current, previous) < MIN_FOR_PERCENT
        ? `${difference > 0 ? "+" : ""}${locale.number(difference)}`
        : `${difference > 0 ? "+" : ""}${locale.number(Math.round(difference / previous * 1000) / 10)}%`

    return { text, class: difference > 0 ? "text-green-700" : "text-red-700" }
}

const healthChange = (row: WebsiteRow) => {
    if (row.health === null || row.previous_health === null || row.health === row.previous_health) {
        return null
    }

    const difference = Math.round((row.health - row.previous_health) * 10) / 10

    return { text: `${difference > 0 ? "+" : ""}${locale.number(difference)}`, class: difference > 0 ? "text-green-700" : "text-red-700" }
}

const healthClass = (health: number) => health >= 90 ? "text-green-700" : health >= 70 ? "text-amber-700" : "text-red-700"

const totals = computed(() => {
    const websites = props.data?.websites ?? []

    return {
        visitors: websites.reduce((sum, row) => sum + row.visitors, 0),
        clicks: websites.reduce((sum, row) => sum + (row.clicks ?? 0), 0),
        tracked: websites.reduce((sum, row) => sum + row.tracked_keywords, 0),
        top10: websites.reduce((sum, row) => sum + row.top_10, 0),
        withSearch: websites.filter((row) => row.clicks !== null).length,
        audited: websites.filter((row) => row.health !== null).length,
    }
})

const numericPt = { columnHeaderContent: { class: "justify-end" }, bodyCell: { class: "!text-right tabular-nums" } }
</script>

<template>
    <div v-if="data" class="space-y-4 px-4 py-4">
        <section :aria-label="ctrans('Portfolio totals')" class="rounded-xl bg-white ring-1 ring-gray-200">
            <dl class="flex flex-wrap gap-x-10 gap-y-3 px-5 py-4">
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Live websites") }}</dt>
                    <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">{{ locale.number(data.websites.length) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Visitors, last :days days", { days: data.days }) }}</dt>
                    <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">{{ locale.number(totals.visitors) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Google clicks, last :days days", { days: data.days }) }}</dt>
                    <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">{{ locale.number(totals.clicks) }}</dd>
                    <dd class="text-xs text-gray-500">{{ ctrans(":count websites with Search Console", { count: totals.withSearch }) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Tracked keywords in the top 10") }}</dt>
                    <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">{{ ctrans(":top of :tracked", { top: locale.number(totals.top10), tracked: locale.number(totals.tracked) }) }}</dd>
                </div>
            </dl>
            <p class="border-t border-gray-100 px-5 py-2 text-xs text-gray-500">
                {{ ctrans("Visitors :from to :to against the :days days before.", { from: useFormatTime(data.traffic.from), to: useFormatTime(data.traffic.to), days: data.days }) }}
                <template v-if="data.search">{{ ctrans("Google clicks :from to :to, the last day Search Console has.", { from: useFormatTime(data.search.from), to: useFormatTime(data.search.to) }) }}</template>
                {{ ctrans("Site health from each website's latest audit; :count websites audited.", { count: totals.audited }) }}
            </p>
        </section>

        <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Websites')">
            <DataTable :value="data.websites" dataKey="id" sortField="visitors" :sortOrder="-1" removableSort size="small" class="text-sm">
                <Column field="name" :header="ctrans('Website')" sortable>
                    <template #body="{ data: row }">
                        <Link v-if="row.route" :href="route(row.route.name, row.route.parameters)" class="primaryLink">{{ row.domain }}</Link>
                        <span v-else>{{ row.domain }}</span>
                        <div class="text-xs text-gray-500">{{ row.shop ?? row.name }}</div>
                    </template>
                </Column>
                <Column field="health" sortable :pt="numericPt">
                    <template #header><span v-tooltip="ctrans('Site health of the latest audit, with the change since the audit before')">{{ ctrans("Site health") }}</span></template>
                    <template #body="{ data: row }">
                        <span v-if="row.health !== null" :class="healthClass(row.health)">{{ locale.number(row.health) }}%</span>
                        <span v-else class="text-gray-400">-</span>
                        <span v-if="healthChange(row)" class="block text-xs" :class="healthChange(row)!.class">{{ healthChange(row)!.text }}</span>
                    </template>
                </Column>
                <Column field="visitors" sortable :pt="numericPt">
                    <template #header><span v-tooltip="ctrans('Unique visitors per day, added up, from Aiku tracking')">{{ ctrans("Visitors") }}</span></template>
                    <template #body="{ data: row }">
                        {{ locale.number(row.visitors) }}
                        <span v-if="change(row.visitors, row.previous_visitors)" class="block text-xs" :class="change(row.visitors, row.previous_visitors)!.class">{{ change(row.visitors, row.previous_visitors)!.text }}</span>
                    </template>
                </Column>
                <Column field="clicks" sortable :pt="numericPt">
                    <template #header><span v-tooltip="ctrans('Clicks from Google Search, from Search Console')">{{ ctrans("Google clicks") }}</span></template>
                    <template #body="{ data: row }">
                        <template v-if="row.clicks !== null">
                            {{ locale.number(row.clicks) }}
                            <span v-if="change(row.clicks, row.previous_clicks)" class="block text-xs" :class="change(row.clicks, row.previous_clicks)!.class">{{ change(row.clicks, row.previous_clicks)!.text }}</span>
                        </template>
                        <span v-else class="text-gray-400" v-tooltip="ctrans('No Search Console data for this website')">-</span>
                    </template>
                </Column>
                <Column field="position" sortable :pt="numericPt">
                    <template #header><span v-tooltip="ctrans('Average Google position, weighted by impressions')">{{ ctrans("Position") }}</span></template>
                    <template #body="{ data: row }">{{ row.position === null ? "-" : locale.number(row.position) }}</template>
                </Column>
                <Column field="top_10" sortable :pt="numericPt">
                    <template #header><span v-tooltip="ctrans('Tracked keywords in the top 10 at their latest check, of the keywords tracked for the shop')">{{ ctrans("Top 10 keywords") }}</span></template>
                    <template #body="{ data: row }">
                        <template v-if="row.tracked_keywords">{{ ctrans(":top of :tracked", { top: locale.number(row.top_10), tracked: locale.number(row.tracked_keywords) }) }}</template>
                        <span v-else class="text-gray-400" v-tooltip="ctrans('No keyword tracked for this shop')">-</span>
                    </template>
                </Column>
                <Column field="referring_domains" sortable :pt="numericPt">
                    <template #header><span v-tooltip="ctrans('Domains linking to the website, from the weekly backlink fetch, with the new and lost ones since the week before')">{{ ctrans("Referring domains") }}</span></template>
                    <template #body="{ data: row }">
                        <template v-if="row.referring_domains !== null">
                            {{ locale.number(row.referring_domains) }}
                            <span v-if="row.new_referring || row.lost_referring" class="block text-xs">
                                <span class="text-green-700">+{{ locale.number(row.new_referring ?? 0) }}</span>
                                <span class="ml-1 text-red-700">-{{ locale.number(row.lost_referring ?? 0) }}</span>
                            </span>
                        </template>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </Column>
                <Column field="rank" sortable :pt="numericPt">
                    <template #header><span v-tooltip="ctrans('DataForSEO domain rank, 0 to 100')">{{ ctrans("Rank") }}</span></template>
                    <template #body="{ data: row }">{{ row.rank ?? "-" }}</template>
                </Column>
            </DataTable>
        </section>
    </div>
</template>
