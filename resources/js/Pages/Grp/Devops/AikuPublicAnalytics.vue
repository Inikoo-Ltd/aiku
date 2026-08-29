
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
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
    day?: string
    path?: string
    referrer?: string
    country?: string
    query?: string
    last_visited_at?: string
}

const lastVisited = (value?: string) => value ? new Date(value).toLocaleString(undefined, { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }) : ""

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
    }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const shortDate = (value?: string) => value ? new Date(value).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" }) : "—"

const maxDailyViews = computed(() => Math.max(...props.overview?.daily ?? [].map(d => Number(d.views)), 1))

const sections = computed(() => [
    { label: trans("Pages"), key: "path", rows: props.overview?.pages ?? [] },
    { label: trans("Referrers"), key: "referrer", rows: props.overview?.referrers ?? [] },
    { label: trans("Countries"), key: "country", rows: props.overview?.countries ?? [] },
    { label: trans("Searches"), key: "query", rows: props.overview?.searches ?? [] },
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
                <span class="whitespace-nowrap text-xs text-gray-500">{{ lastVisited(item.last_visited_at) }}</span>
            </template>
        </Table>

        <Table v-if="currentTab === 'hashtags' && hashtags" :resource="hashtags" name="hashtags" class="mt-2">
            <template #cell(hashtag)="{ item }">
                <span class="block -ml-2 lg:-ml-6">{{ item.hashtag }}</span>
            </template>
            <template #cell(last_visited_at)="{ item }">
                <span class="whitespace-nowrap text-xs text-gray-500">{{ lastVisited(item.last_visited_at) }}</span>
            </template>
        </Table>

        <template v-if="currentTab === 'overview' && overview">
        <section>
            <h2 class="text-sm font-medium">{{ trans("Daily visits (last 30 days)") }}</h2>
            <div class="mt-2 flex h-32 items-end gap-1">
                <div v-for="d in overview.daily" :key="d.day" class="group relative max-w-10 flex-1">
                    <div class="w-full rounded-t bg-indigo-500/80"
                        :style="{ height: `${(Number(d.views) / maxDailyViews) * 120}px` }" />
                    <div class="pointer-events-none absolute bottom-full left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs text-white group-hover:block">
                        {{ d.day }}: {{ d.views }} {{ trans("views") }}, {{ d.visitors }} {{ trans("visitors") }}
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-8 lg:grid-cols-3">
            <section v-for="section in sections" :key="section.key">
                <h2 class="text-sm font-medium">{{ section.label }}</h2>
                <table class="mt-2 w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                            <th class="py-1 font-normal">{{ section.label }}</th>
                            <th class="py-1 text-right font-normal">{{ trans("Visitors") }}</th>
                            <th class="py-1 text-right font-normal">{{ trans("Views") }}</th>
                            <th class="py-1 text-right font-normal">{{ trans("Last visit") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in section.rows" :key="String(row[section.key as keyof StatRow])" class="border-b border-gray-100">
                            <td class="max-w-56 truncate py-1">{{ row[section.key as keyof StatRow] }}</td>
                            <td class="py-1 text-right">{{ row.visitors }}</td>
                            <td class="py-1 text-right text-gray-500">{{ row.views }}</td>
                            <td class="whitespace-nowrap py-1 text-right text-xs text-gray-500">{{ lastVisited(row.last_visited_at) }}</td>
                        </tr>
                        <tr v-if="!section.rows.length">
                            <td colspan="4" class="py-2 text-xs text-gray-500">{{ trans("No data yet") }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <section>
            <h2 class="text-sm font-medium">{{ trans("Referrers per article") }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th class="py-1 font-normal">{{ trans("Page") }}</th>
                        <th class="py-1 font-normal">{{ trans("Referrer") }}</th>
                        <th class="py-1 text-right font-normal">{{ trans("Visitors") }}</th>
                        <th class="py-1 text-right font-normal">{{ trans("Views") }}</th>
                        <th class="py-1 text-right font-normal">{{ trans("Last visit") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in overview.page_referrers" :key="`${row.path}|${row.referrer}`" class="border-b border-gray-100">
                        <td class="max-w-96 truncate py-1">{{ row.path }}</td>
                        <td class="max-w-56 truncate py-1">{{ row.referrer }}</td>
                        <td class="py-1 text-right">{{ row.visitors }}</td>
                        <td class="py-1 text-right text-gray-500">{{ row.views }}</td>
                        <td class="whitespace-nowrap py-1 text-right text-xs text-gray-500">{{ lastVisited(row.last_visited_at) }}</td>
                    </tr>
                    <tr v-if="!overview.page_referrers.length">
                        <td colspan="5" class="py-2 text-xs text-gray-500">{{ trans("No data yet") }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
        </template>
    </div>
</template>
