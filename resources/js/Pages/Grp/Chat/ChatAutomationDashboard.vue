<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed } from "vue"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import ChatAiCoverage from "@/Components/Chat/ChatAiCoverage.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChartLine, faChartPie, faPaperPlane, faRobot, faFilter, faBolt, faClock, faHandsHelping } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"

interface DraftCounts {
    decided: number
    used: number
    edited: number
    discarded: number
    superseded: number
    pending: number
    auto_sent: number
}

interface Dashboard {
    daily: { date: string, sent: number, drafts: number, noise: number, genuine: number }[]
    by_kind: { kind: string, label: string, total: number, wrong: number }[]
    verdicts: { verdict: string, label: string, total: number }[]
    checks: number
    put_aside: number
    overruled: number
    promises: { made: number, kept: number }
    review: { id: number, at: string, shop: string, branch: string | null, customer: string | null, suggested: { guides?: string[], facts?: string[], engineer?: string, next_step?: string }, draft: string | null, draft_status: string | null, used: string | null, reply: string | null }[]
    readings: { read: number, helped: number, drafts: number, drafts_used: number, guides: number, guides_used: number, facts: number, engineer: number, engineer_used: number }
}

const props = defineProps<{
    title: string
    pageHead: object
    coverage: InstanceType<typeof ChatAiCoverage>["$props"]["coverage"]
    dashboard: Dashboard
    draftStats: DraftCounts & { assistant: DraftCounts & { clients: { client: string, drafts: number, people: number }[] } }
    confidence: {
        days: number
        cells: number
        decided: number
        alone: number
        alone_clean: number
        gaps_filled: number
        harms: number
        would_send: number
        would_send_right: number
        untagged: number
        lower_bound: number | null
        best: { shop: string, intent: string, risk: string, decided: number, alone_clean: number, lower_bound: number }[]
    }
}>()

const kindTotal = (kind: string) => props.dashboard.by_kind.find((row) => row.kind === kind)?.total ?? 0
const sentTotal = computed(() => props.dashboard.by_kind.filter((row) => row.kind !== "ai_draft").reduce((sum, row) => sum + row.total, 0))
const share = (count: number, of = props.draftStats.decided) => of ? Math.round(100 * count / of) + "%" : "—"

const cards = computed(() => [
    { label: ctrans("Sent to customers"), value: sentTotal.value, note: ctrans(":count claim checklists", { count: kindTotal("claim_details") }), icon: faPaperPlane, color: "text-indigo-600" },
    { label: ctrans("AI draft replies"), value: kindTotal("ai_draft"), note: ctrans(":share sent as written", { share: share(props.draftStats.used) }), icon: faRobot, color: "text-violet-600" },
    { label: ctrans("Noise checks"), value: props.dashboard.checks, note: ctrans(":count overruled by staff", { count: props.dashboard.overruled }), icon: faFilter, color: "text-amber-600" },
    { label: ctrans("Sent without staff"), value: props.draftStats.auto_sent, note: ctrans("Not switched on yet"), icon: faBolt, color: "text-emerald-600" },
    { label: ctrans("Promises kept"), value: `${props.dashboard.promises.kept} / ${props.dashboard.promises.made}`, note: ctrans("Answered within an hour of opening"), icon: faClock, color: "text-sky-600" },
])

const percentOf = (count: number, total: number) => total ? Math.round(100 * count / total) + "%" : "—"

const helpRows = computed(() => {
    const readings = props.dashboard.readings

    return [
        { label: ctrans("AI draft replies"), offered: readings.drafts, used: readings.drafts_used },
        { label: ctrans("Guides"), offered: readings.guides, used: readings.guides_used },
        { label: ctrans("Facts from aiku"), offered: readings.facts, used: null },
        { label: ctrans("Programmers card"), offered: readings.engineer, used: readings.engineer_used },
    ]
})

const dayLabel = (date: string) => new Date(date + "T00:00:00").toLocaleDateString(undefined, { day: "numeric", month: "short" })

const line = (label: string, key: "sent" | "drafts" | "noise" | "genuine", color: string) => ({
    label,
    data: props.dashboard.daily.map((row) => row[key]),
    borderColor: color,
    backgroundColor: color,
    tension: 0,
    borderWidth: 1.5,
    pointRadius: 2,
})

const lineChart = computed(() => ({
    labels: props.dashboard.daily.map((row) => dayLabel(row.date)),
    datasets: [
        line(ctrans("Sent to customers"), "sent", "#4f46e5"),
        line(ctrans("AI draft replies"), "drafts", "#7c3aed"),
        line(ctrans("Noise"), "noise", "#d97706"),
        line(ctrans("Genuine"), "genuine", "#1f845a"),
    ],
}))

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: { legend: { position: "bottom", labels: { boxWidth: 12 } } },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 10, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
}

const VERDICT_COLORS = ["#1f845a", "#d97706", "#dc2626", "#7c3aed", "#0ea5e9", "#6b7280", "#db2777"]

const donutChart = computed(() => ({
    labels: props.dashboard.verdicts.map((row) => row.label),
    datasets: [{
        data: props.dashboard.verdicts.map((row) => row.total),
        backgroundColor: props.dashboard.verdicts.map((row, index) => row.verdict === "genuine" ? "#1f845a" : VERDICT_COLORS[(index % (VERDICT_COLORS.length - 1)) + 1]),
    }],
}))

const donutOptions = { responsive: true, maintainAspectRatio: false, cutout: "70%", plugins: { legend: { display: false } } }

const colorOf = (index: number) => donutChart.value.datasets[0].backgroundColor[index]
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4">
        <ChatAiCoverage :coverage="coverage" />

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="card in cards" :key="card.label" class="rounded-lg border border-gray-200 px-4 py-3">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <FontAwesomeIcon :icon="card.icon" :class="card.color" fixed-width />
                    {{ card.label }}
                </div>
                <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ card.value }}</div>
                <div class="text-xs text-gray-500">{{ card.note }}</div>
            </div>
        </div>

        <DashboardWidgetBox storageKey="chat_ai_dashboard_daily_collapsed">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon :icon="faChartLine" class="text-indigo-600" fixed-width />
                    {{ ctrans("Every day, last 30 days") }}
                </span>
            </template>
            <div class="h-72">
                <Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
            </div>
        </DashboardWidgetBox>

        <DashboardWidgetBox storageKey="chat_ai_dashboard_help_collapsed">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon :icon="faHandsHelping" class="text-emerald-600" fixed-width />
                    {{ ctrans("Help offered in the inbox, last 30 days") }}
                </span>
            </template>
            <p class="text-sm text-gray-700">
                {{ ctrans(":share of :count customer messages had something to offer", { share: percentOf(dashboard.readings.helped, dashboard.readings.read), count: dashboard.readings.read }) }}
            </p>
            <table class="mt-2 w-full text-sm tabular-nums">
                <thead>
                    <tr class="text-left text-xs text-gray-500">
                        <th class="py-1 font-normal"></th>
                        <th class="py-1 text-right font-normal">{{ ctrans("Offered") }}</th>
                        <th class="py-1 text-right font-normal">{{ ctrans("Of all messages") }}</th>
                        <th class="py-1 text-right font-normal">{{ ctrans("Used by staff") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in helpRows" :key="row.label" class="border-b border-gray-100 last:border-0">
                        <td class="py-1.5 text-gray-700">{{ row.label }}</td>
                        <td class="py-1.5 text-right">{{ row.offered }}</td>
                        <td class="py-1.5 text-right text-gray-500">{{ percentOf(row.offered, dashboard.readings.read) }}</td>
                        <td class="py-1.5 text-right">{{ row.used === null ? "—" : `${row.used} (${percentOf(row.used, row.offered)})` }}</td>
                    </tr>
                </tbody>
            </table>
        </DashboardWidgetBox>

        <DashboardWidgetBox storageKey="chat_ai_dashboard_review_collapsed">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon :icon="faHandsHelping" class="text-sky-600" fixed-width />
                    {{ ctrans("Suggested and what staff wrote, latest 30") }}
                </span>
            </template>
            <p v-if="!dashboard.review.length" class="text-sm text-gray-500">{{ ctrans("Nothing answered yet.") }}</p>
            <div v-for="row in dashboard.review" :key="row.id" class="grid gap-3 border-b border-gray-100 py-2 text-xs last:border-0 md:grid-cols-3">
                <div>
                    <div class="text-[11px] text-gray-400">{{ row.shop }} · {{ row.branch ?? ctrans("no area") }}</div>
                    <p class="line-clamp-4 whitespace-pre-line text-gray-800" :title="row.customer ?? ''">{{ row.customer }}</p>
                </div>
                <div>
                    <div class="text-[11px] text-gray-400">{{ ctrans("Suggested") }}<span v-if="row.used || row.draft_status"> · {{ row.used ?? row.draft_status }}</span></div>
                    <p v-if="row.draft" class="line-clamp-4 whitespace-pre-line text-indigo-800">{{ row.draft }}</p>
                    <p v-for="guide in row.suggested.guides ?? []" :key="guide" class="text-emerald-800">{{ guide }}</p>
                    <p v-for="fact in row.suggested.facts ?? []" :key="fact" class="text-sky-800">{{ fact }}</p>
                    <p v-if="row.suggested.engineer" class="text-amber-800">{{ row.suggested.engineer }}</p>
                    <p v-if="row.suggested.next_step" class="text-gray-700">{{ row.suggested.next_step }}</p>
                    <p v-if="!row.draft && !Object.keys(row.suggested).length" class="text-gray-400">{{ ctrans("Nothing") }}</p>
                </div>
                <div>
                    <div class="text-[11px] text-gray-400">{{ ctrans("Staff wrote") }}</div>
                    <p class="line-clamp-4 whitespace-pre-line text-gray-800" :title="row.reply ?? ''">{{ row.reply }}</p>
                </div>
            </div>
        </DashboardWidgetBox>

        <div class="grid gap-4 lg:grid-cols-2">
            <DashboardWidgetBox storageKey="chat_ai_dashboard_sent_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon :icon="faPaperPlane" class="text-indigo-600" fixed-width />
                        {{ ctrans("What reached customers, last 30 days") }}
                    </span>
                </template>
                <table class="w-full text-sm tabular-nums">
                    <tbody>
                        <tr v-for="row in dashboard.by_kind" :key="row.kind" class="border-b border-gray-100 last:border-0">
                            <td class="py-1.5 text-gray-700">{{ row.label }}</td>
                            <td class="py-1.5 text-right">
                                <span v-if="row.wrong" class="mr-2 rounded-full bg-red-50 px-1.5 py-0.5 text-xs text-red-700 ring-1 ring-inset ring-red-200">
                                    {{ ctrans(":count wrong", { count: row.wrong }) }}
                                </span>
                                <span class="font-medium text-gray-900">{{ row.total }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-gray-100 pt-3 text-xs">
                    <span class="font-medium text-gray-700">{{ ctrans("Drafts decided by staff") }}: {{ draftStats.decided }}</span>
                    <span class="text-emerald-700">{{ ctrans("Sent as written") }} {{ share(draftStats.used) }}</span>
                    <span class="text-sky-700">{{ ctrans("Sent after changes") }} {{ share(draftStats.edited) }}</span>
                    <span class="text-red-700">{{ ctrans("Discarded") }} {{ share(draftStats.discarded) }}</span>
                    <span class="text-gray-500">{{ ctrans("Not used") }} {{ share(draftStats.superseded) }}</span>
                    <span v-if="draftStats.pending" class="text-amber-700">{{ ctrans(":count waiting for staff", { count: draftStats.pending }) }}</span>
                </div>
                <div v-if="draftStats.assistant.decided || draftStats.assistant.pending" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    <span class="font-medium text-gray-700">{{ ctrans("From staff assistants") }}: {{ draftStats.assistant.decided }}</span>
                    <span class="text-emerald-700">{{ ctrans("Sent as written") }} {{ share(draftStats.assistant.used, draftStats.assistant.decided) }}</span>
                    <span class="text-sky-700">{{ ctrans("Sent after changes") }} {{ share(draftStats.assistant.edited, draftStats.assistant.decided) }}</span>
                    <span class="text-red-700">{{ ctrans("Discarded") }} {{ share(draftStats.assistant.discarded, draftStats.assistant.decided) }}</span>
                    <span class="text-gray-500">{{ ctrans("Not used") }} {{ share(draftStats.assistant.superseded, draftStats.assistant.decided) }}</span>
                    <span v-for="row in draftStats.assistant.clients" :key="row.client" class="text-gray-500">{{ row.client }}: {{ ctrans(":drafts drafts by :people people", { drafts: row.drafts, people: row.people }) }}</span>
                </div>
            </DashboardWidgetBox>

            <DashboardWidgetBox storageKey="chat_ai_dashboard_noise_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon :icon="faChartPie" class="text-amber-600" fixed-width />
                        {{ ctrans("Noise checks, last 30 days") }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ ctrans(":count put aside automatically", { count: dashboard.put_aside }) }} · {{ ctrans(":count overruled by staff", { count: dashboard.overruled }) }}
                    </span>
                </template>
                <div v-if="dashboard.checks" class="flex items-center gap-4">
                    <div class="relative h-40 w-40 shrink-0">
                        <Chart type="doughnut" :data="donutChart" :options="donutOptions" class="relative z-10 h-full" />
                        <div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold">{{ dashboard.checks }}</span>
                            <span class="text-xs text-gray-500">{{ ctrans("Checked") }}</span>
                        </div>
                    </div>
                    <table class="text-sm tabular-nums">
                        <tbody>
                            <tr v-for="(row, index) in dashboard.verdicts" :key="row.verdict">
                                <td class="py-1 pr-4">
                                    <span class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: colorOf(index) }" />
                                        {{ row.label }}
                                    </span>
                                </td>
                                <td class="py-1 text-right font-medium">{{ row.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-500">{{ ctrans("No noise checks in the last 30 days") }}</p>
            </DashboardWidgetBox>
        </div>

        <div class="rounded-lg border border-gray-200 px-4 py-3 text-sm">
            <div class="flex flex-wrap items-baseline gap-x-3">
                <span class="font-medium text-gray-900">{{ ctrans("Could the AI answer alone?") }}</span>
                <span class="text-xs text-gray-500">{{ ctrans("Drafts staff decided in the last :days days, measured by shop, what the customer wanted and what the reply does. Nothing is sent without a person yet.", { days: confidence.days }) }}</span>
                <Link :href="route('grp.chat.ai.confidence')" class="ml-auto text-xs text-gray-700 underline hover:text-gray-900">{{ ctrans("Open the confidence map") }}</Link>
            </div>
            <div v-if="confidence.decided" class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-4">
                <div><span class="font-medium tabular-nums text-gray-900">{{ percentOf(confidence.alone_clean, confidence.decided) }}</span> <span class="text-gray-500">{{ ctrans("could go alone (:count of :total)", { count: confidence.alone_clean, total: confidence.decided }) }}</span></div>
                <div><span class="font-medium tabular-nums text-gray-900">{{ percentOf(confidence.gaps_filled, confidence.decided) }}</span> <span class="text-gray-500">{{ ctrans("needed staff to fill gaps") }}</span></div>
                <div><span class="font-medium tabular-nums text-gray-900">{{ confidence.would_send ? percentOf(confidence.would_send_right, confidence.would_send) : "—" }}</span> <span class="text-gray-500">{{ ctrans("right when the AI said it would send (:count)", { count: confidence.would_send }) }}</span></div>
                <div><span class="font-medium tabular-nums" :class="confidence.harms ? 'text-red-700' : 'text-gray-900'">{{ confidence.harms }}</span> <span class="text-gray-500">{{ ctrans("harms: flagged wrong or customer unhappy") }}</span></div>
            </div>
            <ul v-if="confidence.best.length" class="mt-2 space-y-1">
                <li v-for="cell in confidence.best" :key="cell.shop + cell.intent + cell.risk" class="flex flex-wrap items-center gap-x-3 text-xs">
                    <span class="w-12 text-right font-medium tabular-nums text-gray-900">{{ Math.round(cell.lower_bound * 100) }}%</span>
                    <span class="text-gray-800">{{ cell.shop }} · {{ cell.intent.replaceAll("_", " ") }} · {{ cell.risk.replaceAll("_", " ") }}</span>
                    <span class="text-gray-500">{{ ctrans(":count of :total could go alone", { count: cell.alone_clean, total: cell.decided }) }}</span>
                </li>
            </ul>
            <p v-else class="mt-1 text-xs text-gray-500">{{ ctrans("No AI drafts measured yet in this period") }}</p>
        </div>
    </div>
</template>
