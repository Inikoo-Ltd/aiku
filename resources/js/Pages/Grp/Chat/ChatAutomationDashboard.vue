<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed } from "vue"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChartLine, faChartPie, faPaperPlane, faRobot, faFilter, faBolt, faClock } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"

interface Dashboard {
    daily: { date: string, sent: number, drafts: number, noise: number, genuine: number }[]
    by_kind: { kind: string, label: string, total: number, wrong: number }[]
    verdicts: { verdict: string, label: string, total: number }[]
    checks: number
    put_aside: number
    overruled: number
    promises: { made: number, kept: number }
}

const props = defineProps<{
    title: string
    pageHead: object
    dashboard: Dashboard
    draftStats: { decided: number, used: number, edited: number, discarded: number, superseded: number, pending: number, auto_sent: number }
    autoSend: {
        enabled: boolean
        min_decided: number
        min_used_share: number
        gates: { shop: string, topic: string, earned: boolean, decided: number, used_share: number, flagged: number }[]
    }
}>()

const kindTotal = (kind: string) => props.dashboard.by_kind.find((row) => row.kind === kind)?.total ?? 0
const sentTotal = computed(() => props.dashboard.by_kind.filter((row) => row.kind !== "ai_draft").reduce((sum, row) => sum + row.total, 0))
const share = (count: number) => props.draftStats.decided ? Math.round(100 * count / props.draftStats.decided) + "%" : "—"

const cards = computed(() => [
    { label: ctrans("Sent to customers"), value: sentTotal.value, note: ctrans(":count claim checklists", { count: kindTotal("claim_details") }), icon: faPaperPlane, color: "text-indigo-600" },
    { label: ctrans("AI draft replies"), value: kindTotal("ai_draft"), note: ctrans(":share sent as written", { share: share(props.draftStats.used) }), icon: faRobot, color: "text-violet-600" },
    { label: ctrans("Noise checks"), value: props.dashboard.checks, note: ctrans(":count overruled by staff", { count: props.dashboard.overruled }), icon: faFilter, color: "text-amber-600" },
    { label: ctrans("Sent without staff"), value: props.draftStats.auto_sent, note: props.autoSend.enabled ? ctrans("Switched on where earned") : ctrans("Switched off"), icon: faBolt, color: "text-emerald-600" },
    { label: ctrans("Promises kept"), value: `${props.dashboard.promises.kept} / ${props.dashboard.promises.made}`, note: ctrans("Answered within an hour of opening"), icon: faClock, color: "text-sky-600" },
])

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
                    <span v-if="draftStats.pending" class="text-indigo-700">{{ ctrans(":count waiting for staff", { count: draftStats.pending }) }}</span>
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
                        <Chart type="doughnut" :data="donutChart" :options="donutOptions" class="h-full" />
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
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
                <span class="font-medium text-gray-900">{{ ctrans("Sent without staff, out of hours") }}</span>
                <span :class="autoSend.enabled ? 'text-emerald-700' : 'text-gray-500'">
                    {{ autoSend.enabled ? ctrans("Switched on where earned") : ctrans("Switched off") }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ ctrans("Earned by a shop and topic with at least :count drafts decided in 30 days, :share sent as written, and none flagged as wrong", { count: autoSend.min_decided, share: Math.round(autoSend.min_used_share * 100) + "%" }) }}
                </span>
            </div>
            <ul v-if="autoSend.gates.length" class="mt-2 space-y-1">
                <li v-for="gate in autoSend.gates" :key="gate.shop + gate.topic" class="flex flex-wrap items-center gap-x-3 text-xs">
                    <span class="rounded-full px-2 py-0.5 ring-1 ring-inset"
                        :class="gate.earned ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-gray-50 text-gray-600 ring-gray-200'">
                        {{ gate.earned ? ctrans("Earned") : ctrans("Not yet") }}
                    </span>
                    <span class="text-gray-800">{{ gate.shop }} · {{ gate.topic }}</span>
                    <span class="text-gray-500">{{ ctrans(":count decided", { count: gate.decided }) }} · {{ Math.round(gate.used_share * 100) }}% {{ ctrans("sent as written") }}</span>
                    <span v-if="gate.flagged" class="text-red-700">{{ ctrans(":count flagged as wrong", { count: gate.flagged }) }}</span>
                </li>
            </ul>
            <p v-else class="mt-1 text-xs text-gray-500">{{ ctrans("No AI drafts yet in the last 30 days") }}</p>
        </div>
    </div>
</template>
