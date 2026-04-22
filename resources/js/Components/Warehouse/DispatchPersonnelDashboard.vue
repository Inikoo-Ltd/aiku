<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import Icon from "../Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClock, faList, faCheck, faBox, faCheckCircle, faBoxOpen, faHourglassStart, faAllergies, faChartLine, faFileAlt, faDolly, faPersonCarry, faCheckDouble } from "@fal"
import { faExclamationSquare } from "@fas"

library.add(faClock, faList, faExclamationSquare, faCheck, faBox, faCheckCircle, faBoxOpen, faHourglassStart, faAllergies, faChartLine, faFileAlt, faDolly, faPersonCarry, faCheckDouble)

interface RouteTarget {
    name: string
    parameters?: Record<string, string>
}

interface RefItem {
    reference: string
    route: RouteTarget
}

interface CellData {
    value: number
    route_target?: RouteTarget
    items?: RefItem[]
}

interface Metric {
    key: string
    label: string
    type: 'stat' | 'refs'
    tooltip?: string
    icon?: string[]
}

interface DimensionItem {
    key: string
    label: string
}

const props = defineProps<{
    tab: string
    data: {
        dimension: { key: string; label: string; items: DimensionItem[] }
        metrics: Metric[]
        data: Record<string, Record<string, CellData>>
        row_totals: Record<string, { value: number; route_target?: RouteTarget }>
        totals: Record<string, { value: number }>
        grand_total: { value: number; tooltip?: string; icon?: string[] }
    }
}>()

const lastRowIdx = computed(() => props.data.dimension.items.length - 1)

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

const isWeakValue = (value: number | null | undefined) => value === null || value === 0

const rowBorder = (idx: number) =>
    idx < lastRowIdx.value
        ? 'border-x border-b border-l-gray-200 border-r-gray-200 border-b-gray-100'
        : 'border-x border-l-gray-200 border-r-gray-200'
</script>

<template>
    <div class="bg-white px-1 sm:px-2 md:px-4 overflow-x-auto pb-4">
        <table class="w-full border-separate border-spacing-x-1 md:border-spacing-x-3 border-spacing-y-0 mt-3">
            <!-- ── HEADER ── -->
            <thead>
                <tr>
                    <th class="h-14 md:h-20 border border-gray-200 rounded-t-lg md:rounded-t-xl px-1 md:px-4 font-semibold text-xs md:text-lg text-center align-middle">
                        <span class="md:hidden">{{ data.dimension.label.charAt(0).toUpperCase() }}</span>
                        <span class="hidden md:inline">{{ data.dimension.label }}</span>
                    </th>
                    <th
                        v-for="metric in data.metrics"
                        :key="'h-' + metric.key"
                        class="h-14 md:h-20 border border-gray-200 rounded-t-lg md:rounded-t-xl px-1 md:px-4 align-middle"
                        :style="metric.type === 'refs' ? 'width: 22%' : ''"
                    >
                        <div class="flex flex-col items-center justify-center md:gap-1">
                            <span class="hidden md:inline text-lg font-semibold">{{ metric.label }}</span>
                            <Icon v-if="metric.icon" :data="metric" class="text-xs md:text-xl" />
                        </div>
                    </th>
                    <th class="h-14 md:h-20 border border-gray-200 rounded-t-lg md:rounded-t-xl px-1 md:px-4 align-middle">
                        <div class="flex flex-col items-center justify-center gap-1 font-semibold text-xs md:text-lg">
                            <span class="hidden md:inline">Total</span>
                            <Icon v-if="data?.grand_total?.icon" :data="data.grand_total" class="text-xs md:text-lg" />
                        </div>
                    </th>
                </tr>
            </thead>

            <!-- ── DATA ROWS ── -->
            <tbody>
                <tr v-for="(row, idx) in data.dimension.items" :key="row.key">
                    <!-- Dimension -->
                    <td
                        :class="[
                            'px-1 md:px-3 py-1.5 text-xs md:text-base text-center align-middle',
                            rowBorder(idx)
                        ]"
                    >
                        <span class="md:hidden">{{ row.label.charAt(0).toUpperCase() }}</span>
                        <span class="hidden md:inline">{{ row.label }}</span>
                    </td>

                    <!-- Metrics -->
                    <template v-for="metric in data.metrics" :key="row.key + '-' + metric.key">
                        <!-- STAT -->
                        <td
                            v-if="metric.type === 'stat'"
                            :class="['py-1.5 text-xs md:text-lg text-center align-middle tabular-nums', rowBorder(idx)]"
                        >
                            <span :class="isWeakValue(data.data[row.key]?.[metric.key]?.value) ? 'opacity-40' : ''">
                                {{ data.data[row.key]?.[metric.key]?.value ?? '-' }}
                            </span>
                        </td>
                        <!-- REFS -->
                        <td
                            v-else-if="metric.type === 'refs'"
                            :class="['px-2 md:px-3 py-1.5 align-top', rowBorder(idx)]"
                        >
                            <div v-if="!data.data[row.key]?.[metric.key]?.items?.length" class="text-xs text-gray-300 italic">—</div>
                            <div v-else class="flex flex-wrap gap-1">
                                <Link
                                    v-for="item in data.data[row.key][metric.key].items"
                                    :key="item.reference"
                                    :href="getSafeRoute(item.route) ?? '#'"
                                    :class="[
                                        'inline-block px-1.5 py-0.5 rounded text-xs font-mono transition-colors',
                                        metric.key === 'orders'
                                            ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                                            : 'bg-orange-50 text-orange-700 hover:bg-orange-100'
                                    ]"
                                >
                                    {{ item.reference }}
                                </Link>
                            </div>
                        </td>
                    </template>

                    <!-- Row total -->
                    <td :class="['py-1.5 text-xs md:text-lg text-center align-middle tabular-nums', rowBorder(idx)]">
                        <Link
                            v-if="getSafeRoute(data.row_totals[row.key]?.route_target)"
                            :href="getSafeRoute(data.row_totals[row.key]?.route_target)!"
                            :class="['hover:underline cursor-pointer', isWeakValue(data.row_totals[row.key]?.value) ? 'opacity-40' : '']"
                        >
                            {{ data.row_totals[row.key]?.value ?? '-' }}
                        </Link>
                        <span v-else :class="isWeakValue(data.row_totals[row.key]?.value) ? 'opacity-40' : ''">
                            {{ data.row_totals[row.key]?.value ?? '-' }}
                        </span>
                    </td>
                </tr>
            </tbody>

            <!-- ── FOOTER ── -->
            <tfoot>
                <tr>
                    <td class="h-10 md:h-12 border border-gray-200 rounded-b-lg md:rounded-b-xl px-1 md:px-3 text-center text-xs md:text-lg align-middle">
                        <span class="md:hidden">Σ</span>
                        <span class="hidden md:inline">Total</span>
                    </td>
                    <td
                        v-for="metric in data.metrics"
                        :key="'f-' + metric.key"
                        class="h-10 md:h-12 border border-gray-200 rounded-b-lg md:rounded-b-xl text-center text-xs md:text-lg align-middle tabular-nums"
                    >
                        <span :class="metric.type === 'refs' ? 'opacity-60' : ''">
                            {{ data.totals[metric.key]?.value ?? '-' }}
                        </span>
                    </td>
                    <td class="h-10 md:h-12 border border-gray-200 rounded-b-lg md:rounded-b-xl text-center text-xs md:text-lg align-middle tabular-nums">
                        {{ data.grand_total?.value ?? '-' }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
