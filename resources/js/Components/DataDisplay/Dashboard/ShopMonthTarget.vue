<script setup lang="ts">
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { Link, router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faBullseyeArrow, faChartPie, faChevronDown, faExternalLink, faLightbulb } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faPencil, faBullseyeArrow, faChartPie, faChevronDown, faExternalLink, faLightbulb)

const COLORS = { invoiced: "#1f845a", pipeline: "#f59e0b", needed: "#e5e7eb", lastYear: "#0ea5e9", target: "#334155", thisYear: "#1f845a", forecastBand: "rgba(31, 132, 90, 0.15)" }

interface PeriodTarget {
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
    target: { amount: number | null, is_default: boolean, is_sum_of_shops?: boolean, is_sum_of_categories?: boolean, is_share?: boolean, months_set?: number, growth: number, set_by: string | null }
    gap: number | null
    needed_per_day: number | null
    needed_this_week?: number | null
    tip?: string | null
    remaining_days: number
    chart: { days: number[], this_year: number[], last_year: number[], weekly_versus_last_year?: { x: number, y: number | null }[], forecast?: { expected: (number | null)[], low: (number | null)[], high: (number | null)[] } | null }
    granularity?: "month" | "year"
    children?: ChildTarget[]
    children_label?: string
    selection_setting?: string
    selected_child?: string
    selected_period?: "month" | "year"
    can_edit: boolean
    update_route: { name: string, parameters: Record<string, number> } | null
}

interface ChildTarget extends PeriodTarget {
    key: string
    invoice_category_id?: number | null
    name: string
    link?: { name: string, parameters: Record<string, string> }
}

const props = defineProps<{
    monthTarget: PeriodTarget
    yearTarget?: PeriodTarget
}>()

const activePeriod = ref<"month" | "year">(props.monthTarget.selected_period === "year" && props.yearTarget ? "year" : "month")
const children = computed(() => props.monthTarget.children ?? [])
const selectedChildKey = ref(children.value.some((child) => child.key === props.monthTarget.selected_child) ? props.monthTarget.selected_child! : "all")
const selectedChild = computed(() => children.value.find((child) => child.key === selectedChildKey.value) ?? null)
const showAllCategories = ref(false)

const yearChild = computed(() => props.yearTarget?.children?.find((child) => child.key === selectedChildKey.value) ?? null)

const isYearView = computed(() => activePeriod.value === "year" && !!props.yearTarget)

const chips = computed(() => [
    { key: "all", name: ctrans("All"), block: (isYearView.value ? props.yearTarget : props.monthTarget) as PeriodTarget },
    ...children.value.map((child) => ({
        key: child.key,
        name: child.name,
        block: (isYearView.value ? props.yearTarget?.children?.find((yearChild) => yearChild.key === child.key) ?? child : child) as PeriodTarget,
    })),
])

const periodData = computed<PeriodTarget>(() => {
    if (activePeriod.value === "year" && props.yearTarget) {
        return selectedChildKey.value === "all" ? props.yearTarget : yearChild.value ?? props.yearTarget
    }
    return selectedChild.value ?? props.monthTarget
})

const money = (amount: number | null) =>
    new Intl.NumberFormat(undefined, { style: "currency", currency: periodData.value.currency_code, maximumFractionDigits: 0 }).format(Math.round(amount ?? 0))

const shortMoney = (amount: number) =>
    new Intl.NumberFormat(undefined, { style: "currency", currency: periodData.value.currency_code, notation: "compact", maximumFractionDigits: 1 }).format(amount)

const percentOf = (part: number, whole: number | null) => (whole ? Math.min(100, (part / whole) * 100) : 0)

const target = computed(() => periodData.value.target.amount)

const invoicedWidth = computed(() => percentOf(periodData.value.sales_so_far, target.value))

const versusLastYear = computed(() => {
    if (!periodData.value.last_year_so_far) {
        return null
    }
    return ((periodData.value.sales_so_far / periodData.value.last_year_so_far) - 1) * 100
})

const expectedVersusTarget = computed(() => (target.value ? (periodData.value.expected / target.value) * 100 : null))
const expectedVersusTargetLabel = computed(() => {
    if (expectedVersusTarget.value === null) {
        return ""
    }

    const percent = Math.round(expectedVersusTarget.value)

    if (percent > 100) {
        return ctrans(":percent% above target", { percent: String(percent - 100) })
    }

    return percent === 100 ? ctrans("On target") : ctrans(":percent% of target", { percent: String(percent) })
})

const isEditing = ref(false)
const isSaving = ref(false)
const newTarget = ref<number | null>(null)
const canEditTarget = computed(() => periodData.value.can_edit && !periodData.value.target.is_sum_of_categories)

const selectPeriod = (period: "month" | "year") => {
    isEditing.value = false
    activePeriod.value = period
    axios.patch(route("grp.models.profile.update"), { settings: { sales_target_period: period } })
}

const selectChild = (key: string) => {
    isEditing.value = false
    if (!props.yearTarget?.children?.some((child) => child.key === key)) {
        activePeriod.value = "month"
    }
    selectedChildKey.value = key
    if (props.monthTarget.selection_setting) {
        axios.patch(route("grp.models.profile.update"), { settings: { [props.monthTarget.selection_setting]: key } })
    }
}

const invoicedPercent = (block: PeriodTarget) => (block.target.amount ? Math.round((block.sales_so_far / block.target.amount) * 100) : null)

const startEditing = () => {
    newTarget.value = target.value ? Math.round(target.value) : null
    isEditing.value = true
}

const saveTarget = async () => {
    if (newTarget.value === null || newTarget.value < 0 || !periodData.value.update_route) {
        return
    }
    isSaving.value = true
    try {
        await axios.patch(route(periodData.value.update_route.name, periodData.value.update_route.parameters), {
            target_org_currency: newTarget.value,
            month: periodData.value.month,
            invoice_category_id: selectedChild.value?.invoice_category_id ?? null,
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

const dayLabel = (unit: number) => {
    const [year, month] = periodData.value.month.split("-").map(Number)
    return periodData.value.granularity === "year"
        ? new Date(year, unit - 1, 1).toLocaleDateString(undefined, { month: "short" })
        : new Date(year, month - 1, unit).toLocaleDateString(undefined, { day: "numeric", month: "short" })
}

const forecast = computed(() => periodData.value.chart.forecast ?? null)

const isYear = computed(() => periodData.value.granularity === "year")

const onMonthAxis = (series: (number | null)[]) => isYear.value ? series.map((y, index) => ({ x: index + 1, y })) : series

const thisYearLine = computed(() => {
    const points = onMonthAxis(periodData.value.chart.this_year) as any[]
    if (!isYear.value || !points.length) {
        return points
    }
    const today = new Date()
    const daysInMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate()
    points[points.length - 1].x = points.length - 1 + today.getDate() / daysInMonth
    return points
})

const chartData = computed(() => ({
    labels: periodData.value.chart.days.map(dayLabel),
    datasets: [
        { label: periodData.value.month_label, data: thisYearLine.value, borderColor: COLORS.thisYear, backgroundColor: COLORS.thisYear, tension: 0, borderWidth: 1.5, pointRadius: 2 },
        ...(forecast.value ? [
            { key: "forecast", label: ctrans("Forecast"), data: onMonthAxis(forecast.value.expected), borderColor: "transparent", backgroundColor: "transparent", tension: 0, borderWidth: 0, pointRadius: 0, inLegend: false },
            { key: "band", label: ctrans("Forecast"), data: onMonthAxis(forecast.value.high), borderColor: "transparent", backgroundColor: COLORS.forecastBand, fill: "+1", tension: 0, borderWidth: 0, pointRadius: 0, order: 10, isBand: true },
            { key: "band_low", label: "", data: onMonthAxis(forecast.value.low), borderColor: "transparent", backgroundColor: COLORS.forecastBand, tension: 0, borderWidth: 0, pointRadius: 0, order: 10, inLegend: false },
        ] : []),
        { label: periodData.value.last_year_label, data: onMonthAxis(periodData.value.chart.last_year), borderColor: COLORS.lastYear, backgroundColor: COLORS.lastYear, borderDash: [4, 3], tension: 0, borderWidth: 1.5, pointRadius: 0 },
        ...(target.value ? [{ label: ctrans("Target"), data: onMonthAxis(periodData.value.chart.days.map(() => target.value)), borderColor: COLORS.target, backgroundColor: COLORS.target, borderDash: [8, 4], borderWidth: 1.5, pointRadius: 0 }] : []),
    ],
}))

const aheadBehindLine = computed(() => {
    const thisYear = periodData.value.chart.this_year
    const lastYear = periodData.value.chart.last_year
    return thisYearLine.value.map((point: any, index: number) => {
        const isPartialMonth = index === thisYear.length - 1
        const comparedTo = isPartialMonth ? periodData.value.last_year_so_far : lastYear[index]
        const sales = isPartialMonth ? periodData.value.sales_so_far : thisYear[index]
        return { x: point.x, y: comparedTo ? Math.round(((sales / comparedTo) - 1) * 1000) / 10 : null }
    })
})

const weekLabel = (monthPosition: number) => {
    const year = Number(periodData.value.month.slice(0, 4))
    const monthIndex = Math.min(11, Math.floor(monthPosition - 0.0001))
    const day = Math.max(1, Math.round((monthPosition - monthIndex) * new Date(year, monthIndex + 1, 0).getDate()))
    return new Date(year, monthIndex, day).toLocaleDateString(undefined, { day: "numeric", month: "short" })
}

const percentOfLastYear = (series: (number | null)[]) => series.map((sales, index) => {
    const lastYear = periodData.value.chart.last_year[index]
    return { x: index + 1, y: sales !== null && lastYear ? Math.round(((sales / lastYear) - 1) * 1000) / 10 : null }
})

const aheadBehindForecast = computed(() => forecast.value
    ? { expected: percentOfLastYear(forecast.value.expected), low: percentOfLastYear(forecast.value.low), high: percentOfLastYear(forecast.value.high) }
    : null)

const aheadBehindData = computed(() => ({
    datasets: [
        ...(aheadBehindForecast.value ? [
            { key: "forecast", label: ctrans("Forecast"), data: aheadBehindForecast.value.expected, borderColor: COLORS.thisYear, borderDash: [4, 3], tension: 0, borderWidth: 1.5, pointRadius: 0 },
            { key: "band", label: "", data: aheadBehindForecast.value.high, borderColor: "transparent", backgroundColor: COLORS.forecastBand, fill: "+1", tension: 0, borderWidth: 0, pointRadius: 0 },
            { key: "band_low", label: "", data: aheadBehindForecast.value.low, borderColor: "transparent", tension: 0, borderWidth: 0, pointRadius: 0 },
        ] : []),
        {
            label: ctrans("vs :last_year", { last_year: periodData.value.last_year_label }),
            data: periodData.value.chart.weekly_versus_last_year ?? aheadBehindLine.value,
            borderColor: COLORS.thisYear,
            tension: 0,
            borderWidth: 1.5,
            pointRadius: periodData.value.chart.weekly_versus_last_year ? 0 : 2,
            fill: { target: { value: 0 }, above: "rgba(31, 132, 90, 0.15)", below: "rgba(220, 38, 38, 0.15)" },
            segment: { borderColor: (context: any) => context.p1.parsed.y < 0 ? "#dc2626" : COLORS.thisYear },
            pointBackgroundColor: (context: any) => context.parsed?.y < 0 ? "#dc2626" : COLORS.thisYear,
        },
    ],
}))

const monthRows = computed(() => {
    const thisYear = periodData.value.chart.this_year
    const lastYear = periodData.value.chart.last_year
    const expected = forecast.value?.expected ?? []
    const inMonth = (series: (number | null)[], index: number) => (series[index] ?? 0) - (index > 0 ? (series[index - 1] ?? 0) : 0)
    return lastYear.map((_, index) => {
        const isPartial = index === thisYear.length - 1
        const isForecast = index >= thisYear.length && expected[index] != null && expected[index - 1] != null
        const lastYearInMonth = isPartial ? periodData.value.last_year_so_far - (lastYear[index - 1] ?? 0) : inMonth(lastYear, index)
        const sales = index < thisYear.length - 1 ? inMonth(thisYear, index)
            : isPartial ? periodData.value.sales_so_far - (thisYear[index - 1] ?? 0)
            : isForecast ? inMonth(expected, index) : null
        return {
            month: index + 1,
            kind: isPartial ? "partial" : isForecast ? "forecast" : index < thisYear.length ? "actual" : "future",
            sales,
            lastYear: lastYearInMonth,
            change: sales !== null && lastYearInMonth ? ((sales / lastYearInMonth) - 1) * 100 : null,
        }
    })
})

const aheadBehindOptions = computed(() => {
    const values = [periodData.value.chart.weekly_versus_last_year ?? aheadBehindLine.value, ...Object.values(aheadBehindForecast.value ?? {})].flat().map((point: any) => Math.abs(point.y ?? 0))
    const reach = Math.max(2, 2 * Math.ceil(Math.max(...values) * 0.6))
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                filter: (item: any) => item.parsed.y !== null && !String(item.dataset.key ?? "").startsWith("band"),
                callbacks: {
                    title: (items: any[]) => items.length ? weekLabel(items[0].parsed.x) : "",
                    label: (item: any) => `${item.dataset.label}: ${item.parsed.y > 0 ? "+" : ""}${item.parsed.y}%`,
                },
            },
        },
        scales: {
            x: { type: "linear", min: 0, max: 12, grid: { display: false }, ticks: { stepSize: 1, maxRotation: 0, callback: (value: number) => value < 12 ? dayLabel(value + 1) : "" } },
            y: { min: -reach, max: reach, afterFit: (axis: any) => { axis.width = 56 }, grid: { color: (context: any) => context.tick.value === 0 ? "#64748b" : "#f1f5f9" }, ticks: { count: 5, callback: (value: number) => `${value > 0 ? "+" : ""}${Math.round(value * 10) / 10}%` } },
        },
    }
})

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            filter: (item: any) => item.parsed.y !== null && !String(item.dataset.key ?? "").startsWith("band"),
            callbacks: {
                title: (items: any[]) => isYear.value && items.length ? dayLabel(Math.ceil(items[0].parsed.x)) : items[0]?.label,
                label: (item: any) => item.dataset.key === "forecast" && forecast.value
                    ? ctrans(":label: :amount (likely :low to :high)", { label: item.dataset.label, amount: money(item.parsed.y), low: money(forecast.value.low[item.dataIndex]), high: money(forecast.value.high[item.dataIndex]) })
                    : `${item.dataset.label}: ${money(item.parsed.y)}`,
            },
        },
    },
    scales: {
        x: isYear.value
            ? { type: "linear", min: 0, max: 12, grid: { display: false }, ticks: { stepSize: 1, maxRotation: 0, callback: (value: number) => value < 12 ? dayLabel(value + 1) : "" } }
            : { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, afterFit: (axis: any) => { axis.width = 56 }, ticks: { callback: (value: number) => shortMoney(value) } },
    },
}))

const neededAfterPipeline = computed(() => Math.max(0, (target.value ?? 0) - periodData.value.sales_so_far - periodData.value.pipeline.amount))

const breakdown = computed(() => [
    { key: "invoiced", label: ctrans("Invoiced"), amount: periodData.value.sales_so_far, color: COLORS.invoiced },
    { key: "pipeline", label: ctrans("In the warehouse pipeline"), amount: periodData.value.pipeline.amount, color: COLORS.pipeline },
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
        <template #header="{ collapsed }">
            <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                <FontAwesomeIcon icon="fal fa-bullseye-arrow" class="text-[var(--theme-color-4)]" fixed-width aria-hidden="true" />
                {{ ctrans(":month target", { month: periodData.month_label }) }}
                <span v-if="selectedChild && (activePeriod === 'month' || yearChild)" class="font-normal text-gray-500">· {{ selectedChild.name }}</span>
                <Link v-if="selectedChild?.link && activePeriod === 'month'" :href="route(selectedChild.link.name, selectedChild.link.parameters)" class="text-xs font-normal text-gray-400 hover:text-gray-700" :aria-label="ctrans('Open dashboard')" @click.stop>
                    <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
                </Link>
            </span>
            <span v-if="collapsed && target" class="flex items-center gap-3 text-xs tabular-nums text-gray-500">
                <span class="relative h-2 w-28 overflow-hidden rounded-full bg-gray-100">
                    <span class="absolute inset-y-0 left-0" :style="{ width: Math.min(100, invoicedWidth) + '%', backgroundColor: COLORS.invoiced }" />
                    <span class="absolute inset-y-0" :style="{ left: Math.min(100, invoicedWidth) + '%', width: Math.max(0, Math.min(100 - invoicedWidth, percentOf(periodData.pipeline.amount, target))) + '%', backgroundColor: COLORS.pipeline }" />
                </span>
                <span>{{ ctrans(":invoiced of :target (:percent%)", { invoiced: money(periodData.sales_so_far), target: money(target), percent: String(Math.round(invoicedWidth)) }) }}</span>
                <span v-if="expectedVersusTarget !== null" :class="expectedVersusTarget < 100 ? 'text-red-600' : 'text-green-600'">
                    {{ ctrans("expected :amount (:versus_target)", { amount: money(periodData.expected), versus_target: expectedVersusTargetLabel }) }}
                </span>
                <span class="text-gray-400">{{ ctrans(":days days left", { days: String(periodData.remaining_days) }) }}</span>
            </span>
            <span v-if="!collapsed" class="ml-6 hidden items-center gap-3 text-xs text-gray-500 xl:flex">
                <span v-for="dataset in chartData.datasets.filter((dataset: any) => dataset.inLegend !== false)" :key="dataset.label" class="flex items-center gap-1.5">
                    <span v-if="(dataset as any).isBand" class="h-2.5 w-4 rounded-sm" :style="{ backgroundColor: dataset.backgroundColor }" />
                    <span v-else class="w-4 border-t-2" :class="(dataset as any).borderDash ? 'border-dashed' : ''" :style="{ borderColor: dataset.borderColor }" />
                    {{ dataset.label }}
                </span>
            </span>
            <span v-if="!collapsed || !target" class="ml-auto text-xs text-gray-400">
                {{ ctrans(":invoiced invoiced · :pipeline in the pipeline · :days days left", { invoiced: money(periodData.sales_so_far), pipeline: money(periodData.pipeline.amount), days: String(periodData.remaining_days) }) }}
            </span>
            <div v-if="yearTarget" class="flex rounded-md border border-gray-200 text-xs" :class="collapsed && target ? 'ml-auto' : ''">
                <button type="button" class="rounded-l-md px-2.5 py-1" :class="activePeriod === 'month' ? 'bg-[var(--theme-color-4)] text-[var(--theme-color-5)]' : 'text-gray-500'" @click="selectPeriod('month')">{{ ctrans("This month") }}</button>
                <button type="button" class="rounded-r-md px-2.5 py-1" :class="activePeriod === 'year' ? 'bg-[var(--theme-color-4)] text-[var(--theme-color-5)]' : 'text-gray-500'" @click="selectPeriod('year')">{{ ctrans("Year to date") }}</button>
            </div>
        </template>

        <div v-if="children.length" class="mb-4 flex flex-wrap items-center gap-1.5 text-sm">
            <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ monthTarget.children_label }}</span>
            <button
                v-for="chip in chips"
                :key="chip.key"
                type="button"
                class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                :class="selectedChildKey === chip.key && (activePeriod === 'month' || chip.key === 'all' || yearChild)
                    ? 'border-[var(--theme-color-4)] bg-[var(--theme-color-4)] text-[var(--theme-color-5)] shadow-sm'
                    : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                @click="selectChild(chip.key)">
                <span>{{ chip.name }}</span>
                <span v-if="invoicedPercent(chip.block) !== null" class="rounded-full px-1.5 text-xs tabular-nums" :class="selectedChildKey === chip.key && (activePeriod === 'month' || chip.key === 'all' || yearChild) ? 'bg-white/20' : 'bg-white text-gray-500'">{{ invoicedPercent(chip.block) }}%</span>
            </button>
        </div>

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="flex min-w-0 flex-col xl:col-span-3">
                <div class="h-56">
                    <Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
                </div>
                <div v-if="isYear && aheadBehindLine.length" class="mt-4 flex flex-1 flex-col">
                    <div class="mb-1 text-xs text-gray-500">{{ ctrans("Ahead or behind :last_year", { last_year: periodData.last_year_label }) }}</div>
                    <div class="relative min-h-32 flex-1">
                        <Chart type="line" :data="aheadBehindData" :options="aheadBehindOptions" class="!absolute inset-0" />
                    </div>
                </div>
            </div>

            <div class="min-w-0 xl:col-span-2 xl:border-l xl:border-gray-100 xl:pl-6">
                <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <form v-if="isEditing" class="flex items-center gap-2" @submit.prevent="saveTarget">
                            <input v-model.number="newTarget" type="number" min="0" step="1" class="w-36 rounded border-gray-300 text-lg font-bold" :aria-label="ctrans('Target')" autofocus />
                            <button type="submit" :disabled="isSaving" class="rounded bg-[var(--theme-color-4)] px-3 py-1.5 text-sm text-[var(--theme-color-5)] disabled:opacity-50">{{ ctrans("Save") }}</button>
                            <button type="button" class="text-sm text-gray-500" @click="isEditing = false">{{ ctrans("Cancel") }}</button>
                        </form>
                        <p v-else class="text-2xl font-bold tabular-nums">
                            {{ target ? money(target) : ctrans("No target") }}
                            <button v-if="canEditTarget" type="button" class="ml-1 align-middle text-sm text-gray-400 hover:text-gray-700" :aria-label="ctrans('Edit target')" @click="startEditing">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                            </button>
                        </p>
                        <p class="text-xs text-gray-400">
                            <template v-if="periodData.target.is_sum_of_shops">{{ ctrans("Sum of the shops' targets") }}</template>
                            <template v-else-if="periodData.target.is_sum_of_categories">{{ ctrans("Sum of the invoice categories' targets") }}</template>
                            <template v-else-if="periodData.target.is_share && target">{{ ctrans("Share of the shop target, by :last_year sales", { last_year: periodData.last_year_label }) }}</template>
                            <template v-else-if="periodData.target.months_set && periodData.target.months_set < 12">{{ ctrans(":count of 12 months set by management, the rest :last_year sales plus :growth%", { count: String(periodData.target.months_set), last_year: periodData.last_year_label, growth: String(Math.round(periodData.target.growth * 100)) }) }}</template>
                            <template v-else-if="!periodData.target.is_default">{{ ctrans("Target set by :name", { name: periodData.target.set_by ?? ctrans("management") }) }}</template>
                            <template v-else-if="target">{{ ctrans("Target: :last_year sales plus :growth%", { last_year: periodData.last_year_label, growth: String(Math.round(periodData.target.growth * 100)) }) }}</template>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">{{ periodData.granularity === "year" ? ctrans("Expected by year end") : ctrans("Expected by month end") }}</p>
                        <p class="text-2xl font-bold tabular-nums" :class="expectedVersusTarget !== null && expectedVersusTarget < 100 ? 'text-red-600' : 'text-green-600'">{{ money(periodData.expected) }}</p>
                        <p v-if="expectedVersusTarget !== null" class="text-xs text-gray-400">
                            {{ expectedVersusTargetLabel }}
                            <span v-if="target" class="font-medium tabular-nums" :class="periodData.sales_so_far < target ? 'text-gray-600' : 'text-green-600'">
                                · {{ periodData.sales_so_far < target ? ctrans(":amount to go", { amount: money(target - periodData.sales_so_far) }) : ctrans("Target reached") }}
                            </span>
                        </p>
                    </div>
                </div>

                <div v-if="target" class="flex flex-wrap items-center gap-4">
                    <div class="relative h-32 w-32 shrink-0">
                        <Chart type="doughnut" :data="donutData" :options="donutOptions" class="relative z-10 h-full" />
                        <div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold">{{ Math.round(invoicedWidth) }}%</span>
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

                <p class="mt-2 text-xs text-gray-500">
                    <span v-if="versusLastYear !== null" :class="versusLastYear < 0 ? 'text-red-600' : 'text-green-600'">{{ ctrans(":change% vs same days last year", { change: (versusLastYear > 0 ? "+" : "") + versusLastYear.toFixed(1) }) }}</span>
                    <template v-if="periodData.needed_per_day"> · {{ ctrans(":amount needed per day", { amount: money(periodData.needed_per_day) }) }}</template>
                    <template v-if="periodData.needed_this_week && periodData.granularity !== 'year'"> · {{ ctrans(":amount this week", { amount: money(periodData.needed_this_week) }) }}</template>
                    · {{ ctrans(":orders orders in the pipeline", { orders: String(periodData.pipeline.orders) }) }}
                </p>

                <table v-if="isYear" class="mt-4 w-full text-xs tabular-nums">
                    <thead class="text-gray-400">
                        <tr>
                            <th class="py-0.5 text-left font-normal">{{ ctrans("Month") }}</th>
                            <th class="py-0.5 text-right font-normal">{{ periodData.month.slice(0, 4) }}</th>
                            <th class="py-0.5 text-right font-normal">{{ periodData.last_year_label }}</th>
                            <th class="py-0.5 text-right font-normal">{{ ctrans("Change") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in monthRows" :key="row.month" :class="row.kind === 'forecast' ? 'italic text-gray-400' : 'text-gray-700'">
                            <td class="py-0.5">{{ dayLabel(row.month) }}<span v-if="row.kind === 'partial'" class="text-gray-400"> · {{ ctrans("so far") }}</span><span v-if="row.kind === 'forecast'"> · {{ ctrans("forecast") }}</span></td>
                            <td class="py-0.5 text-right">{{ row.sales === null ? "—" : money(row.sales) }}</td>
                            <td class="py-0.5 text-right text-gray-500">{{ money(row.lastYear) }}</td>
                            <td class="py-0.5 text-right" :class="row.change === null ? '' : row.change < 0 ? 'text-red-600' : 'text-green-600'">{{ row.change === null ? "" : (row.change > 0 ? "+" : "") + row.change.toFixed(1) + "%" }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p v-if="periodData.tip && periodData.granularity !== 'year'" class="mt-4 flex items-start gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
            <FontAwesomeIcon icon="fal fa-lightbulb" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
            <span><span class="font-semibold">{{ ctrans("Today's tip") }}:</span> {{ periodData.tip }}</span>
        </p>

        <template v-if="children.length">
            <div class="mt-3 flex justify-end">
                <button type="button" class="text-xs text-gray-400 hover:text-gray-700" :aria-expanded="showAllCategories" @click="showAllCategories = !showAllCategories">
                    {{ showAllCategories ? ctrans("Hide all targets") : ctrans("Show all targets") }}
                    <FontAwesomeIcon icon="fal fa-chevron-down" class="ml-0.5 transition-transform duration-300" :class="showAllCategories ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div class="grid transition-all duration-300 ease-out" :class="showAllCategories ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'">
                <div class="overflow-hidden">
                    <div class="overflow-x-auto border-t border-gray-100 pt-3">
                        <table class="w-full text-sm tabular-nums">
                            <thead class="text-xs text-gray-500">
                                <tr>
                                    <th class="py-1 pr-4 text-left font-normal">{{ monthTarget.children_label }}</th>
                                    <th class="py-1 pr-4 text-right font-normal">{{ ctrans("Invoiced") }}</th>
                                    <th class="py-1 pr-4 text-right font-normal">{{ ctrans("Target") }}</th>
                                    <th class="w-40 py-1 pr-4 text-left font-normal">{{ ctrans("Progress") }}</th>
                                    <th class="py-1 pr-4 text-right font-normal">{{ ctrans("To go") }}</th>
                                    <th class="py-1 text-right font-normal">{{ ctrans("Per day") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="child in children" :key="child.key" class="cursor-pointer border-t border-gray-50 hover:bg-gray-50" :class="selectedChildKey === child.key ? 'font-semibold' : ''" @click="selectChild(child.key)">
                                    <td class="py-1.5 pr-4">{{ child.name }}</td>
                                    <td class="py-1.5 pr-4 text-right">{{ money(child.sales_so_far) }}</td>
                                    <td class="py-1.5 pr-4 text-right" :class="child.target.is_share ? 'text-gray-500' : ''">{{ money(child.target.amount) }}</td>
                                    <td class="py-1.5 pr-4">
                                        <span class="flex items-center gap-2">
                                            <span class="relative h-2 w-24 overflow-hidden rounded-full bg-gray-100">
                                                <span class="absolute inset-y-0 left-0" :style="{ width: percentOf(child.sales_so_far, child.target.amount) + '%', backgroundColor: COLORS.invoiced }" />
                                            </span>
                                            <span class="text-xs text-gray-500">{{ invoicedPercent(child) !== null ? invoicedPercent(child) + "%" : "" }}</span>
                                        </span>
                                    </td>
                                    <td class="py-1.5 pr-4 text-right" :class="child.target.amount && child.sales_so_far >= child.target.amount ? 'text-green-600' : ''">{{ child.target.amount && child.sales_so_far < child.target.amount ? money(child.target.amount - child.sales_so_far) : child.target.amount ? ctrans("Target reached") : "" }}</td>
                                    <td class="py-1.5 text-right text-gray-600">{{ child.target.amount && child.sales_so_far < child.target.amount ? money((child.target.amount - child.sales_so_far) / Math.max(1, child.remaining_days)) : "" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </DashboardWidgetBox>
</template>
