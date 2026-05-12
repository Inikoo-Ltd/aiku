<script setup lang="ts">
import { ref, computed } from "vue";
import Chart from "primevue/chart";
import { trans } from 'laravel-vue-i18n';

const props = defineProps<{
    mailshotState: string;
    timeSeriesData?: Array<{
        id: number;
        period: string;
        filter_date: string;
        error: number;
        sent: number;
        delivered: number;
        hard_bounce: number;
        soft_bounce: number;
        opened: number;
        clicked: number;
        spam: number;
        unsubscribed: number;
        delay: number;
    }>;
}>();

const selectedPeriod = ref('day');
const selectedMetric = ref('sent');

const periods = [
    { key: 'day', label: 'Day' },
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' }
];

const metrics = [
    { key: 'error', label: 'Error' },
    { key: 'sent', label: 'Sent' },
    { key: 'delay', label: 'Delay' },
    { key: 'delivered', label: 'Delivered' },
    { key: 'hard_bounce', label: 'Hard Bounce' },
    { key: 'soft_bounce', label: 'Soft Bounce' },
    { key: 'opened', label: 'Opened' },
    { key: 'clicked', label: 'Clicked' },
    { key: 'spam', label: 'Spam' },
    { key: 'unsubscribed', label: 'Unsubscribed' }
];

const processTimeSeriesData = (period: string, metric: string) => {
    if (!props.timeSeriesData || !Array.isArray(props.timeSeriesData) || props.timeSeriesData.length === 0) {
        // Fallback to dummy data if no real data available
        return generateDummyData(period, metric);
    }

    const labels = props.timeSeriesData.map(item => item.period);
    const data = props.timeSeriesData.map(item => {
        switch (metric) {
            case 'error': return item.error;
            case 'sent': return item.sent;
            case 'delivered': return item.delivered;
            case 'hard_bounce': return item.hard_bounce;
            case 'soft_bounce': return item.soft_bounce;
            case 'opened': return item.opened;
            case 'clicked': return item.clicked;
            case 'spam': return item.spam;
            case 'unsubscribed': return item.unsubscribed;
            case 'delay': return item.delay;
            default: return 0;
        }
    });

    return { labels, data };
};

const generateDummyData = (period: string, metric: string) => {
    const dataPoints = period === 'day' ? 24 : period === 'week' ? 7 : 30;
    const labels = period === 'day'
        ? Array.from({ length: dataPoints }, (_, i) => `${i}:00`)
        : period === 'week'
            ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
            : Array.from({ length: dataPoints }, (_, i) => `Day ${i + 1}`);

    const baseValues = {
        error: [2, 3, 1, 4, 2, 5, 3, 2, 4, 3, 2, 1, 3, 2, 4, 5, 3, 2, 4, 3, 2, 1, 3, 2],
        sent: [120, 150, 180, 200, 170, 190, 210, 230, 180, 160, 140, 130, 150, 170, 190, 210, 200, 180, 160, 140, 130, 120, 110, 100],
        delay: [5, 8, 3, 12, 7, 9, 4, 6, 8, 5, 7, 3, 6, 8, 4, 7, 9, 5, 6, 4, 3, 2, 4, 3],
        delivered: [115, 142, 177, 192, 163, 181, 202, 222, 172, 152, 133, 127, 144, 162, 182, 203, 194, 171, 152, 134, 127, 116, 106, 97],
        hard_bounce: [1, 2, 1, 3, 2, 1, 2, 1, 2, 1, 1, 0, 1, 1, 2, 1, 2, 1, 2, 1, 1, 0, 1, 1],
        soft_bounce: [2, 3, 2, 4, 3, 2, 3, 2, 3, 2, 2, 1, 2, 2, 3, 2, 3, 2, 3, 2, 2, 1, 2, 1],
        opened: [80, 95, 110, 125, 105, 120, 135, 145, 115, 100, 90, 85, 95, 105, 120, 135, 125, 115, 100, 90, 85, 75, 70, 65],
        clicked: [30, 35, 42, 48, 40, 45, 52, 56, 45, 40, 35, 32, 38, 42, 48, 54, 50, 45, 40, 35, 32, 28, 26, 24],
        spam: [1, 1, 2, 1, 1, 2, 1, 1, 2, 1, 1, 0, 1, 1, 2, 1, 1, 2, 1, 1, 1, 0, 1, 1],
        unsubscribed: [2, 3, 2, 4, 3, 2, 3, 2, 3, 2, 2, 1, 2, 2, 3, 2, 3, 2, 3, 2, 2, 1, 2, 1]
    };

    const values = baseValues[metric as keyof typeof baseValues] || baseValues.sent;
    const data = values.slice(0, dataPoints);

    return { labels, data };
};

const chartData = computed(() => {
    const { labels, data } = processTimeSeriesData(selectedPeriod.value, selectedMetric.value);

    return {
        labels,
        datasets: [
            {
                data,
                borderColor: '#36a2eb',
                borderWidth: 2,
                fill: false,
                tension: 0.1,
                pointBackgroundColor: '#36a2eb',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ]
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: '#36a2eb',
            borderWidth: 1
        }
    },
    scales: {
        x: {
            display: true,
            grid: {
                display: false
            },
            ticks: {
                color: '#666',
                font: {
                    size: 11
                }
            }
        },
        y: {
            display: true,
            grid: {
                color: 'rgba(0, 0, 0, 0.05)'
            },
            ticks: {
                color: '#666',
                font: {
                    size: 11
                },
                beginAtZero: true
            }
        }
    },
    elements: {
        point: {
            hoverRadius: 6
        }
    }
}));

const shouldShow = computed(() => {
    // return props.mailshotState === 'ready' || props.mailshotState === 'in_process';
    return true;
});
</script>

<template>
    <div v-if="shouldShow" class="card p-4 mt-6">
        <div class="mb-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Metrics</h3>

            <!-- Time Period Tabs -->
            <div class="flex space-x-1 mb-4 bg-gray-100 p-1 rounded-lg w-fit">
                <button v-for="period in periods" :key="period.key" @click="selectedPeriod = period.key" :class="[
                    'px-4 py-2 rounded-md text-sm font-medium transition-all duration-200',
                    selectedPeriod === period.key
                        ? 'bg-white text-blue-600 shadow-sm'
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                ]">
                    {{ period.label }}
                </button>
            </div>

            <!-- Metric Selection -->
            <div class="flex items-center space-x-3">
                <label for="metric-select" class="text-sm font-medium text-gray-700">
                    Metric:
                </label>
                <select id="metric-select" v-model="selectedMetric"
                    class="block w-48 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option v-for="metric in metrics" :key="metric.key" :value="metric.key">
                        {{ metric.label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <div class="h-64">
                <Chart type="line" :data="chartData" :options="chartOptions" class="w-full h-full" />
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.card {
    border-radius: 8px;

    @media (max-width: 768px) {
        padding: 0.5rem;
    }
}

select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
    appearance: none;
}
</style>
