<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 Feb 2023 14:32:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import Icon from "../Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faClock,
    faList,
    faCheck,
    faBox,
    faCheckCircle,
    faBoxOpen,
    faHourglassStart,
    faAllergies,
    faChartLine,
} from "@fal"

library.add(faClock, faList, faCheck, faBox, faCheckCircle, faBoxOpen, faHourglassStart, faAllergies, faChartLine)

interface RouteTarget {
    name: string
    parameters?: object
}

interface MetricData {
    value: number | null
    route_target?: RouteTarget
}

interface MetricItem {
    key: string
    label: string
    icon?: string[]
}

interface Metric {
    key: string
    label: string
    type: string
    icon?: string[]
    items?: MetricItem[]
}

interface DashboardData {
    dimension: {
        key: string
        label: string
        items: { key: string; label: string }[]
    }
    metrics: Metric[]
    data: {
        [rowKey: string]: {
            [metricKey: string]: MetricData
        }
    }
    row_totals: {
        [rowKey: string]: { value: number }
    }
    totals: {
        [metricKey: string]: { value: number }
    }
    grand_total: {
        value: number
        icon?: string[]
    }
}

const props = defineProps<{
    tab: string
    data: DashboardData
}>()

const rows = computed(() => {
    if (!props.data?.dimension) {
        return [{ key: "_global", label: null }]
    }
    return props.data.dimension.items
})

const getSafeRoute = (routeTarget?: RouteTarget): string | null => {
    if (!routeTarget) return null
    try {
        if (route().has(routeTarget.name)) {
            return route(routeTarget.name, (routeTarget.parameters ?? {}) as Record<string, string>)
        }
    } catch {
        return null
    }
    return null
}
</script>

<template>
    <div class="overflow-x-auto bg-white px-4 pb-4">
        <div class="flex gap-3 w-full pt-3">

            <!-- ================= DIMENSION COLUMN ================= -->
            <div v-if="data?.dimension"
                class="flex-1 basis-0 min-w-[120px] flex flex-col rounded-xl border border-gray-200">
                <div
                    class="h-14 flex items-center justify-center text-xs font-semibold text-gray-600 border-b border-gray-200 px-4">
                    {{ data.dimension.label }}
                </div>

                <div v-for="row in rows" :key="row.key"
                    class="h-11 flex items-center justify-center text-sm text-gray-500 border-b border-gray-100 last:border-b-0">
                    {{ row.label }}
                </div>

                <div
                    class="h-12 flex items-center justify-center text-sm font-semibold text-gray-700 border-t border-gray-200">
                    Total
                </div>
            </div>

            <!-- ================= METRICS ================= -->
            <template v-for="metric in data?.metrics" :key="metric.key">

                <!-- SINGLE METRIC -->
                <div v-if="metric.type !== 'group'"
                    class="flex-1 basis-0 min-w-[140px] flex flex-col rounded-xl border border-gray-200">
                    <div class="h-14 flex flex-col items-center justify-center gap-1 border-b border-gray-200 px-4">
                        <span class="text-xs font-semibold text-gray-600">{{ metric.label }}</span>
                        <Icon v-if="metric.icon" :data="metric" class='text-xl' />
                    </div>

                    <template v-for="row in rows" :key="row.key">
                        <component :is="getSafeRoute(data.data[row.key]?.[metric.key]?.route_target) ? Link : 'div'"
                            :href="getSafeRoute(data.data[row.key]?.[metric.key]?.route_target) ?? undefined" :class="[
                                'h-11 flex items-center justify-center text-base text-gray-500 border-b border-gray-100 last:border-b-0',
                                getSafeRoute(data.data[row.key]?.[metric.key]?.route_target)
                                    ? 'hover:text-indigo-600 hover:underline cursor-pointer'
                                    : ''
                            ]">
                            {{ data.data[row.key]?.[metric.key]?.value ?? '-' }}
                        </component>
                    </template>

                    <div v-if="data?.dimension"
                        class="h-12 flex items-center justify-center text-sm font-semibold text-gray-700 border-t border-gray-200">
                        {{ data.totals[metric.key]?.value ?? '-' }}
                    </div>
                </div>

                <!-- GROUP METRIC -->
                <div v-else class="flex flex-1 basis-0 min-w-max rounded-xl border border-gray-200">
                    <div v-for="(item, itemIndex) in metric.items" :key="item.key" class="min-w-[140px] flex flex-col"
                        :class="itemIndex < (metric.items?.length ?? 0) - 1 ? 'border-r border-gray-200' : ''">
                        <div class="h-14 flex flex-col items-center justify-center gap-1 border-b border-gray-200 px-4">
                            <span class="text-xs font-semibold text-gray-600">{{ item.label }}</span>
                            <Icon v-if="item.icon" :data="item" class='text-xl' />
                        </div>

                        <template v-for="row in rows" :key="row.key + '-' + item.key">
                            <component :is="getSafeRoute(data.data[row.key]?.[item.key]?.route_target) ? Link : 'div'"
                                :href="getSafeRoute(data.data[row.key]?.[item.key]?.route_target) ?? undefined" :class="[
                                    'h-11 flex items-center justify-center text-base text-gray-500 border-b border-gray-100 last:border-b-0',
                                    getSafeRoute(data.data[row.key]?.[item.key]?.route_target)
                                        ? 'hover:text-indigo-600 hover:underline cursor-pointer'
                                        : ''
                                ]">
                                {{ data.data[row.key]?.[item.key]?.value ?? '-' }}
                            </component>
                        </template>

                        <div v-if="data?.dimension"
                            class="h-12 flex items-center justify-center text-sm font-semibold text-gray-700 border-t border-gray-200">
                            {{ data.totals[item.key]?.value ?? '-' }}
                        </div>
                    </div>
                </div>
            </template>

            <!-- ================= ROW TOTAL BOX ================= -->
            <div class="flex-1 basis-0 min-w-[140px] flex flex-col rounded-xl border border-gray-200">
                <div class="h-14 flex flex-col items-center justify-center gap-1 border-b border-gray-200 px-4">
                    <span class="text-xs font-semibold text-gray-600">Total</span>
                    <Icon v-if="data?.grand_total?.icon" :data="data.grand_total" class='text-xl' />
                </div>

                <div v-for="row in rows" :key="row.key"
                    class="h-11 flex items-center justify-center text-base font-semibold text-gray-600 border-b border-gray-100 last:border-b-0">
                    {{ data.row_totals[row.key]?.value ?? '-' }}
                </div>

                <div v-if="data?.dimension"
                    class="h-12 flex items-center justify-center text-sm font-bold text-gray-700 border-t border-gray-200">
                    {{ data.grand_total?.value ?? '-' }}
                </div>
            </div>

        </div>
    </div>
</template>