<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"

type Summary = {
    date: string
    rank: number | null
    backlinks: number
    referring_domains: number
    broken_backlinks: number
    new_referring_domains: number | null
    lost_referring_domains: number | null
}

type OverviewData = {
    latest: Summary | null
    previous: Summary | null
    history: Summary[]
    links: { new: number, lost: number, broken: number, fetched_at: string | null, days: number }
    competitors: { domain: string, label: string | null, summary: Summary | null }[]
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: OverviewData | null
    domain: string
}>()

const locale = useLocaleStore()

const change = (key: "rank" | "backlinks" | "referring_domains" | "broken_backlinks") => {
    const latest = props.data?.latest?.[key]
    const previous = props.data?.previous?.[key]

    if (latest === null || latest === undefined || previous === null || previous === undefined || latest === previous) {
        return null
    }

    const difference = latest - previous
    const isGood = key === "broken_backlinks" ? difference < 0 : difference > 0

    return {
        text: `${difference > 0 ? "+" : ""}${locale.number(difference)}`,
        class: isGood ? "text-green-700" : "text-red-700",
    }
}

const metrics = computed(() => {
    const latest = props.data?.latest

    if (!latest) {
        return []
    }

    return [
        { key: "rank" as const, label: ctrans("Rank"), hint: ctrans("DataForSEO domain rank, 0 to 100. Not the Semrush Authority Score or Moz DA, so compare trends, not values"), value: latest.rank === null ? "-" : locale.number(latest.rank) },
        { key: "referring_domains" as const, label: ctrans("Referring domains"), hint: ctrans("Domains linking to us, our own websites included"), value: locale.number(latest.referring_domains) },
        { key: "backlinks" as const, label: ctrans("Backlinks"), hint: ctrans("Links to us, our own websites included"), value: locale.number(latest.backlinks) },
        { key: "broken_backlinks" as const, label: ctrans("Broken backlinks"), hint: ctrans("Links pointing at a page of ours that does not answer"), value: locale.number(latest.broken_backlinks) },
    ]
})

const accentColor = () => getComputedStyle(document.documentElement).getPropertyValue("--app-accent").trim() || "#4f46e5"

const showTrend = computed(() => (props.data?.history.length ?? 0) > 1)

const trendData = computed(() => ({
    labels: props.data!.history.map((summary) => useFormatTime(summary.date, { formatTime: "d MMM" })),
    datasets: [
        {
            label: ctrans("Referring domains"),
            data: props.data!.history.map((summary) => summary.referring_domains),
            borderColor: accentColor(),
            backgroundColor: accentColor(),
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.25,
        },
    ],
}))

const trendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0 } },
        y: { border: { display: false }, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280" } },
    },
}
</script>

<template>
    <div class="pb-4">
        <section v-if="!data?.latest" class="mx-4 mt-4 rounded-xl bg-white px-5 py-6 text-sm text-gray-600 ring-1 ring-gray-200">
            <p class="font-medium text-gray-900">{{ ctrans("No backlink data for :domain yet.", { domain }) }}</p>
            <p class="mt-1">{{ ctrans("Backlinks are fetched from DataForSEO every Monday for our website and the competitors set in SEO > Keywords.") }}</p>
        </section>

        <template v-else>
            <section :aria-label="ctrans('Our backlinks')" class="mx-4 mt-4 rounded-xl bg-white ring-1 ring-gray-200">
                <div class="flex flex-wrap items-end gap-x-10 gap-y-4 px-5 py-4">
                    <dl class="flex flex-wrap gap-x-8 gap-y-3">
                        <div v-for="metric in metrics" :key="metric.key">
                            <dt class="text-xs text-gray-500" v-tooltip="metric.hint">{{ metric.label }}</dt>
                            <dd class="mt-0.5 flex items-baseline gap-x-1.5">
                                <span class="text-2xl font-semibold tabular-nums tracking-tight text-gray-900">{{ metric.value }}</span>
                                <span v-if="change(metric.key)" class="text-xs tabular-nums" :class="change(metric.key)!.class">{{ change(metric.key)!.text }}</span>
                            </dd>
                        </div>
                    </dl>

                    <div class="ml-auto text-right text-xs text-gray-500">
                        <div class="font-medium text-gray-900">{{ domain }}</div>
                        <div>{{ ctrans("Fetched :date", { date: useFormatTime(data.latest.date) }) }}</div>
                        <div v-if="data.latest.new_referring_domains !== null">
                            {{ ctrans(":new new and :lost lost referring domains since the previous week", { new: locale.number(data.latest.new_referring_domains), lost: locale.number(data.latest.lost_referring_domains ?? 0) }) }}
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-1 border-t border-gray-100 sm:grid-cols-3">
                    <div class="px-5 py-3 sm:border-l sm:border-gray-100 sm:first:border-l-0">
                        <dt class="text-xs text-gray-500">{{ ctrans("New links, last :days days", { days: data.links.days }) }}</dt>
                        <dd class="mt-0.5 text-lg font-medium tabular-nums text-gray-900">{{ locale.number(data.links.new) }}</dd>
                    </div>
                    <div class="px-5 py-3 sm:border-l sm:border-gray-100">
                        <dt class="text-xs text-gray-500">{{ ctrans("Lost links, last :days days", { days: data.links.days }) }}</dt>
                        <dd class="mt-0.5 text-lg font-medium tabular-nums text-gray-900">{{ locale.number(data.links.lost) }}</dd>
                    </div>
                    <div class="px-5 py-3 sm:border-l sm:border-gray-100">
                        <dt class="text-xs text-gray-500" v-tooltip="ctrans('Links to a page of ours that does not answer. A redirect wins them back')">{{ ctrans("Broken links") }}</dt>
                        <dd class="mt-0.5 text-lg font-medium tabular-nums text-gray-900">{{ locale.number(data.links.broken) }}</dd>
                    </div>
                </dl>
                <p class="border-t border-gray-100 px-5 py-2 text-xs text-gray-500">
                    {{ data.links.fetched_at
                        ? ctrans("Links from our own websites are left out. Link lists fetched :date, every four weeks.", { date: useFormatTime(data.links.fetched_at) })
                        : ctrans("Our link lists have not been fetched yet; they are fetched every four weeks.") }}
                </p>

                <div v-if="showTrend" class="border-t border-gray-100 px-5 py-4">
                    <h3 class="text-xs font-medium text-gray-700">{{ ctrans("Referring domains per week") }}</h3>
                    <div class="mt-2 h-36" role="img" :aria-label="ctrans('Referring domains of the last :count weeks', { count: data.history.length })">
                        <Chart type="line" :data="trendData" :options="trendOptions" class="h-full" />
                    </div>
                </div>
            </section>

            <section :aria-labelledby="'backlinks-competitors'" class="mx-4 mt-4 rounded-xl bg-white ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-5 py-3">
                    <h2 id="backlinks-competitors" class="text-sm font-medium text-gray-900">{{ ctrans("Compared with competitors") }}</h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs text-gray-500">
                            <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Domain") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Rank") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Referring domains") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Backlinks") }}</th>
                            <th scope="col" class="px-5 py-2 text-right font-medium">{{ ctrans("Broken backlinks") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="bg-gray-50">
                            <td class="px-5 py-2 font-medium text-gray-900">{{ domain }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ data.latest.rank ?? "-" }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(data.latest.referring_domains) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(data.latest.backlinks) }}</td>
                            <td class="px-5 py-2 text-right tabular-nums">{{ locale.number(data.latest.broken_backlinks) }}</td>
                        </tr>
                        <tr v-for="competitor in data.competitors" :key="competitor.domain">
                            <td class="px-5 py-2 text-gray-900">
                                {{ competitor.domain }}
                                <span v-if="competitor.label" class="text-xs text-gray-500">{{ competitor.label }}</span>
                            </td>
                            <template v-if="competitor.summary">
                                <td class="px-3 py-2 text-right tabular-nums">{{ competitor.summary.rank ?? "-" }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(competitor.summary.referring_domains) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(competitor.summary.backlinks) }}</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ locale.number(competitor.summary.broken_backlinks) }}</td>
                            </template>
                            <td v-else colspan="4" class="px-5 py-2 text-right text-xs text-gray-500">{{ ctrans("Fetched on the next weekly run") }}</td>
                        </tr>
                        <tr v-if="!data.competitors.length">
                            <td colspan="5" class="px-5 py-3 text-sm text-gray-600">{{ ctrans("No competitors yet. Add their domains in SEO > Keywords, Competitors tab.") }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>
    </div>
</template>
