
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
import { PageHeadingTypes } from "@/types/PageHeading"
import { faChartLine } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, ref } from "vue"

library.add(faChartLine)

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
    stats: {
        articles: ArticleRow[]
        daily: StatRow[]
        pages: StatRow[]
        searches: StatRow[]
        referrers: StatRow[]
        page_referrers: StatRow[]
        countries: StatRow[]
    }
}>()

const currentTab = ref<"overview" | "articles">("overview")
const shortDate = (value?: string) => value ? new Date(value).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" }) : "—"

const maxDailyViews = computed(() => Math.max(...props.stats.daily.map(d => Number(d.views)), 1))

const sections = computed(() => [
    { label: trans("Pages"), key: "path", rows: props.stats.pages },
    { label: trans("Referrers"), key: "referrer", rows: props.stats.referrers },
    { label: trans("Countries"), key: "country", rows: props.stats.countries },
    { label: trans("Searches"), key: "query", rows: props.stats.searches },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-8 p-4">
        <div class="flex gap-1 border-b border-gray-200 text-sm">
            <button v-for="tab in [{ key: 'overview', label: trans('Overview') }, { key: 'articles', label: trans('Articles') }]"
                :key="tab.key"
                class="-mb-px rounded-t px-4 py-2"
                :class="currentTab === tab.key ? 'border border-b-0 border-gray-200 font-medium' : 'text-gray-500 hover:text-gray-700'"
                @click="currentTab = tab.key as 'overview' | 'articles'">
                {{ tab.label }}
            </button>
        </div>

        <section v-if="currentTab === 'articles'">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th class="py-1 font-normal">{{ trans("Article") }}</th>
                        <th class="py-1 text-right font-normal">{{ trans("Dated") }}</th>
                        <th class="py-1 text-right font-normal">{{ trans("Committed") }}</th>
                        <th class="py-1 text-right font-normal">{{ trans("Visitors") }}</th>
                        <th class="py-1 text-right font-normal">{{ trans("Views") }}</th>
                        <th class="py-1 text-right font-normal">{{ trans("Last visit") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in stats.articles" :key="row.slug" class="border-b border-gray-100">
                        <td class="max-w-md py-1">
                            <a :href="row.url" target="_blank" class="text-indigo-600 hover:underline">{{ row.title }}</a>
                        </td>
                        <td class="whitespace-nowrap py-1 text-right text-gray-500">{{ shortDate(row.date) }}</td>
                        <td class="whitespace-nowrap py-1 text-right text-gray-500">{{ shortDate(row.committed_at) }}</td>
                        <td class="py-1 text-right">{{ row.visitors }}</td>
                        <td class="py-1 text-right text-gray-500">{{ row.views }}</td>
                        <td class="whitespace-nowrap py-1 text-right text-xs text-gray-500">{{ lastVisited(row.last_visited_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <template v-if="currentTab === 'overview'">
        <section>
            <h2 class="text-sm font-medium">{{ trans("Daily visits (last 30 days)") }}</h2>
            <div class="mt-2 flex h-32 items-end gap-1">
                <div v-for="d in stats.daily" :key="d.day" class="group relative max-w-10 flex-1">
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
                    <tr v-for="row in stats.page_referrers" :key="`${row.path}|${row.referrer}`" class="border-b border-gray-100">
                        <td class="max-w-96 truncate py-1">{{ row.path }}</td>
                        <td class="max-w-56 truncate py-1">{{ row.referrer }}</td>
                        <td class="py-1 text-right">{{ row.visitors }}</td>
                        <td class="py-1 text-right text-gray-500">{{ row.views }}</td>
                        <td class="whitespace-nowrap py-1 text-right text-xs text-gray-500">{{ lastVisited(row.last_visited_at) }}</td>
                    </tr>
                    <tr v-if="!stats.page_referrers.length">
                        <td colspan="5" class="py-2 text-xs text-gray-500">{{ trans("No data yet") }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
        </template>
    </div>
</template>
