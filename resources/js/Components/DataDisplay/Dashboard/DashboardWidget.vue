<script setup lang="ts">
import { getComponentWidget } from "@/Composables/Listing/DashboardWidgetsList"
import { Pie } from "vue-chartjs"
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js";
import { trans } from "laravel-vue-i18n"
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faInfoCircle)

ChartJS.register(ArcElement, Tooltip, Legend, Colors);

const props = defineProps<{
	intervals: {
		options: {
			label: string
			value: string
			labelShort: string
		}[]
		value: string
	}
    tableData: {

    }
}>()

// Chart options
const options = {
    responsive: true,
    plugins: {
        legend: { display: false },
        tooltip: {
            titleFont: { size: 10, weight: "lighter" },
            bodyFont: { size: 11, weight: "bold" },
        },
    },
}

const dataSetsSplit = computed(() => {
    const shopsBody = props.tableData.tables.shops.body.filter(row => row.state === 'active')

    const sortedShops = [...shopsBody].sort((a, b) => {
        const aValue = Number(a.columns.sales_org_currency[props.intervals.value]?.raw_value) || 0;
        const bValue = Number(b.columns.sales_org_currency[props.intervals.value]?.raw_value) || 0;
        return bValue - aValue; // Descending (highest first)
        // return aValue - bValue; // Ascending (lowest first)
    });

    // Split the array
    const firstFour = sortedShops.slice(0, 4);

    const summedValue = sortedShops.slice(4).reduce((sum, item) => {
        const xx = sum + (Number(item.columns.sales_org_currency[props.intervals.value]?.raw_value) || 0); 
        // console.log('xx', item.columns.label.formatted_value, xx)
        return xx
    }, 0);

    // console.log('summedValue', 'background: green; color: white', sortedShops.slice(4));
    // Create the summed object
    const summedEntry = {
        columns: {
            sales_org_currency: {
                [props.intervals.value]: {
                    raw_value: summedValue,
                    formatted_value: trans('Others')
                }
            },
            label: {
                formatted_value: trans('Others'),
                align: "left",
            },
        }
    }

    return [...firstFour, summedEntry];
})
</script>

<template>
    <div class="grid grid-cols-2 gap-3 px-4" :xxstyle="{
        'grid-template-columns': `repeat(${widgetsData?.column_count || 1}, minmax(0, 1fr))`,
    }">
        <div class="flex justify-between px-4 py-5 sm:p-6 rounded-lg bg-gray-50 border border-gray-200 tabular-nums">
            <dd class="flex flex-col gap-x-2">
                <div class="text-base mb-1 text-gray-400 capitalize">
                    {{ trans('Shops sales') }}
                    <FontAwesomeIcon v-tooltip="trans('The graph of column sales. Only active shop is shown.')" :icon="faInfoCircle" class="hover:text-gray-600" fixed-width aria-hidden="true" />
                </div>
                <div
                    class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                    <!-- Total Count -->
                    <div class="flex gap-x-2 items-end">
                        {{ props.tableData?.tables?.shops?.totals?.columns?.sales_org_currency[intervals.value].formatted_value }}
                        <!-- <span class="text-sm font-medium leading-4 text-gray-500">
                            {{ trans("in total") }}
                        </span> -->
                    </div>

                    <!-- Case Breakdown -->
                    <div
                        class="text-sm text-gray-500 flex gap-x-5 gap-y-1 items-center flex-wrap">
                        <!-- <template v-for="(dCase, idxCase) in customerStats.cases" :key="dCase.value">
                            <component
                                :is="dCase.route?.name ? Link : 'div'"
                                :href="dCase.route?.name ? route(dCase.route.name, dCase.route.parameters) : null"
                                :class="dCase.route?.name ? 'hover:bg-gray-200 px-1 py-0.5 rounded' : ''"
                                class="flex gap-x-0.5 items-center font-normal"
                                v-tooltip="capitalize(dCase.icon.tooltip)"
                                @start="() => isLoadingVisit = idxCase"
                                @finish="() => isLoadingVisit = null"
                            >
                                <LoadingIcon v-if="isLoadingVisit === idxCase" class="text-gray-500" />
                                <FontAwesomeIcon
                                    v-else
                                    :icon="dCase.icon.icon"
                                    :class="dCase.icon.class"
                                    fixed-width
                                    :title="dCase.icon.tooltip"
                                    aria-hidden="true" />
                                <span class="font-semibold">{{ locale.number(dCase.count) }}</span>
                            </component>
                        </template> -->
                    </div>
                </div>

            </dd>
            <!-- Pie Chart -->
            <div class="w-28">
                <Pie
                    :data="{
                        labels: dataSetsSplit.map(bod => bod.columns.label.formatted_value),
                        datasets: [
                            {
                                data: dataSetsSplit.map(bod => bod.columns.sales_org_currency[intervals.value].raw_value),
                                hoverOffset: 4,
                            },
                        ],
                    }"
                    :options="options" />
            </div>
            <!-- <pre>{{ tableData.tables.shops.body }}</pre> -->
        </div>

        <!-- <pre>{{ tableData.tables.shops.body[0].columns.sales_org_currency.[all].raw_value }}</pre> -->
        <!-- <pre>{{ tableData.tables.shops.body[0].columns.label.formatted_value }}</pre> -->
        <!-- label: {{ tableData.tables.shops.body.map(bod => bod.columns.sales_org_currency[intervals.value].raw_value) }} -->
        <!-- <pre>{{ dataSetsSplit.map(bod => bod.columns.label) }}</pre> -->
    </div>
</template>

<style scoped>
/* .grid-container {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(var(--column-count, 2), minmax(0, 1fr));
    grid-auto-rows: minmax(0, auto);
}

.widget-item {
    height: 500px;
}

@media (max-width: 768px) {
    .grid-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .widget-item {
        width: 100%;
    }
} */
</style>