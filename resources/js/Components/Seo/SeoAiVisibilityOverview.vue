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

type BrandRow = {
    competitor_id: number | null
    is_ours: boolean
    domain: string
    label: string | null
    answers: number
    mentioned: number
    cited: number
    mention_rate: number | null
    citation_rate: number | null
    brand_position: number | null
}

type TrendWeek = {
    week: string
    answers: number
    ours: number
    competitors: Record<string, number>
}

type MentionsPlatform = {
    platform: string
    date: string
    previous_date: string | null
    country: string | null
    language: string
    rows: {
        domain: string
        label: string | null
        is_ours: boolean
        mentions: number
        previous_mentions: number | null
        ai_search_volume: number
        share: number | null
    }[]
}

type OverviewData = {
    days: number
    prompts: { active: number, answered: number }
    brands: BrandRow[]
    trend: TrendWeek[]
    llm_mentions: MentionsPlatform[]
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: OverviewData | null
}>()

const locale = useLocaleStore()

const COMPETITOR_COLOURS = ["#d97706", "#0891b2", "#7c3aed", "#db2777", "#65a30d", "#475569"]

const accentColor = () => getComputedStyle(document.documentElement).getPropertyValue("--app-accent").trim() || "#4f46e5"

const ours = computed(() => props.data?.brands.find((row) => row.is_ours) ?? null)

const brandName = (row: BrandRow) => row.is_ours ? ctrans("Us") : (row.label || row.domain)

const percent = (value: number | null) => value === null ? "-" : `${locale.number(value)}%`

const chartData = computed(() => {
    if (!props.data || props.data.trend.length < 2) {
        return null
    }

    const competitors = props.data.brands.filter((row) => !row.is_ours)

    return {
        labels: props.data.trend.map((week) => useFormatTime(week.week, { formatTime: "d MMM" })),
        datasets: [
            {
                label: ctrans("Us"),
                data: props.data.trend.map((week) => week.ours),
                borderColor: accentColor(),
                backgroundColor: accentColor(),
                borderWidth: 2,
                pointRadius: 3,
            },
            ...competitors.map((competitor, index) => ({
                label: competitor.label || competitor.domain,
                data: props.data!.trend.map((week) => week.competitors[String(competitor.competitor_id)] ?? 0),
                borderColor: COMPETITOR_COLOURS[index % COMPETITOR_COLOURS.length],
                backgroundColor: COMPETITOR_COLOURS[index % COMPETITOR_COLOURS.length],
                borderWidth: 1,
                pointRadius: 2,
            })),
        ],
    }
})

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 10, color: "#4b5563" } },
        tooltip: { callbacks: { label: (context: { dataset: { label: string }, raw: number }) => `${context.dataset.label}: ${context.raw}%` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0 } },
        y: { min: 0, max: 100, border: { display: false }, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", callback: (value: number) => `${value}%` } },
    },
}

const platformTitle = (platform: MentionsPlatform) => platform.platform === "chat_gpt"
    ? ctrans("ChatGPT, :country, :language", { country: platform.country ?? "", language: platform.language })
    : ctrans("Google AI Overviews, :country, :language", { country: platform.country ?? "", language: platform.language })

const mentionChange = (row: MentionsPlatform["rows"][number]) => {
    if (row.previous_mentions === null || row.previous_mentions === row.mentions) {
        return null
    }

    const difference = row.mentions - row.previous_mentions

    return { text: `${difference > 0 ? "+" : ""}${locale.number(difference)}`, class: difference > 0 ? "text-green-700" : "text-red-700" }
}
</script>

<template>
    <div v-if="data" class="space-y-4 px-4 py-4">
        <section :aria-label="ctrans('Our AI visibility')" class="rounded-xl bg-white ring-1 ring-gray-200">
            <dl class="flex flex-wrap items-start gap-x-10 gap-y-3 px-5 py-4">
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Named by ChatGPT") }}</dt>
                    <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">{{ percent(ours?.mention_rate ?? null) }}</dd>
                    <dd v-if="ours?.answers" class="text-xs text-gray-500">{{ ctrans(":count of :total answers", { count: ours.mentioned, total: ours.answers }) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Our website cited") }}</dt>
                    <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">{{ percent(ours?.citation_rate ?? null) }}</dd>
                    <dd v-if="ours?.answers" class="text-xs text-gray-500">{{ ctrans(":count of :total answers", { count: ours.cited, total: ours.answers }) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Prompts answered") }}</dt>
                    <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">{{ ctrans(":answered of :active", { answered: data.prompts.answered, active: data.prompts.active }) }}</dd>
                </div>
            </dl>
            <p class="border-t border-gray-100 px-5 py-2 text-xs text-gray-500">
                {{ ctrans("Over the answers of the last :days days. ChatGPT answers the same prompt differently from week to week, so read the rates, not a single answer.", { days: data.days }) }}
            </p>
        </section>

        <p v-if="!data.prompts.active && !ours?.answers" class="rounded-xl bg-white px-5 py-4 text-sm text-gray-600 ring-1 ring-gray-200">
            {{ ctrans("No prompt yet. Add the questions a customer would ask ChatGPT in the Prompts tab; they are sent every week.") }}
        </p>

        <section v-if="ours?.answers" class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Share of voice')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Share of voice in ChatGPT") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("How often ChatGPT names each brand and cites its website in the answers to our prompts. Add competitors on the Competitors page.") }}</p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs text-gray-500">
                        <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Brand") }}</th>
                        <th scope="col" class="w-1/3 px-3 py-2 font-medium" v-tooltip="ctrans('Answers that write one of the brand names or link to the website')">{{ ctrans("Named") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('Answers that cite a page of the website as a source')">{{ ctrans("Cited") }}</th>
                        <th scope="col" class="px-5 py-2 text-right font-medium" v-tooltip="ctrans('Average place in the list an answer gives (its numbered suppliers or headings), when the brand is in it. 1 is the first entry.')">{{ ctrans("Place in the list") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in data.brands" :key="row.competitor_id ?? 'ours'" :class="row.is_ours ? 'bg-[--app-accent-soft]' : ''">
                        <td class="px-5 py-2">
                            <span class="font-medium text-gray-900">{{ brandName(row) }}</span>
                            <span class="block text-xs text-gray-500">{{ row.is_ours ? row.label : row.domain }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-3">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                                    <div class="h-full rounded-full" :class="row.is_ours ? 'bg-[--app-accent]' : 'bg-gray-400'" :style="{ width: `${row.mention_rate ?? 0}%` }" />
                                </div>
                                <span class="w-14 text-right tabular-nums">{{ percent(row.mention_rate) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ percent(row.citation_rate) }}</td>
                        <td class="px-5 py-2 text-right tabular-nums">{{ row.brand_position === null ? "-" : locale.number(row.brand_position) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="chartData" class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Trend')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Named by ChatGPT per week") }}</h2>
            </div>
            <div class="h-64 px-5 py-4" role="img" :aria-label="ctrans('Share of answers naming each brand per week')">
                <Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
            </div>
        </section>

        <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('LLM Mentions')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Mentions in AI answers across the web") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("From DataForSEO LLM Mentions, a database of AI answers to real searches, fetched monthly. It needs no prompts of ours. ChatGPT data there is United States and English only.") }}</p>
            </div>

            <p v-if="!data.llm_mentions.length" class="px-5 py-4 text-sm text-gray-600">{{ ctrans("Not fetched yet. It runs on the first day of each month.") }}</p>

            <div v-for="platform in data.llm_mentions" :key="platform.platform" class="border-b border-gray-100 last:border-b-0">
                <h3 class="px-5 pt-3 text-xs font-medium text-gray-700">
                    {{ platformTitle(platform) }}
                    <span class="font-normal text-gray-500">{{ ctrans("fetched :date", { date: useFormatTime(platform.date) }) }}</span>
                </h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Domain") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="platform.previous_date ? ctrans('Answers mentioning the domain, with the change since :date', { date: useFormatTime(platform.previous_date) }) : ctrans('Answers mentioning the domain')">{{ ctrans("Mentions") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('Share of the mentions of all the domains here')">{{ ctrans("Share") }}</th>
                            <th scope="col" class="px-5 py-2 text-right font-medium" v-tooltip="ctrans('Estimated monthly searches behind the answers that mention the domain')">{{ ctrans("AI search volume") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in platform.rows" :key="row.domain" :class="row.is_ours ? 'bg-[--app-accent-soft]' : ''">
                            <td class="px-5 py-2">
                                {{ row.domain }}
                                <span v-if="row.label" class="ml-1 text-xs text-gray-500">{{ row.label }}</span>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ locale.number(row.mentions) }}
                                <span v-if="mentionChange(row)" class="ml-1 text-xs" :class="mentionChange(row)!.class">{{ mentionChange(row)!.text }}</span>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ percent(row.share) }}</td>
                            <td class="px-5 py-2 text-right tabular-nums">{{ locale.number(row.ai_search_volume) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
