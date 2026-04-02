<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 14 Sept 2022 15:29:08 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import FlatTreeMap from "@/Components/Navigation/FlatTreeMap.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Pie } from "vue-chartjs"
import { trans } from "laravel-vue-i18n"
import { faSeedling, faThumbsDown, faPalletAlt, faHistory, faBoxOpen, faMapMarkerAlt, faSkullCow, faDollarSign, faBan } from "@fal"
import { faCheckCircle, faTimesCircle, faPauseCircle, faExclamationCircle } from "@fas"

import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"

import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import { PageHeadingTypes } from "@/types/PageHeading"
import AccuracyDashboardWidget from "@/Components/DataDisplay/AccuracyDashboardWidget.vue"
import StatProgressCard from "@/Components/DataDisplay/StatProgressCard.vue"
import StatsBox from "@/Components/Stats/StatsBox.vue"

library.add(
    faSeedling,
    faThumbsDown,
    faTimesCircle,
    faPauseCircle,
    faExclamationCircle,
    faCheckCircle,
    faPalletAlt,
    faHistory,
    faBoxOpen,
    faMapMarkerAlt,
    faSkullCow,
    faDollarSign,
    faBan,
)

ChartJS.register(ArcElement, Tooltip, Legend, Colors)
const locale = useLocaleStore()

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    flatTreeMaps: {}
    flatTreeMapsTwo?: {}
    dashboard: {
        columns: Array<{
            widgets: Array<{
                label: string
                type: string
                data: {
                    stockValue: number
                    utilization: number
                }
            }>
        }>
    }
    statsBox: {}
    stockHistoryToday?: {
        date: string
        number_org_stocks: number
        number_out_of_stock_org_stocks: number
        percentage_out_of_stock: number
        number_locations: number
        org_stock_value: number
        currency_code: string
        value_dormant_stock_1y: number
        percentage_dormant_1y: number
        number_org_stocks_not_sold_1y: number
        percentage_not_sold_1y: number
        route: {
            name: string
            parameters: Record<string, string>
        }
        locations_route: {
            name: string
            parameters: Record<string, string>
        }
    } | null
    dashboardStats: {
        [key: string]: {
            label: string
            count: number
            cases: {
                value: string
                count: number
                label: string
                icon: {
                    icon: string | string[]
                    tooltip: string
                    class: string
                }
            }[]
        }
    }
}>()


// Pie: options
const options = {
    responsive: true,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            titleFont: {
                size: 10,
                weight: "lighter"
            },
            bodyFont: {
                size: 11,
                weight: "bold"
            }
        }
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <div v-if="stockHistoryToday" class="px-4 mt-4">
        <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-y divide-gray-100 bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden">
            <div
                class="px-5 py-4 cursor-pointer hover:bg-indigo-50 transition-colors"
                @click="router.visit(route(stockHistoryToday.route.name, stockHistoryToday.route.parameters))"
            >
                <dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
                    <FontAwesomeIcon icon="fal fa-box" fixed-width aria-hidden="true" />
                    {{ trans('Stored SKUs') }}
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">
                    {{ locale.number(stockHistoryToday.number_org_stocks) }}
                </dd>
            </div>
            <div
                class="px-5 py-4 cursor-pointer hover:bg-indigo-50 transition-colors"
                @click="router.visit(route(stockHistoryToday.locations_route.name, stockHistoryToday.locations_route.parameters))"
            >
                <dt class="flex items-center gap-x-1.5 text-xs font-medium text-indigo-500">
                    <FontAwesomeIcon icon="fal fa-inventory" fixed-width aria-hidden="true" />
                    {{ trans('Locations') }}
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-indigo-600">
                    {{ locale.number(stockHistoryToday.number_locations) }}
                </dd>
            </div>
            <div
                class="px-5 py-4 cursor-pointer hover:bg-indigo-50 transition-colors"
                @click="router.visit(route(stockHistoryToday.route.name, stockHistoryToday.route.parameters))"
            >
                <dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
                    <FontAwesomeIcon icon="fas fa-times-circle" class="text-red-400" fixed-width aria-hidden="true" />
                    {{ trans('Out of Stock') }}
                </dt>
                <dd class="mt-1 flex items-baseline gap-x-2">
                    <span class="text-2xl font-semibold tabular-nums text-red-500">
                        {{ locale.number(stockHistoryToday.number_out_of_stock_org_stocks) }}
                    </span>
                    <span
                        class="text-sm font-medium tabular-nums text-gray-400"
                        v-tooltip="trans('Percentage of total SKUs')"
                    >
                        {{ stockHistoryToday.percentage_out_of_stock }}%
                    </span>
                </dd>
            </div>
            <div
                class="px-5 py-4 cursor-pointer hover:bg-indigo-50 transition-colors"
                @click="router.visit(route(stockHistoryToday.route.name, stockHistoryToday.route.parameters))"
            >
                <dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
                    <FontAwesomeIcon icon="fal fa-dollar-sign" fixed-width aria-hidden="true" />
                    {{ trans('Stock Value') }}
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">
                    {{ locale.currencyFormat(stockHistoryToday.currency_code, Number(stockHistoryToday.org_stock_value)) }}
                </dd>
            </div>
            <div
                class="px-5 py-4 cursor-pointer hover:bg-indigo-50 transition-colors"
                @click="router.visit(route(stockHistoryToday.route.name, stockHistoryToday.route.parameters))"
            >
                <dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
                    <FontAwesomeIcon icon="fal fa-skull-cow" fixed-width aria-hidden="true" />
                    {{ trans('Dormant 1Y') }}
                </dt>
                <dd class="mt-1 flex items-baseline gap-x-2">
                    <span class="text-2xl font-semibold tabular-nums text-gray-800">
                        {{ locale.currencyFormat(stockHistoryToday.currency_code, Number(stockHistoryToday.value_dormant_stock_1y)) }}
                    </span>
                    <span
                        class="text-sm font-medium tabular-nums text-gray-400"
                        v-tooltip="trans('Percentage of total stock value')"
                    >
                        {{ stockHistoryToday.percentage_dormant_1y }}%
                    </span>
                </dd>
            </div>
            <div
                class="px-5 py-4 cursor-pointer hover:bg-indigo-50 transition-colors"
                @click="router.visit(route(stockHistoryToday.route.name, stockHistoryToday.route.parameters))"
            >
                <dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
                    <FontAwesomeIcon icon="fal fa-ban" class="text-orange-400" fixed-width aria-hidden="true" />
                    {{ trans('No Sold 1Y') }}
                </dt>
                <dd class="mt-1 flex items-baseline gap-x-2">
                    <span class="text-2xl font-semibold tabular-nums text-gray-800">
                        {{ locale.number(stockHistoryToday.number_org_stocks_not_sold_1y) }}
                    </span>
                    <span
                        class="text-sm font-medium tabular-nums text-gray-400"
                        v-tooltip="trans('Percentage of total SKUs')"
                    >
                        {{ stockHistoryToday.percentage_not_sold_1y }}%
                    </span>
                </dd>
            </div>
        </dl>
    </div>

    <FlatTreeMap class="mx-4" v-for="(treeMap, idx) in flatTreeMaps" :key="idx" :nodes="treeMap" />

    <div class="py-6 px-4">
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
            <StatsBox v-for="(stat, index) in statsBox"
                :key="index"
                :stat="stat"
            />
        </dl>
    </div>

    <dl class="px-4 mt-5 grid grid-cols-1 md:grid-cols-2 gap-x-2 gap-y-3">
        <!-- <div class="col-span-12">
            <div v-for="(column, colIdx) in dashboard.columns" :key="colIdx" class="flex flex-col md:flex-row gap-6">
                <StatProgressCard
                    v-for="(widget, key) in column.widgets"
                    :if="widget.type === 'stat_progress_card'"
                    :key="key"
                    :title="widget.label"
                    :utilization="widget.data.utilization"
                    :stockValue="widget.data.stockValue"
                />
            </div>
            <div class="flex flex-col md:flex-row gap-6 mt-5">
            <AccuracyDashboardWidget/>
            </div>
        </div> -->
        <div
            v-for="stats in dashboardStats"
            class="px-4 py-5 sm:p-6 rounded-lg bg-white shadow tabular-nums ring-3 ring-gray-300">
            <dt class="text-base font-medium text-gray-400">{{ stats.label }}</dt>
            <dd class="mt-2 flex justify-between gap-x-2">
                <div
                    class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold">
                    <!-- In Total -->
                    <div class="flex gap-x-2 items-end">
                        {{ locale.number(stats.count) }}
                        <span class="text-sm font-medium leading-4 text-gray-500">{{
                            trans("current")
                        }}</span>
                    </div>

                    <!-- Statistic -->
                    <div class="text-sm text-gray-500 flex gap-x-5 gap-y-1 items-center flex-wrap">
                        <div
                            v-for="sCase in stats.cases"
                            class="flex gap-x-0.5 items-center font-normal"
                            v-tooltip="capitalize(sCase.icon.tooltip)">
                            <FontAwesomeIcon
                                :icon="sCase.icon.icon"
                                :class="sCase.icon.class"
                                fixed-width
                                :title="sCase.icon.tooltip"
                                aria-hidden="true" />
                            <span class="font-semibold">
                                {{ locale.number(sCase.count) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Donut -->
                <div class="w-20">
                    <Pie
                        :data="{
                            labels: Object.entries(stats.cases).map(([, value]) => value.label),
                            datasets: [
                                {
                                    data: Object.entries(stats.cases).map(
                                        ([, value]) => value.count
                                    ),
                                    hoverOffset: 4,
                                },
                            ],
                        }"
                        :options="options" />
                </div>
            </dd>
        </div>
    </dl>
</template>
