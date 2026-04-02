<script setup lang="ts">
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faHistory, faBox, faInventory, faSkullCow, faBan, faDollarSign } from "@fal"
import { faTimesCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { router } from "@inertiajs/vue3"
import { useLocaleStore } from "@/Stores/locale"

library.add(faChevronDown, faHistory, faBox, faInventory, faSkullCow, faBan, faDollarSign, faTimesCircle)

const locale = useLocaleStore()

type RouteInfo = {
    name: string
    parameters: Record<string, string | number>
} | null

defineProps<{
    rows: {
        name: string
        slug: string
        currency_code: string
        date: string | null
        number_org_stocks: string
        number_out_of_stock_org_stocks: string
        percentage_out_of_stock: number
        number_locations: string
        org_stock_value: number | null
        number_org_stocks_not_sold_1y: string
        percentage_not_sold_1y: number
        value_dormant_stock_1y: number | null
        percentage_dormant_1y: number
        history_route: RouteInfo
        locations_route: RouteInfo
    }[]
}>()

const isOpen = ref(false)

const visitHistoryTab = (historyRoute: RouteInfo, tab: string) => {
    if (!historyRoute) return
    router.visit(route(historyRoute.name, { ...historyRoute.parameters, tab }))
}

const visitRoute = (routeInfo: RouteInfo) => {
    if (!routeInfo) return
    router.visit(route(routeInfo.name, routeInfo.parameters))
}
</script>

<template>
    <div class="bg-white border-t border-gray-200">
        <button
            class="w-full flex items-center justify-between px-6 py-3 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"
            @click="isOpen = !isOpen"
        >
            <div class="flex items-center gap-x-2">
                <FontAwesomeIcon icon="fal fa-history" class="text-gray-400" fixed-width aria-hidden="true" />
                {{ trans('Stock History') }}
            </div>
            <FontAwesomeIcon
                icon="fal fa-chevron-down"
                class="text-gray-400 transition-transform duration-200"
                :class="isOpen ? 'rotate-180' : ''"
                fixed-width
                aria-hidden="true"
            />
        </button>

        <div v-if="isOpen" class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-t border-gray-100 bg-gray-50 text-gray-500">
                        <th class="px-4 py-2 text-left font-medium">{{ trans('Organisation') }}</th>
                        <th class="px-4 py-2 text-right font-medium">
                            <FontAwesomeIcon icon="fal fa-dollar-sign" fixed-width aria-hidden="true" />
                            {{ trans('Stock Value') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            <FontAwesomeIcon icon="fal fa-box" fixed-width aria-hidden="true" />
                            {{ trans('Stored SKUs') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            <FontAwesomeIcon icon="fal fa-inventory" fixed-width aria-hidden="true" />
                            {{ trans('Locations') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            <FontAwesomeIcon icon="fas fa-times-circle" class="text-red-500" fixed-width aria-hidden="true" />
                            {{ trans('Out of Stock') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            <FontAwesomeIcon icon="fal fa-skull-cow" class="text-red-500" fixed-width aria-hidden="true" />
                            {{ trans('Dormant 1Y') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            <FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" />
                            {{ trans('No Sold 1Y') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="row in rows"
                        :key="row.slug"
                    >
                        <td
                            class="px-4 py-2.5 font-medium text-gray-700 cursor-pointer hover:bg-indigo-50 transition-colors"
                            @click="router.visit(route('grp.org.dashboard.show', { organisation: row.slug }))"
                        >
                            {{ row.name }}
                            <span v-if="row.date" class="ml-1.5 text-gray-400 font-normal">{{ row.date }}</span>
                        </td>
                        <td
                            class="px-4 py-2.5 text-right tabular-nums text-gray-700 transition-colors"
                            :class="row.history_route ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default'"
                            @click="visitHistoryTab(row.history_route, 'org_stocks')"
                        >
                            {{ row.org_stock_value !== null ? locale.currencyFormat(row.currency_code, row.org_stock_value) : '--' }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right tabular-nums text-gray-700 transition-colors"
                            :class="row.history_route ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default'"
                            @click="visitHistoryTab(row.history_route, 'org_stocks')"
                        >
                            {{ row.number_org_stocks }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right tabular-nums text-gray-700 transition-colors"
                            :class="row.locations_route ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default'"
                            @click="visitRoute(row.locations_route)"
                        >
                            {{ row.number_locations }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right tabular-nums transition-colors"
                            :class="row.history_route ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default'"
                            @click="visitHistoryTab(row.history_route, 'out_of_stock')"
                        >
                            <span class="text-red-500">{{ row.number_out_of_stock_org_stocks }}</span>
                            <span v-if="row.percentage_out_of_stock > 0" class="ml-1 text-red-400">{{ row.percentage_out_of_stock }}%</span>
                        </td>
                        <td
                            class="px-4 py-2.5 text-right tabular-nums transition-colors"
                            :class="row.history_route ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default'"
                            @click="visitHistoryTab(row.history_route, 'dormant_stock_1y')"
                        >
                            <span class="text-red-500">{{ row.value_dormant_stock_1y !== null ? locale.currencyFormat(row.currency_code, row.value_dormant_stock_1y) : '--' }}</span>
                            <span v-if="row.percentage_dormant_1y > 0" class="ml-1 text-red-400">{{ row.percentage_dormant_1y }}%</span>
                        </td>
                        <td
                            class="px-4 py-2.5 text-right tabular-nums transition-colors"
                            :class="row.history_route ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default'"
                            @click="visitHistoryTab(row.history_route, 'not_sold_1y')"
                        >
                            <span class="text-red-500">{{ row.number_org_stocks_not_sold_1y }}</span>
                            <span v-if="row.percentage_not_sold_1y > 0" class="ml-1 text-red-400">{{ row.percentage_not_sold_1y }}%</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
