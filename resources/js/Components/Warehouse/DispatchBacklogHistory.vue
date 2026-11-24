<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import Chart from "primevue/chart"
import { useLocalStorage } from "@vueuse/core"
import { ctrans } from "@/Composables/useTrans"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChartLine } from "@fal"

library.add(faChartLine)

type Day = { backlog: number; created: number; dispatched: number; cancelled: number }

const props = defineProps<{
    history: {
        interval: string
        options: Record<string, string>
        channels: { value: string; label: string }[]
        daily: { date: string; channels: Record<string, Day> }[]
    }
}>()

const selectedChannel = useLocalStorage<string>("dispatch-backlog-channel", "all")

const channelOptions = computed(() => [{ value: "all", label: ctrans("All") }, ...props.history.channels])
const channel = computed(() => (channelOptions.value.some((option) => option.value === selectedChannel.value) ? selectedChannel.value : "all"))

const days = computed(() =>
    props.history.daily.map((day) => {
        const picked = channel.value === "all" ? Object.values(day.channels) : [day.channels[channel.value]].filter(Boolean)

        return {
            date: day.date,
            backlog: picked.reduce((sum, value) => sum + value.backlog, 0),
            created: picked.reduce((sum, value) => sum + value.created, 0),
            dispatched: picked.reduce((sum, value) => sum + value.dispatched, 0),
        }
    })
)

const change = computed(() => (days.value.length ? days.value[days.value.length - 1].backlog - days.value[0].backlog : 0))

const chartData = computed(() => {
    const pointRadius = days.value.length > 40 ? 0 : 2
    return {
        labels: days.value.map((day) => new Date(`${day.date}T00:00:00`).toLocaleDateString(undefined, { day: "numeric", month: "short" })),
        datasets: [
            { label: ctrans("Backlog"), data: days.value.map((day) => day.backlog), borderColor: "#f59e0b", backgroundColor: "#f59e0b", tension: 0, borderWidth: 2.5, pointRadius },
            { label: ctrans("New"), data: days.value.map((day) => day.created), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0, borderWidth: 1.5, pointRadius },
            { label: ctrans("Dispatched"), data: days.value.map((day) => day.dispatched), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius },
        ],
    }
})

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: { legend: { position: "bottom", labels: { boxWidth: 12 } } },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
}
</script>

<template>
    <div>
    <div class="flex flex-wrap items-center gap-3 px-4 pt-4">
        <TicketsCreatedInterval :options="history.options" :selected="history.interval" storageKey="dispatch-backlog-interval" param="backlog" label="" :only="['backlog_history']" compact />
        <SegmentedToggle v-if="history.channels.length > 1" v-model="selectedChannel" :options="channelOptions" :ariaLabel="ctrans('Channel')" />
    </div>
    <div class="px-4 py-4">
        <DashboardWidgetBox storageKey="dispatch_backlog_history_collapsed">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon icon="fal fa-chart-line" class="text-amber-500" fixed-width aria-hidden="true" />
                    {{ ctrans("Backlog per day") }}
                </span>
                <span class="text-xs text-gray-400">
                    {{ days.at(-1)?.backlog ?? 0 }} {{ ctrans("now") }} ·
                    <span :class="change > 0 ? 'text-red-600' : change < 0 ? 'text-green-600' : ''">{{ change > 0 ? "+" : "" }}{{ change }}</span>
                    {{ ctrans("in this period") }}
                </span>
            </template>
            <div class="h-72">
                <Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
            </div>
        </DashboardWidgetBox>
    </div>
    </div>
</template>
