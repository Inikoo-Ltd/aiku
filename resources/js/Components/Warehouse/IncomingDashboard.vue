<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import Icon from "../Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faCog,
    faCheck,
    faBox,
    faTruck,
    faTruckLoading,
    faClipboardCheck,
    faClipboardList,
    faPalletAlt,
    faPaperPlane,
    faTruckContainer,
    faTruckCouch,
    faExchange,
    faChair,
    faHandPaper,
    faCheckDouble,
    faChartLine,
} from "@fal"

library.add(
    faCog, faCheck, faBox, faTruck, faTruckLoading, faClipboardCheck,
    faClipboardList, faPalletAlt, faPaperPlane, faTruckContainer,
    faTruckCouch, faExchange, faChair, faHandPaper, faCheckDouble, faChartLine
)

interface RouteTarget {
    name: string
    parameters?: object | string[]
}

interface MetricData {
    value: number | null
    route_target?: RouteTarget
}

interface Metric {
    key: string
    label: string
    type: string
    icon?: string[]
    tooltip?: string
}

interface DashboardData {
    dimension?: {
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
        [rowKey: string]: { value: number; route_target?: RouteTarget }
    }
    totals: {
        [metricKey: string]: { value: number; route_target?: RouteTarget }
    }
    grand_total: {
        value: number
        icon?: string[]
        tooltip?: string
        route_target?: RouteTarget
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

const isWeakValue = (value: number | null | undefined) => {
    return value === null || value === 0
}
</script>

<template>
    <div class="bg-white px-1 sm:px-2 md:px-4 overflow-x-auto pb-4">
        <div class="flex gap-1 md:gap-3 w-full pt-3">

            <!-- ================= DIMENSION COLUMN ================= -->
            <div v-if="data?.dimension"
                class="basis-0 min-w-[20px] sm:min-w-[40px] md:min-w-[120px] flex flex-col rounded-lg md:rounded-xl border border-gray-200"
                :style="{ flexGrow: 1 }">

                <div class="h-14 md:h-20 flex items-center justify-center font-semibold text-xs md:text-lg border-b border-gray-200 px-1 md:px-4">
                    <span class="md:hidden">{{ data.dimension.label.charAt(0).toUpperCase() }}</span>
                    <span class="hidden md:inline">{{ data.dimension.label }}</span>
                </div>

                <div v-for="row in rows" :key="row.key"
                    class="h-9 md:h-11 flex items-center justify-center text-xs md:text-lg border-b border-gray-100 last:border-b-0">
                    <span class="md:hidden">{{ row.label?.charAt(0).toUpperCase() }}</span>
                    <span class="hidden md:inline">{{ row.label }}</span>
                </div>

                <div class="h-10 md:h-12 flex items-center justify-center text-xs md:text-lg border-t border-gray-200">
                    <span class="md:hidden">Σ</span>
                    <span class="hidden md:inline">Total</span>
                </div>
            </div>

            <!-- ================= METRICS ================= -->
            <div v-for="metric in data?.metrics" :key="metric.key"
                class="basis-0 min-w-[20px] sm:min-w-[40px] md:min-w-[140px] flex flex-col rounded-lg md:rounded-xl border border-gray-200"
                :style="{ flexGrow: 1 }">

                <div class="h-14 md:h-20 flex flex-col items-center justify-center md:gap-1 border-b border-gray-200 px-1 md:px-4">
                    <span class="hidden md:inline text-lg font-semibold">{{ metric.label }}</span>
                    <Icon v-if="metric.icon" :data="metric" class='text-xs md:text-xl' />
                </div>

                <template v-for="row in rows" :key="row.key">
                    <component :is="getSafeRoute(data.data[row.key]?.[metric.key]?.route_target) ? Link : 'div'"
                        :href="getSafeRoute(data.data[row.key]?.[metric.key]?.route_target) ?? undefined"
                        :class="[
                            'h-9 md:h-11 flex items-center justify-center text-xs md:text-lg border-b border-gray-100 last:border-b-0',
                            isWeakValue(data.data[row.key]?.[metric.key]?.value) ? 'opacity-40' : '',
                            getSafeRoute(data.data[row.key]?.[metric.key]?.route_target) ? 'hover:underline cursor-pointer' : ''
                        ]">
                        {{ data.data[row.key]?.[metric.key]?.value ?? '-' }}
                    </component>
                </template>

                <component v-if="data?.dimension"
                    :is="getSafeRoute(data.totals[metric.key]?.route_target) ? Link : 'div'"
                    :href="getSafeRoute(data.totals[metric.key]?.route_target) ?? undefined"
                    :class="[
                        'h-10 md:h-12 flex items-center justify-center text-xs md:text-lg border-t border-gray-200',
                        getSafeRoute(data.totals[metric.key]?.route_target) ? 'hover:underline cursor-pointer' : ''
                    ]">
                    {{ data.totals[metric.key]?.value ?? '-' }}
                </component>
            </div>

            <!-- ================= ROW TOTAL BOX ================= -->
            <div class="basis-0 min-w-[20px] sm:min-w-[40px] md:min-w-[140px] flex flex-col rounded-lg md:rounded-xl border border-gray-200"
                :style="{ flexGrow: 1 }">
                <div class="h-14 md:h-20 flex flex-col items-center justify-center font-semibold text-xs md:text-lg gap-1 border-b border-gray-200">
                    <span class="hidden md:inline">Total</span>
                    <Icon v-if="data?.grand_total?.icon" :data="data.grand_total" class='text-xs md:text-lg' />
                </div>

                <component v-for="row in rows" :key="row.key"
                    :is="getSafeRoute(data.row_totals[row.key]?.route_target) ? Link : 'div'"
                    :href="getSafeRoute(data.row_totals[row.key]?.route_target) ?? undefined"
                    :class="[
                        'h-9 md:h-11 flex items-center justify-center text-xs md:text-lg border-b border-gray-100 last:border-b-0',
                        isWeakValue(data.row_totals[row.key]?.value) ? 'opacity-40' : '',
                        getSafeRoute(data.row_totals[row.key]?.route_target) ? 'hover:underline cursor-pointer' : ''
                    ]">
                    {{ data.row_totals[row.key]?.value ?? '-' }}
                </component>

                <component v-if="data?.dimension"
                    :is="getSafeRoute(data.grand_total?.route_target) ? Link : 'div'"
                    :href="getSafeRoute(data.grand_total?.route_target) ?? undefined"
                    :class="[
                        'h-10 md:h-12 flex items-center justify-center text-xs md:text-lg border-t border-gray-200',
                        getSafeRoute(data.grand_total?.route_target) ? 'hover:underline cursor-pointer' : ''
                    ]">
                    {{ data.grand_total?.value ?? '-' }}
                </component>
            </div>
        </div>
    </div>
</template>
