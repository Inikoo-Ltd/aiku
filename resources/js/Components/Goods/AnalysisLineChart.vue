<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Fri, 25 Sep 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend } from "chart.js"
import { Line } from "vue-chartjs"

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

const props = defineProps<{
    labels: string[]
    datasets: { label: string; data: (number | null)[]; color: string }[]
    height?: number
}>()

const chartData = computed(() => ({
    labels: props.labels,
    datasets: props.datasets.map((dataset) => ({
        label: dataset.label,
        data: dataset.data,
        borderColor: dataset.color,
        backgroundColor: dataset.color,
        fill: false,
        tension: 0.3,
        pointRadius: 0,
        pointHoverRadius: 3,
        borderWidth: 2,
    })),
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index" as const, intersect: false },
    plugins: {
        legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkipPadding: 12 } },
        y: { ticks: { font: { size: 10 } } },
    },
}
</script>

<template>
    <div :style="{ height: (height ?? 220) + 'px' }">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
