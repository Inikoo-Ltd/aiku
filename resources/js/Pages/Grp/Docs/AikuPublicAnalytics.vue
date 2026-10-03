
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { Tabs as TSTabs } from "@/types/Tabs"
import { faChartLine, faHashtag, faNewspaper, faTachometerAltFast } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, ref } from "vue"

library.add(faChartLine, faHashtag, faNewspaper, faTachometerAltFast)

interface StatRow {
    views: number
    visitors: number
    suspect?: number
    day?: string
    path?: string
    referrer?: string
    country?: string
    query?: string
    last_visited_at?: string
}

const lastVisited = (value?: string) => {
    if (!value) return ""
    const d = new Date(value)
    const pad = (n: number) => String(n).padStart(2, "0")
    return `${pad(d.getDate())} ${d.toLocaleString("en", { month: "short" }).slice(0, 3)} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

interface ArticleRow {
    slug: string
    title: string
    url: string
    date: string
    committed_at?: string
    visitors: number
    views: number
    last_visited_at?: string
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    articles?: { data: ArticleRow[] } | any
    hashtags?: any
    overview?: {
        daily: StatRow[]
        pages: StatRow[]
        searches: StatRow[]
        referrers: StatRow[]
        page_referrers: StatRow[]
        countries: StatRow[]
        bots: StatRow[]
    }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const shortDate = (value?: string) => value ? new Date(value).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" }) : "—"

const dailyChart = computed(() => {
    const daily = props.overview?.daily ?? []
    const line = (label: string, values: number[], color: string) => ({ label, data: values, borderColor: color, backgroundColor: color, tension: 0, borderWidth: 1.5, pointRadius: 2 })
    return {
        labels: daily.map(d => new Date(d.day as string).toLocaleDateString("en-GB", { day: "numeric", month: "short" })),
        datasets: [
            line(ctrans("Views"), daily.map(d => Number(d.views)), "#d97706"),
            line(ctrans("Visitors"), daily.map(d => Number(d.visitors)), "#c0399f"),
            line(ctrans("Suspect"), daily.map(d => Number(d.suspect ?? 0)), "#1f845a"),
        ],
    }
})

const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: { legend: { position: "bottom", labels: { boxWidth: 12 } } },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
}

const sort = ref<Record<string, { column: string, desc: boolean }>>({ referrer: { column: "views", desc: true } })

const sortBy = (key: string, column: string) => {
    const current = sort.value[key]
    sort.value[key] = { column, desc: current?.column === column ? !current.desc : true }
}

const sortArrow = (key: string, column: string) => sort.value[key]?.column === column ? (sort.value[key].desc ? " ↓" : " ↑") : ""

const sortRows = (key: string, rows: StatRow[]) => {
    const current = sort.value[key]
    if (!current) {
        return rows
    }

    const value = (row: StatRow) => current.column === key
        ? String(row[key as keyof StatRow] ?? "")
        : (current.column === "last_visited_at" ? String(row.last_visited_at ?? "") : Number(row[current.column as keyof StatRow] ?? 0))

    return [...rows].sort((a, b) => {
        const left = value(a), right = value(b)
        const comparison = typeof left === "number" ? left - (right as number) : left.localeCompare(right as string)

        return current.desc ? -comparison : comparison
    })
}

const sections = computed(() => [
    { label: ctrans("Pages"), key: "path", rows: props.overview?.pages ?? [] },
    { label: ctrans("Referrers"), key: "referrer", rows: props.overview?.referrers ?? [] },
    { label: ctrans("Countries"), key: "country", rows: props.overview?.countries ?? [] },
    { label: ctrans("Searches"), key: "query", rows: props.overview?.searches ?? [] },
    { label: ctrans("Bots (excluded above)"), key: "user_agent", rows: props.overview?.bots ?? [] },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <div class="space-y-8 p-4">
        <Table v-if="currentTab === 'articles' && articles" :resource="articles" name="articles" class="mt-2">
            <template #cell(title)="{ item }">
                <a :href="item.url" target="_blank" class="block -ml-2 lg:-ml-6 hover:underline">{{ item.title }}</a>
            </template>
            <template #cell(committed_at)="{ item }">
                <span class="whitespace-nowrap text-gray-500">{{ shortDate(item.committed_at) }}</span>
            </template>
            <template #cell(visitors)="{ item }">
                <span class="tabular-nums">{{ item.visitors }}</span>
            </template>
            <template #cell(views)="{ item }">
                <span class="tabular-nums text-gray-500">{{ item.views }}</span>
            </template>
            <template #cell(last_visited_at)="{ item }">
                <span class="whitespace-nowrap font-mono text-xs text-gray-500">{{ lastVisited(item.last_visited_at) }}</span>
            </template>
        </Table>

        <Table v-if="currentTab === 'hashtags' && hashtags" :resource="hashtags" name="hashtags" class="mt-2">
            <template #cell(hashtag)="{ item }">
                <span class="block -ml-2 lg:-ml-6">{{ item.hashtag }}</span>
            </template>
            <template #cell(last_visited_at)="{ item }">
                <span class="whitespace-nowrap font-mono text-xs text-gray-500">{{ lastVisited(item.last_visited_at) }}</span>
            </template>
        </Table>

        <template v-if="currentTab === 'overview' && overview">
        <section>
            <h2 class="text-sm font-medium">{{ ctrans("Daily visits (last 30 days)") }}</h2>
            <div class="mt-2 h-56"><Chart type="line" :data="dailyChart" :options="dailyChartOptions" class="h-full" /></div>
        </section>

        <div class="grid gap-8 lg:grid-cols-3">
            <section v-for="section in sections" :key="section.key">
                <h2 class="text-sm font-medium">{{ section.label }}</h2>
                <table class="mt-2 w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                            <th class="cursor-pointer select-none py-1 font-normal" @click="sortBy(section.key, section.key)">{{ section.label }}<span class="text-gray-400">{{ sortArrow(section.key, section.key) }}</span></th>
                            <th class="cursor-pointer select-none py-1 text-right font-normal" @click="sortBy(section.key, 'visitors')">{{ ctrans("Visitors") }}<span class="text-gray-400">{{ sortArrow(section.key, 'visitors') }}</span></th>
                            <th class="cursor-pointer select-none py-1 text-right font-normal" @click="sortBy(section.key, 'views')">{{ ctrans("Views") }}<span class="text-gray-400">{{ sortArrow(section.key, 'views') }}</span></th>
                            <th class="cursor-pointer select-none py-1 text-right font-normal" @click="sortBy(section.key, 'last_visited_at')">{{ ctrans("Last visit") }}<span class="text-gray-400">{{ sortArrow(section.key, 'last_visited_at') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in sortRows(section.key, section.rows)" :key="String(row[section.key as keyof StatRow])" class="border-b border-gray-100">
                            <td class="max-w-56 truncate py-1">{{ row[section.key as keyof StatRow] }}</td>
                            <td class="py-1 text-right">{{ row.visitors }}</td>
                            <td class="py-1 text-right text-gray-500">{{ row.views }}</td>
                            <td class="whitespace-nowrap py-1 text-right font-mono text-xs text-gray-500">{{ lastVisited(row.last_visited_at) }}</td>
                        </tr>
                        <tr v-if="!section.rows.length">
                            <td colspan="4" class="py-2 text-xs text-gray-500">{{ ctrans("No data yet") }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <section>
            <h2 class="text-sm font-medium">{{ ctrans("Referrers per article") }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th class="py-1 font-normal">{{ ctrans("Page") }}</th>
                        <th class="py-1 font-normal">{{ ctrans("Referrer") }}</th>
                        <th class="py-1 text-right font-normal">{{ ctrans("Visitors") }}</th>
                        <th class="py-1 text-right font-normal">{{ ctrans("Views") }}</th>
                        <th class="py-1 text-right font-normal">{{ ctrans("Last visit") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in overview.page_referrers" :key="`${row.path}|${row.referrer}`" class="border-b border-gray-100">
                        <td class="max-w-96 truncate py-1">{{ row.path }}</td>
                        <td class="max-w-56 truncate py-1">{{ row.referrer }}</td>
                        <td class="py-1 text-right">{{ row.visitors }}</td>
                        <td class="py-1 text-right text-gray-500">{{ row.views }}</td>
                        <td class="whitespace-nowrap py-1 text-right font-mono text-xs text-gray-500">{{ lastVisited(row.last_visited_at) }}</td>
                    </tr>
                    <tr v-if="!overview.page_referrers.length">
                        <td colspan="5" class="py-2 text-xs text-gray-500">{{ ctrans("No data yet") }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
        </template>
    </div>
</template>
