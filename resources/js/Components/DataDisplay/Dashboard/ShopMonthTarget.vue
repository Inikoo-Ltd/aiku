<script setup lang="ts">
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faBullseyeArrow, faChartPie } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faPencil, faBullseyeArrow, faChartPie)

const COLORS = { invoiced: "#1f845a", pipeline: "#f59e0b", needed: "#e5e7eb", thisYear: "#4f46e5", lastYear: "#9ca3af", target: "#1f845a" }

interface MonthTarget {
    month: string
    month_label: string
    last_year_label: string
    currency_code: string
    day_of_month: number
    days_in_month: number
    sales_so_far: number
    last_year_so_far: number
    last_year_total: number
    expected: number
    pipeline: { amount: number, orders: number, submitted_amount: number, in_warehouse_amount: number }
    target: { amount: number | null, is_default: boolean, growth: number, set_by: string | null }
    gap: number | null
    needed_per_day: number | null
    remaining_days: number
    chart: { days: number[], this_year: number[], last_year: number[] }
    can_edit: boolean
    update_route: { name: string, parameters: Record<string, number> }
}

const props = defineProps<{
    monthTarget: MonthTarget
}>()

const money = (amount: number | null) =>
    new Intl.NumberFormat(undefined, { style: "currency", currency: props.monthTarget.currency_code, maximumFractionDigits: 0 }).format(Math.round(amount ?? 0))

const shortMoney = (amount: number) =>
    new Intl.NumberFormat(undefined, { style: "currency", currency: props.monthTarget.currency_code, notation: "compact", maximumFractionDigits: 1 }).format(amount)

const percentOf = (part: number, whole: number | null) => (whole ? Math.min(100, (part / whole) * 100) : 0)

const target = computed(() => props.monthTarget.target.amount)

const invoicedWidth = computed(() => percentOf(props.monthTarget.sales_so_far, target.value))

const versusLastYear = computed(() => {
    if (!props.monthTarget.last_year_so_far) {
        return null
    }
    return ((props.monthTarget.sales_so_far / props.monthTarget.last_year_so_far) - 1) * 100
})

const expectedVersusTarget = computed(() => (target.value ? (props.monthTarget.expected / target.value) * 100 : null))

const isEditing = ref(false)
const isSaving = ref(false)
const newTarget = ref<number | null>(null)

const startEditing = () => {
    newTarget.value = target.value ? Math.round(target.value) : null
    isEditing.value = true
}

const saveTarget = async () => {
    if (newTarget.value === null || newTarget.value < 0) {
        return
    }
    isSaving.value = true
    try {
        await axios.patch(route(props.monthTarget.update_route.name, props.monthTarget.update_route.parameters), {
            target_org_currency: newTarget.value,
            month: props.monthTarget.month,
        })
        isEditing.value = false
        router.reload({ only: ["dashboard"] })
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("The target was not saved"),
            type: "error",
        })
    } finally {
        isSaving.value = false
    }
}

const [year, month] = props.monthTarget.month.split("-").map(Number)
const dayLabel = (day: number) => new Date(year, month - 1, day).toLocaleDateString(undefined, { day: "numeric", month: "short" })

const chartData = computed(() => ({
    labels: props.monthTarget.chart.days.map(dayLabel),
    datasets: [
        { label: props.monthTarget.month_label, data: props.monthTarget.chart.this_year, borderColor: COLORS.thisYear, backgroundColor: COLORS.thisYear, tension: 0, borderWidth: 1.5, pointRadius: 2 },
        { label: props.monthTarget.last_year_label, data: props.monthTarget.chart.last_year, borderColor: COLORS.lastYear, backgroundColor: COLORS.lastYear, borderDash: [4, 3], tension: 0, borderWidth: 1.5, pointRadius: 0 },
        ...(target.value ? [{ label: ctrans("Target"), data: props.monthTarget.chart.days.map(() => target.value), borderColor: COLORS.target, backgroundColor: COLORS.target, borderDash: [8, 4], borderWidth: 1.5, pointRadius: 0 }] : []),
    ],
}))

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 12 } },
        tooltip: { callbacks: { label: (item: any) => `${item.dataset.label}: ${money(item.raw)}` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { callback: (value: number) => shortMoney(value) } },
    },
}))

const neededAfterPipeline = computed(() => Math.max(0, (target.value ?? 0) - props.monthTarget.sales_so_far - props.monthTarget.pipeline.amount))

const breakdown = computed(() => [
    { key: "invoiced", label: ctrans("Invoiced"), amount: props.monthTarget.sales_so_far, color: COLORS.invoiced },
    { key: "pipeline", label: ctrans("In the warehouse pipeline"), amount: props.monthTarget.pipeline.amount, color: COLORS.pipeline },
    { key: "needed", label: ctrans("Still needed"), amount: neededAfterPipeline.value, color: COLORS.needed },
])

const donutData = computed(() => ({
    labels: breakdown.value.map((row) => row.label),
    datasets: [{ data: breakdown.value.map((row) => row.amount), backgroundColor: breakdown.value.map((row) => row.color) }],
}))

const donutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: "70%",
    plugins: {
        legend: { display: false },
        tooltip: { padding: 10, boxPadding: 6, callbacks: { label: (item: { raw: number }) => money(item.raw) } },
    },
}
</script>

<template>
    <DashboardWidgetBox storageKey="shop_dashboard_month_target_collapsed" class="mx-4 mt-4">
        <template #header>
            <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                <FontAwesomeIcon icon="fal fa-bullseye-arrow" class="text-indigo-600" fixed-width aria-hidden="true" />
                {{ ctrans(":month target", { month: monthTarget.month_label }) }}
            </span>
            <span class="text-xs text-gray-400">
                {{ ctrans(":invoiced invoiced · :pipeline in the pipeline · :days days left", { invoiced: money(monthTarget.sales_so_far), pipeline: money(monthTarget.pipeline.amount), days: String(monthTarget.remaining_days) }) }}
            </span>
        </template>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="h-72 lg:col-span-3">
                <Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
            </div>

            <div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <form v-if="isEditing" class="flex items-center gap-2" @submit.prevent="saveTarget">
                            <input v-model.number="newTarget" type="number" min="0" step="1" class="w-36 rounded border-gray-300 text-lg font-bold" :aria-label="ctrans('Target')" autofocus />
                            <button type="submit" :disabled="isSaving" class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white disabled:opacity-50">{{ ctrans("Save") }}</button>
                            <button type="button" class="text-sm text-gray-500" @click="isEditing = false">{{ ctrans("Cancel") }}</button>
                        </form>
                        <p v-else class="text-2xl font-bold tabular-nums">
                            {{ target ? money(target) : ctrans("No target") }}
                            <button v-if="monthTarget.can_edit" type="button" class="ml-1 align-middle text-sm text-gray-400 hover:text-gray-700" :aria-label="ctrans('Edit target')" @click="startEditing">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                            </button>
                        </p>
                        <p class="text-xs text-gray-400">
                            <template v-if="!monthTarget.target.is_default">{{ ctrans("Target set by :name", { name: monthTarget.target.set_by ?? ctrans("management") }) }}</template>
                            <template v-else-if="target">{{ ctrans("Target: :last_year sales plus :growth%", { last_year: monthTarget.last_year_label, growth: String(Math.round(monthTarget.target.growth * 100)) }) }}</template>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">{{ ctrans("Expected by month end") }}</p>
                        <p class="text-2xl font-bold tabular-nums" :class="expectedVersusTarget !== null && expectedVersusTarget < 100 ? 'text-red-600' : 'text-green-600'">{{ money(monthTarget.expected) }}</p>
                        <p v-if="expectedVersusTarget !== null" class="text-xs text-gray-400">{{ ctrans(":percent% of target", { percent: String(Math.round(expectedVersusTarget)) }) }}</p>
                    </div>
                </div>

                <div v-if="target" class="flex items-center gap-4">
                    <div class="relative h-40 w-40 shrink-0">
                        <Chart type="doughnut" :data="donutData" :options="donutOptions" class="relative z-10 h-full" />
                        <div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
                            <span class="text-3xl font-bold">{{ Math.round(invoicedWidth) }}%</span>
                            <span class="text-xs text-gray-500">{{ ctrans("invoiced") }}</span>
                        </div>
                    </div>
                    <table class="text-sm tabular-nums">
                        <tbody>
                            <tr v-for="row in breakdown" :key="row.key">
                                <td class="py-1 pr-5">
                                    <span class="flex items-center gap-2">
                                        <span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: row.color }" />
                                        {{ row.label }}
                                    </span>
                                </td>
                                <td class="py-1 pr-5 text-right font-medium">{{ money(row.amount) }}</td>
                                <td class="py-1 text-right text-gray-500">{{ percentOf(row.amount, target).toFixed(0) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mt-3 text-xs text-gray-500">
                    <span v-if="versusLastYear !== null" :class="versusLastYear < 0 ? 'text-red-600' : 'text-green-600'">{{ ctrans(":change% vs same days last year", { change: (versusLastYear > 0 ? "+" : "") + versusLastYear.toFixed(1) }) }}</span>
                    <template v-if="monthTarget.needed_per_day"> · {{ ctrans(":amount needed per day", { amount: money(monthTarget.needed_per_day) }) }}</template>
                    · {{ ctrans(":orders orders in the pipeline", { orders: String(monthTarget.pipeline.orders) }) }}
                </p>
            </div>
        </div>
    </DashboardWidgetBox>
</template>
