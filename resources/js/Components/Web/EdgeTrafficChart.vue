<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, LineElement, PointElement, Filler, Tooltip, Legend } from "chart.js"
import type { Plugin } from "chart.js"
import { Bar, Line } from "vue-chartjs"
import { useLocaleStore } from "@/Stores/locale"

ChartJS.register(BarElement, CategoryScale, LinearScale, LineElement, PointElement, Filler, Tooltip, Legend)

const props = withDefaults(defineProps<{
    type?: "bar" | "line"
    labels: string[]
    datasets: { label: string; data: (number | null)[]; color: string }[]
    stacked?: boolean
    unit?: "count" | "percent" | "ms"
    eventIndexes?: number[]
    height?: string
    clickable?: boolean
}>(), {
    type: "bar",
    stacked: false,
    unit: "count",
    eventIndexes: () => [],
    height: "h-56",
    clickable: false,
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
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHitRadius: 12,
        tension: 0.3,
        spanGaps: false,
        fill: props.type === "line" && props.datasets.length === 1,
        maxBarThickness: 18,
    })),
}))

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
        <Line v-if="type === 'line'" :data="chartData" :options="chartOptions" :plugins="[eventLines]" />
        <Bar v-else :data="chartData" :options="chartOptions" :plugins="[eventLines]" />
    </div>
</template>
