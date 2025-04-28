<script setup lang="ts">
// import { getComponentWidget } from "@/Composables/Listing/DashboardWidgetsList"
import { Pie } from "vue-chartjs"
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js";
import { trans } from "laravel-vue-i18n"
import { useStringToHex } from '@/Composables/useStringToHex'

import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCircle } from "@fas"
import { Link } from "@inertiajs/vue3"
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

    if (sortedShops.length <= 4) {
        return sortedShops  // if Shops length just 4 or less, return it anyway
    }

    // Split the array
    const firstFour = sortedShops.slice(0, 4);

    const summedValue = sortedShops.slice(4).reduce((sum, item) => {
        const xx = sum + (Number(item.columns.sales_org_currency[props.intervals.value]?.raw_value) || 0); 
        return xx
    }, 0);

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

const isLoadingVisit = ref<number | null>(null)
</script>

<template>
    <div class="flex justify-between gap-x-4 px-4 py-5 sm:p-6 rounded-lg bg-gray-50 border border-gray-200 tabular-nums">
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
                </div>

                <!-- Case Breakdown -->
                <div
                    class="text-sm text-gray-500 flex gap-x-5 gap-y-1 items-center flex-wrap">
                    <template v-for="(row, idxCase) in dataSetsSplit" :key="row.value">
                        <component
                            :is="row.route?.name ? Link : 'div'"
                            :href="row.route?.name ? route(row.route.name, row.route.parameters) : null"
                            :class="row.route?.name ? 'hover:bg-gray-200 px-1 py-0.5 rounded' : ''"
                            class="flex gap-x-0.5 items-center font-normal"
                            v-tooltip="row.columns.label.formatted_value"
                            @start="() => isLoadingVisit = idxCase"
                            @finish="() => isLoadingVisit = null"
                        >
                            <LoadingIcon v-if="isLoadingVisit === idxCase" class="text-gray-500" />
                            <FontAwesomeIcon
                                xxv-else
                                :icon="faCircle"
                                :class="row?.icon?.class"
                                fixed-width
                                :title="row?.icon?.tooltip"
                                :style="{
                                    color: useStringToHex(row.columns.label.formatted_value),
                                }"
                                aria-hidden="true" />
                            <span class="font-semibold">{{ row.columns.sales_org_currency[intervals.value]?.formatted_value }}</span>
                        </component>
                    </template>
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
                            backgroundColor: [
                                ...dataSetsSplit.map((dCase, idx) => useStringToHex(dCase.columns.label.formatted_value)),
                            ],
                            hoverOffset: 4,
                        },
                    ],
                }"
                :options="options" />
        </div>
    </div>
</template>