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
import { faExclamationSquare} from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from '@/Composables/useTrans'

library.add(faClock, faList, faExclamationSquare, faCheck, faBox, faCheckCircle, faBoxOpen, faHourglassStart, faAllergies, faChartLine)

interface RouteTarget {
    name: string
    parameters?: object
}

interface QueuedPrefix {
    value: number
    route_target?: RouteTarget
}

interface MetricData {
    value: number | null
    route_target?: RouteTarget
    queued_prefix?: QueuedPrefix
}

interface MetricItem {
    key: string
    label: string
    icon?: string[]
    breakdown_icons?: { icon: string; tooltip: string }[]
}

interface Metric {
    key: string
    label: string
    type: string
    icon?: string[]
    caption?: string
    items?: MetricItem[]
}

interface DashboardData {
    dimension: {
        key: string
        label: string
        items: { key: string; label: string; route_target?: RouteTarget }[]
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
        [metricKey: string]: { value: number; route_target?: RouteTarget; queued_prefix?: QueuedPrefix }
    }
    grand_total: {
        value: number
        icon?: string[]
        route_target?: RouteTarget
    }
}

const props = defineProps<{
    tab: string
    data: DashboardData
}>()

const rows = computed(() => {
    if (!props.data?.dimension) {
        return [{ key: "_global", label: null, route_target: undefined }]
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

const hasExtras = (cell?: { breakdown?: unknown[]; lines?: number; shared?: number }) => !!cell?.breakdown?.length || cell?.lines !== undefined || cell?.shared !== undefined

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

                <div
                    class="h-14 md:h-20 flex items-center justify-center font-semibold text-xs md:text-lg border-b border-gray-200 px-1 md:px-4">
                    <span class="md:hidden">
                        {{ data.dimension.label.charAt(0).toUpperCase() }}
                    </span>
                    <span class="hidden md:inline">
                        {{ data.dimension.label }}
                    </span>
                </div>

                <component v-for="row in rows" :key="row.key"
                    :is="getSafeRoute(row.route_target) ? Link : 'div'"
                    :href="getSafeRoute(row.route_target) ?? undefined"
                    :class="['h-9 md:h-11 flex items-center justify-center text-xs md:text-lg border-b border-gray-100 last:border-b-0', getSafeRoute(row.route_target) ? 'hover:underline cursor-pointer' : '']">
                    <span class="md:hidden">
                        {{ row.label.length <= 6 ? row.label : row.label.charAt(0).toUpperCase() }}
                    </span>
                    <span class="hidden md:inline">
                        {{ row.label }}
                    </span>
                </component>

                <div class="h-10 md:h-12 flex items-center justify-center text-xs md:text-lg border-t border-gray-200">
                    <span class="md:hidden">Σ</span>
                    <span class="hidden md:inline">{{ ctrans("Total") }}</span>
                </div>
            </div>

            <!-- ================= METRICS ================= -->
            <template v-for="metric in data?.metrics" :key="metric.key">

                <!-- SINGLE METRIC -->
                <div v-if="metric.type !== 'group'"
                    class="basis-0 min-w-[20px] sm:min-w-[40px] md:min-w-[140px] flex flex-col rounded-lg md:rounded-xl border border-gray-200"
                    :style="{ flexGrow: 1 }">

                    <div
                        class="h-14 md:h-20 flex flex-col items-center justify-center md:gap-1 border-b border-gray-200 px-1 md:px-4">
                        <span class="hidden md:inline text-lg font-semibold">
                            {{ metric.label }}
                        </span>
                        <Icon v-if="metric.icon" :data="metric" class='text-xs md:text-xl' />
                    </div>

                    <template v-for="row in rows" :key="row.key">
                        <component :is="getSafeRoute(data.data[row.key]?.[metric.key]?.route_target) ? Link : 'div'"
                            :href="getSafeRoute(data.data[row.key]?.[metric.key]?.route_target) ?? undefined" :class="[
                                'h-9 md:h-11 flex items-center justify-center text-xs md:text-lg border-b border-gray-100 last:border-b-0',
                                isWeakValue(data.data[row.key]?.[metric.key]?.value)
                                    ? 'opacity-40'
                                    : '',
                                getSafeRoute(data.data[row.key]?.[metric.key]?.route_target)
                                    ? 'hover:underline cursor-pointer'
                                    : ''
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

                <!-- GROUP METRIC -->
                <div v-else
                    class="relative flex basis-0 min-w-[20px] sm:min-w-[40px] md:min-w-max rounded-lg md:rounded-xl border border-gray-200"
                    :style="{ flexGrow: metric.items.length }">
                    <span v-if="metric.caption" class="absolute -top-2 left-3 bg-white px-1.5 text-[10px] md:text-xs leading-none text-gray-400 uppercase tracking-wide">
                        {{ metric.caption }}
                    </span>
                    <div v-for="(item, itemIndex) in metric.items" :key="item.key"
                        class="flex-1 min-w-[20px] sm:min-w-[40px] md:min-w-[140px] flex flex-col"
                        :class="[itemIndex < (metric.items?.length ?? 0) - 1 ? 'border-r border-gray-200' : '', hasExtras(data.totals[item.key]) ? 'md:min-w-max' : '']">
                        <div
                            class="h-14 md:h-20 flex flex-col items-center justify-center gap-1 border-b border-gray-200 px-1 md:px-4">
                            <span class="hidden md:inline text-lg font-semibold">
                                {{ item.label }}
                            </span>
                            <Icon v-if="item.icon" :data="item" class='text-xs md:text-lg' />
                            <!-- Icons over the breakdown cells, same widths as the cells below so they line up -->
                            <div v-if="item.breakdown_icons?.length" class="hidden md:flex items-center gap-2 text-xs text-gray-400">
                                <span class="w-14" />
                                <span v-if="data.totals[item.key]?.shared !== undefined" class="w-7 md:w-9 -ml-1" />
                                <span class="inline-flex border border-transparent divide-x divide-transparent">
                                    <span v-for="(part, partIndex) in item.breakdown_icons" :key="partIndex" v-tooltip="part.tooltip" class="w-9 text-center">
                                        <FontAwesomeIcon :icon="part.icon" fixed-width aria-hidden="true" />
                                    </span>
                                </span>
                                <span v-if="data.totals[item.key]?.lines !== undefined" class="w-12" />
                            </div>
                        </div>

                        <template v-for="row in rows" :key="row.key + '-' + item.key">
                            <div class="h-9 md:h-11 flex items-center justify-center gap-2 text-xs md:text-lg border-b border-gray-100 last:border-b-0"
                                :class="hasExtras(data.totals[item.key]) ? 'px-2 md:px-5' : ''">
                                <template v-if="data.data[row.key]?.[item.key]?.queued_prefix?.value">
                                    <component
                                        :is="getSafeRoute(data.data[row.key]?.[item.key]?.queued_prefix?.route_target) ? Link : 'span'"
                                        :href="getSafeRoute(data.data[row.key]?.[item.key]?.queued_prefix?.route_target) ?? undefined"
                                        v-tooltip="'Queued: ' + data.data[row.key]?.[item.key]?.queued_prefix?.value"
                                        class="opacity-80 hover:underline cursor-pointer tabular-nums"
                                    >{{ data.data[row.key]?.[item.key]?.queued_prefix?.value }}<template v-if="row.key !== 'dropshipping'">+</template></component><span v-if="row.key === 'dropshipping'" class="opacity-60">+</span>
                                </template>
                                <component
                                    :is="getSafeRoute(data.data[row.key]?.[item.key]?.route_target) ? Link : 'span'"
                                    :href="getSafeRoute(data.data[row.key]?.[item.key]?.route_target) ?? undefined"
                                    :class="[
                                        hasExtras(data.data[row.key]?.[item.key]) ? 'w-10 md:w-14 text-right tabular-nums' : '',
                                        isWeakValue(data.data[row.key]?.[item.key]?.value) ? 'opacity-40' : '',
                                        getSafeRoute(data.data[row.key]?.[item.key]?.route_target) ? 'hover:underline cursor-pointer' : ''
                                    ]"
                                >{{ data.data[row.key]?.[item.key]?.value ?? '-' }}</component>
                                <!-- Section: Shared with another column (partly here, partly there) -->
                                <span v-if="data.data[row.key]?.[item.key]?.shared !== undefined"
                                    v-tooltip="data.data[row.key]?.[item.key]?.shared ? data.data[row.key]?.[item.key]?.shared_tooltip : undefined"
                                    :class="['w-7 md:w-9 -ml-1 text-left text-xs md:text-sm tabular-nums', data.data[row.key]?.[item.key]?.shared ? 'text-violet-600' : '']"
                                >{{ data.data[row.key]?.[item.key]?.shared ? `(${data.data[row.key]?.[item.key]?.shared})` : '' }}</span>

                                <!-- Section: Warning -->
                                <Link v-if="data.data[row.key]?.[item.key]?.warning"
                                    :href="data.data[row.key]?.[item.key]?.warning?.route_target?.name ? route(data.data[row.key]?.[item.key]?.warning?.route_target?.name, data.data[row.key]?.[item.key]?.warning?.route_target.parameters) : '#'"
                                    class="relative bg-amber-300 text-amber-700 rounded px-2.5  ml-2 opacity-70 hover:opacity-100"
                                    v-tooltip="data.data[row.key]?.[item.key]?.warning?.tooltip"
                                >
                                    {{ data.data[row.key]?.[item.key]?.warning?.value }}

                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px]" fixed-width aria-hidden="true" />
                                </Link>

                                <!-- Section: CRM Warning -->
                                <component
                                    v-if="data.data[row.key]?.[item.key]?.crm_warning"
                                    :is="getSafeRoute(data.data[row.key]?.[item.key]?.crm_warning?.route_target) ? Link : 'span'"
                                    :href="getSafeRoute(data.data[row.key]?.[item.key]?.crm_warning?.route_target) ?? undefined"
                                    class="relative bg-purple-300 text-purple-700 rounded px-2.5 ml-2 opacity-70 hover:opacity-100"
                                    v-tooltip="data.data[row.key]?.[item.key]?.crm_warning?.tooltip"
                                >
                                    {{ data.data[row.key]?.[item.key]?.crm_warning?.value }}

                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px]" fixed-width aria-hidden="true" />
                                </component>

                                <!-- Section: Breakdown -->
                                <span v-if="data.data[row.key]?.[item.key]?.breakdown?.length" class="inline-flex items-stretch rounded-md border border-gray-200 bg-gray-50 text-[11px] md:text-sm tabular-nums divide-x divide-gray-200">
                                    <component
                                        v-for="(part, partIndex) in data.data[row.key]?.[item.key].breakdown" :key="partIndex"
                                        :is="getSafeRoute(part.route_target) ? Link : 'span'"
                                        :href="getSafeRoute(part.route_target) ?? undefined"
                                        v-tooltip="part.tooltip"
                                        :class="['w-7 md:w-9 py-0.5 text-center', isWeakValue(part.value) ? 'text-gray-300' : 'text-gray-600', getSafeRoute(part.route_target) ? 'hover:underline cursor-pointer' : '']"
                                    >{{ part.value }}</component>
                                </span>
                                <!-- Section: Product lines -->
                                <component v-if="data.data[row.key]?.[item.key]?.lines !== undefined"
    :is="getSafeRoute(data.data[row.key]?.[item.key]?.lines_route) ? Link : 'span'"
    :href="getSafeRoute(data.data[row.key]?.[item.key]?.lines_route) ?? undefined"
                                    v-tooltip="ctrans('Product lines')"
                                    :class="['w-10 md:w-12 py-0.5 text-center text-[10px] md:text-xs rounded-md border tabular-nums', isWeakValue(data.data[row.key]?.[item.key]?.lines) ? 'border-gray-200 bg-gray-50 text-gray-300' : 'border-teal-200 bg-teal-50 text-teal-700 hover:bg-teal-100']"
                                >{{ data.data[row.key]?.[item.key]?.lines }}</component>
                            </div>
                        </template>

                        <div v-if="data?.dimension"
                            class="h-10 md:h-12 flex items-center justify-center gap-2 text-xs md:text-lg border-t border-gray-200"
                            :class="hasExtras(data.totals[item.key]) ? 'px-2 md:px-5' : ''">
                            <template v-if="data.totals[item.key]?.queued_prefix?.value">
                                <component
                                    :is="getSafeRoute(data.totals[item.key]?.queued_prefix?.route_target) ? Link : 'span'"
                                    :href="getSafeRoute(data.totals[item.key]?.queued_prefix?.route_target) ?? undefined"
                                    v-tooltip="'Queued: ' + data.totals[item.key]?.queued_prefix?.value"
                                    class="opacity-80 hover:underline cursor-pointer tabular-nums"
                                >{{ data.totals[item.key]?.queued_prefix?.value }}</component><span class="opacity-60">+</span>
                            </template>
                            <component
                                :is="getSafeRoute(data.totals[item.key]?.route_target) ? Link : 'span'"
                                :href="getSafeRoute(data.totals[item.key]?.route_target) ?? undefined"
                                :class="[getSafeRoute(data.totals[item.key]?.route_target) ? 'hover:underline cursor-pointer' : '', hasExtras(data.totals[item.key]) ? 'w-10 md:w-14 text-right tabular-nums' : '']"
                            >{{ data.totals[item.key]?.value ?? '-' }}</component>
                            <!-- Section: Shared with another column (partly here, partly there) -->
                            <span v-if="data.totals[item.key]?.shared !== undefined"
                                v-tooltip="data.totals[item.key]?.shared ? data.totals[item.key]?.shared_tooltip : undefined"
                                :class="['w-7 md:w-9 -ml-1 text-left text-xs md:text-sm tabular-nums', data.totals[item.key]?.shared ? 'text-violet-600' : '']"
                            >{{ data.totals[item.key]?.shared ? `(${data.totals[item.key]?.shared})` : '' }}</span>

                            <!-- Section: Warning -->
                            <Link v-if="data.totals[item.key]?.warning"
                                :href="data.totals[item.key]?.warning?.route_target?.name ? route(data.totals[item.key]?.warning?.route_target?.name, data.totals[item.key]?.warning?.route_target.parameters) : '#'"
                                class="relative bg-amber-300 text-amber-700 rounded px-2.5  ml-2 opacity-70 hover:opacity-100"
                                v-tooltip="data.totals[item.key]?.warning?.tooltip"
                            >
                                {{ data.totals[item.key]?.warning?.value }}

                                <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                                <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px]" fixed-width aria-hidden="true" />
                            </Link>

                            <!-- Section: CRM Warning -->
                            <component
                                v-if="data.totals[item.key]?.crm_warning"
                                :is="getSafeRoute(data.totals[item.key]?.crm_warning?.route_target) ? Link : 'span'"
                                :href="getSafeRoute(data.totals[item.key]?.crm_warning?.route_target) ?? undefined"
                                class="relative bg-purple-300 text-purple-700 rounded px-2.5 ml-2 opacity-70 hover:opacity-100"
                                v-tooltip="data.totals[item.key]?.crm_warning?.tooltip"
                            >
                                {{ data.totals[item.key]?.crm_warning?.value }}

                                <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                                <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px]" fixed-width aria-hidden="true" />
                            </component>

                            <!-- Section: Breakdown -->
                            <span v-if="data.totals[item.key]?.breakdown?.length" class="inline-flex items-stretch rounded-md border border-gray-200 bg-gray-50 text-[11px] md:text-sm tabular-nums divide-x divide-gray-200">
                                <component
                                    v-for="(part, partIndex) in data.totals[item.key].breakdown" :key="partIndex"
                                    :is="getSafeRoute(part.route_target) ? Link : 'span'"
                                    :href="getSafeRoute(part.route_target) ?? undefined"
                                    v-tooltip="part.tooltip"
                                    :class="['w-7 md:w-9 py-0.5 text-center', isWeakValue(part.value) ? 'text-gray-300' : 'text-gray-600', getSafeRoute(part.route_target) ? 'hover:underline cursor-pointer' : '']"
                                >{{ part.value }}</component>
                            </span>
                            <!-- Section: Product lines -->
                            <component v-if="data.totals[item.key]?.lines !== undefined"
    :is="getSafeRoute(data.totals[item.key]?.lines_route) ? Link : 'span'"
    :href="getSafeRoute(data.totals[item.key]?.lines_route) ?? undefined"
                                v-tooltip="ctrans('Product lines')"
                                :class="['w-10 md:w-12 py-0.5 text-center text-[10px] md:text-xs rounded-md border tabular-nums', isWeakValue(data.totals[item.key]?.lines) ? 'border-gray-200 bg-gray-50 text-gray-300' : 'border-teal-200 bg-teal-50 text-teal-700 hover:bg-teal-100']"
                            >{{ data.totals[item.key]?.lines }}</component>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ================= ROW TOTAL BOX ================= -->
            <div v-if="data?.grand_total" class="basis-0 min-w-[20px] sm:min-w-[40px] md:min-w-[140px] flex flex-col rounded-lg md:rounded-xl border border-gray-200"
                :style="{ flexGrow: 1 }">
                <div
                    class="h-14 md:h-20 flex flex-col items-center justify-center font-semibold text-xs md:text-lg gap-1 border-b border-gray-200">
                    <span class="hidden md:inline">{{ ctrans("Total") }}</span>
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

                <component
    v-if="data?.dimension"
    :is="getSafeRoute(data.grand_total?.route_target) ? Link : 'div'"
    :href="getSafeRoute(data.grand_total?.route_target) ?? undefined"
    :class="[
        'h-10 md:h-12 flex items-center justify-center text-xs md:text-lg border-t border-gray-200',
        getSafeRoute(data.grand_total?.route_target) ? 'hover:underline cursor-pointer' : ''
    ]"
>
    {{ data.grand_total?.value ?? '-' }}
</component>
            </div>

        </div>
    </div>
</template>
