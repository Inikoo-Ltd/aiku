<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Chart as ChartJS, ArcElement, BarElement, CategoryScale, LinearScale, LineElement, PointElement, Filler, Tooltip, Legend } from "chart.js"
import type { Plugin } from "chart.js"
import { Bar, Doughnut, Line } from "vue-chartjs"
import { useLocaleStore } from "@/Stores/locale"

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, LineElement, PointElement, Filler, Tooltip, Legend)

const props = withDefaults(defineProps<{
    type?: "bar" | "line"
    labels: string[]
    datasets: { label: string; data: (number | null)[]; color: string }[]
    stacked?: boolean
    unit?: "count" | "percent" | "ms"
    eventIndexes?: number[]
    height?: string
    clickable?: boolean
    points?: boolean
}>(), {
    type: "bar",
    stacked: false,
    unit: "count",
    eventIndexes: () => [],
    height: "h-56",
    clickable: false,
    points: false,
})

const emit = defineEmits<{ select: [index: number, datasetIndex: number | null] }>()

const locale = useLocaleStore()

const formatValue = (value: number | null): string => {
    if (value === null || value === undefined) {
        return "—"
    }
    if (props.unit === "percent") {
        return `${locale.number(Math.round(value * 10) / 10)}%`
    }
    if (props.unit === "ms") {
        return `${locale.number(Math.round(value))} ms`
    }
    return locale.number(Math.round(value))
}

const formatTick = (value: number): string => {
    if (props.unit === "percent") {
        return `${value}%`
    }
    return locale.numberShort(value)
}

const eventLines: Plugin = {
    id: "edgeTrafficEvents",
    afterDatasetsDraw: (chart) => {
        const xScale = chart.scales.x
        const yScale = chart.scales.y
        if (!xScale || !yScale) {
            return
        }
        const ctx = chart.ctx
        ctx.save()
        ctx.strokeStyle = "#6b7280"
        ctx.lineWidth = 1
        ctx.setLineDash([3, 3])
        props.eventIndexes.forEach((index) => {
            if (index < 0 || index >= props.labels.length) {
                return
            }
            const x = xScale.getPixelForValue(index) - (props.type === "bar" ? (xScale.getPixelForValue(1) - xScale.getPixelForValue(0)) / 2 : 0)
            ctx.beginPath()
            ctx.moveTo(x, yScale.top)
            ctx.lineTo(x, yScale.bottom)
            ctx.stroke()
        })
        ctx.restore()
    },
}

const chartData = computed(() => ({
    labels: props.labels,
    datasets: props.datasets.map((dataset) => ({
        label: dataset.label,
        data: dataset.data,
        backgroundColor: props.type === "line" ? `${dataset.color}1f` : dataset.color,
        borderColor: dataset.color,
        borderWidth: props.type === "line" ? 2 : 0,
        borderRadius: props.type === "bar" && !props.stacked ? 3 : 0,
        borderSkipped: false,
        pointRadius: props.points ? 3 : 0,
        pointBackgroundColor: props.points ? dataset.color : undefined,
        pointHoverRadius: 4,
        pointHitRadius: 12,
        tension: props.points ? 0 : 0.3,
        spanGaps: false,
        fill: props.type === "line" && props.datasets.length === 1 && !props.points,
        maxBarThickness: 18,
    })),
}))

const isPie = computed(() => props.type === "bar" && props.stacked && props.labels.length === 1)
const isSinglePointLine = computed(() => props.type === "line" && props.labels.length === 1)

const pieData = computed(() => ({
    labels: props.datasets.map((dataset) => dataset.label),
    datasets: [{
        data: props.datasets.map((dataset) => dataset.data[0] ?? 0),
        backgroundColor: props.datasets.map((dataset) => dataset.color),
        borderColor: "#ffffff",
        borderWidth: 1,
    }],
}))

const pieOptions = computed(() => {
    const total = props.datasets.reduce((carry, dataset) => carry + (dataset.data[0] ?? 0), 0)
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        cutout: "55%",
        onHover: chartOptions.value.onHover,
        onClick: (_event: unknown, elements: { index: number }[]) => {
            if (props.clickable && elements.length) {
                emit("select", 0, elements[0].index)
            }
        },
        plugins: {
            legend: { ...chartOptions.value.plugins.legend, display: true, position: "right" as const },
            tooltip: {
                ...chartOptions.value.plugins.tooltip,
                callbacks: {
                    label: (item: { label: string; raw: unknown }) => ` ${item.label}: ${formatValue(item.raw as number)}${total ? ` (${locale.number(Math.round(((item.raw as number) / total) * 1000) / 10)}%)` : ""}`,
                },
            },
        },
    }
})

let lastLegendClick = { at: 0, index: -1 }

/**
 * A click toggles a legend item as Chart.js does; a second click on the same item within 400 ms
 * shows that item alone. Doughnut items are slices (index), the others datasets (datasetIndex).
 */
const onLegendClick = (_event: unknown, item: { datasetIndex?: number; index?: number }, legend: { chart: ChartJS }) => {
    const chart = legend.chart
    const isSlice = item.datasetIndex === undefined
    const index = (isSlice ? item.index : item.datasetIndex) ?? 0
    const now = Date.now()
    const isDouble = index === lastLegendClick.index && now - lastLegendClick.at < 400
    lastLegendClick = isDouble ? { at: 0, index: -1 } : { at: now, index }

    const isVisible = (i: number) => (isSlice ? chart.getDataVisibility(i) : chart.isDatasetVisible(i))
    const setVisible = (i: number, visible: boolean) => {
        if (isVisible(i) === visible) {
            return
        }
        if (isSlice) {
            chart.toggleDataVisibility(i)
        } else {
            chart.setDatasetVisibility(i, visible)
        }
    }

    if (isDouble) {
        const count = isSlice ? (chart.data.labels?.length ?? 0) : chart.data.datasets.length
        for (let i = 0; i < count; i++) {
            setVisible(i, i === index)
        }
    } else {
        setVisible(index, !isVisible(index))
    }
    chart.update()
}

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    interaction: { mode: "index" as const, intersect: false },
    onHover: (event: { native: Event | null }, elements: unknown[]) => {
        const target = event.native?.target as HTMLElement | undefined
        if (props.clickable && target) {
            target.style.cursor = elements.length ? "pointer" : "default"
        }
    },
    onClick: (event: Parameters<ChartJS["getElementsAtEventForMode"]>[0], elements: { index: number }[], chart: ChartJS) => {
        if (!props.clickable || !elements.length) {
            return
        }
        const exact = chart.getElementsAtEventForMode(event, "nearest", { intersect: true }, false)[0]
        emit("select", elements[0].index, exact ? exact.datasetIndex : null)
    },
    datasets: { bar: { categoryPercentage: 0.85, barPercentage: 0.9 } },
    plugins: {
        legend: {
            display: props.datasets.length > 1,
            position: "bottom" as const,
            onClick: onLegendClick,
            labels: { usePointStyle: true, pointStyle: "circle", boxWidth: 6, boxHeight: 6, padding: 12, color: "#6b7280", font: { size: 11 } },
        },
        tooltip: {
            backgroundColor: "#111827",
            titleColor: "#f9fafb",
            bodyColor: "#e5e7eb",
            padding: 8,
            boxWidth: 8,
            boxHeight: 8,
            boxPadding: 3,
            usePointStyle: true,
            callbacks: {
                label: (item: { dataset: { label?: string }; raw: unknown }) => ` ${item.dataset.label}: ${formatValue(item.raw as number | null)}`,
            },
            filter: (item: { raw: unknown }) => item.raw !== null && item.raw !== undefined,
        },
    },
    scales: {
        x: {
            stacked: props.stacked,
            grid: { display: false },
            border: { color: "#e5e7eb" },
            ticks: { color: "#9ca3af", font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
        },
        y: {
            stacked: props.stacked,
            beginAtZero: true,
            max: props.unit === "percent" ? 100 : undefined,
            grid: { color: "#f3f4f6" },
            border: { display: false },
            ticks: { color: "#9ca3af", font: { size: 11 }, maxTicksLimit: 5, callback: (value: number | string) => formatTick(Number(value)) },
        },
    },
}))
</script>

<template>
    <div class="relative w-full" :class="height">
        <Doughnut v-if="isPie" :data="pieData" :options="pieOptions" />
        <Line v-else-if="type === 'line' && !isSinglePointLine" :data="chartData" :options="chartOptions" :plugins="[eventLines]" />
        <Bar v-else :data="chartData" :options="chartOptions" :plugins="[eventLines]" />
    </div>
</template>
