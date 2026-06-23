<script setup lang="ts">
import { ref, computed, watch } from "vue";
import Chart from "primevue/chart";
import { trans } from 'laravel-vue-i18n';
import axios from "axios";

const props = defineProps<{
    mailshotState: string;
    totalOpened: number;
    totalClicked: number;
    mailshotSlug: string;
    performanceInsightsRoute: string;
}>();

const selectedPeriod = ref('day');
const selectedMetric = ref('opened');
const isLoading = ref(false);
const records = ref<Array<{ period: string; value: number }>>([]);

const periods = [
    { key: 'day', label: 'Day' },
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' }
];

const rateMetrics = ['open_rate', 'clicked_rate', 'spam_rate', 'unsubscribe_rate'];

const metrics = [
    { key: 'opened', label: 'Total Emails Opened' },
    { key: 'clicked', label: 'Total Clicks' },
    { key: 'open_rate', label: 'Open Rate' },
    { key: 'clicked_rate', label: 'Average Click Rate' },
    { key: 'spam_rate', label: 'Spam Rate' },
    { key: 'unsubscribe_rate', label: 'Unsubscribe Rate' },
    { key: 'bounce_rate', label: 'Bounce Rate' }
];

// Maps this component's metric keys to App\Enums\Comms\Mailshot\MailshotPerformanceInsightMetricEnum values
const apiMetricMap: Record<string, string> = {
    opened: 'total_email_opened',
    clicked: 'total_click',
    open_rate: 'open_rate',
    clicked_rate: 'click_rate',
    spam_rate: 'spam_rate',
    unsubscribe_rate: 'unsubscribe_rate',
    bounce_rate: 'bounce_rate',
};

const isRateMetric = (metric: string) => rateMetrics.includes(metric);

const fetchInsights = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get(
            route(props.performanceInsightsRoute, { mailshot: props.mailshotSlug }),
            {
                params: { frequency: selectedPeriod.value, metric: apiMetricMap[selectedMetric.value] },
                headers: { Accept: 'application/json' },
            }
        );
        records.value = data?.metric ?? [];
    } finally {
        isLoading.value = false;
    }
};

watch([selectedPeriod, selectedMetric], fetchInsights, { immediate: true });

const totalValue = computed(() => {
    if (records.value.length === 0) {
        return 0;
    }

    if (isRateMetric(selectedMetric.value)) {
        const sum = records.value.reduce((acc, record) => acc + record.value, 0);
        return sum / records.value.length;
    }

    return records.value.reduce((sum, record) => sum + record.value, 0);
});

const chartData = computed(() => ({
    labels: records.value.map(record => record.period),
    datasets: [
        {
            data: records.value.map(record => record.value),
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
}));

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
            borderWidth: 1,
            callbacks: {
                label: function(context: any) {
                    const value = context.parsed.y;
                    return isRateMetric(selectedMetric.value) ? `${value}%` : `${value}`
                }
            }
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
</script>

<template>
    <div class="card p-4 mt-6">
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

            <div class="flex items-center space-x-4">
                <label for="average-select" class="text-sm font-medium text-gray-700">
                    {{ isRateMetric(selectedMetric) ? 'Average Rate' : 'Total Opened' }}
                </label>
                <span class="text-sm font-bold text-gray-900">
                    {{ isRateMetric(selectedMetric) ? `${totalValue.toFixed(2)}%` : props.totalOpened }}
                </span>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <div class="h-64 relative flex justify-center items-center">
                <span v-if="isLoading" class="text-gray-500 text-sm">{{ trans('Loading...') }}</span>
                <span v-else-if="records.length === 0" class="text-gray-500 text-sm">{{ trans('No Data Available') }}</span>
                <Chart v-else type="line" :data="chartData" :options="chartOptions" class="w-full h-full" />
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
