<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { trans } from 'laravel-vue-i18n'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from '@/Composables/useFormatTime'
import { routeType } from '@/types/route'
import { Intervals, Settings } from '@/types/Components/Dashboard'
import DashboardSettings from '@/Components/DataDisplay/Dashboard/DashboardSettings.vue'

type ChannelRow = {
    platform_type: string
    platform_name: string
    sales_channel_type: string
    sales_channel_name: string
    number_orders: number
    number_held_unpaid: number
    total_sales: number
    last_order_at: string | null
}

const props = defineProps<{
    title: string
    pageHead: any
    data: { currency_code: string, orders_route: routeType, period_label: string, period_from: string | null, period_to: string | null, rows: ChannelRow[] }
    intervals: Intervals
    settings: Settings
}>()

const locale = useLocaleStore()
const showDetail = ref(true)

const later = (a: string | null, b: string | null) => !a ? b : !b ? a : a > b ? a : b

const groups = computed(() => {
    const byKey: Record<string, any> = {}
    for (const row of props.data.rows) {
        const key = row.platform_type
        byKey[key] ??= { key, label: row.platform_name, number_orders: 0, number_held_unpaid: 0, total_sales: 0, last_order_at: null as string | null, children: {} as Record<string, any> }
        const g = byKey[key]
        g.number_orders += row.number_orders
        g.number_held_unpaid += row.number_held_unpaid
        g.total_sales += row.total_sales
        g.last_order_at = later(g.last_order_at, row.last_order_at)
        g.children[row.sales_channel_type] ??= { key: row.sales_channel_type, label: row.sales_channel_name, number_orders: 0, number_held_unpaid: 0, total_sales: 0, last_order_at: null as string | null }
        const c = g.children[row.sales_channel_type]
        c.number_orders += row.number_orders
        c.number_held_unpaid += row.number_held_unpaid
        c.total_sales += row.total_sales
        c.last_order_at = later(c.last_order_at, row.last_order_at)
    }
    return Object.values(byKey)
        .map((g: any) => ({ ...g, children: Object.values(g.children).sort((a: any, b: any) => b.total_sales - a.total_sales) }))
        .sort((a: any, b: any) => b.total_sales - a.total_sales)
})

const totals = computed(() => groups.value.reduce((t: any, g: any) => ({
    number_orders: t.number_orders + g.number_orders,
    number_held_unpaid: t.number_held_unpaid + g.number_held_unpaid,
    total_sales: t.total_sales + g.total_sales,
}), { number_orders: 0, number_held_unpaid: 0, total_sales: 0 }))

/* One child is not a breakdown */
const expandable = (g: any) => g.children.length > 1

const money = (value: number) => locale.currencyFormat(props.data.currency_code, value)
const share = (part: number) => totals.value.total_sales > 0 ? (part / totals.value.total_sales * 100).toFixed(1) + '%' : '—'
const periodText = computed(() => props.data.period_from ? props.data.period_label.toLowerCase() : trans('all time'))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="pt-3">
        <DashboardSettings :intervals="intervals" :settings="settings" currentTab="order_channels" :reloadOnly="['data', 'intervals']" />
    </div>

    <div class="mx-4 my-4">
        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <span class="text-sm font-medium text-gray-800">{{ trans('By platform and channel') }}</span>
                    <span class="ml-2 text-xs text-gray-400">{{ trans('where orders came from, and how they were placed') + ' · ' + periodText + ', ' + data.currency_code }}</span>
                </div>
                <button type="button" @click="showDetail = !showDetail"
                    class="shrink-0 text-xs text-gray-500 hover:text-gray-800 border border-gray-200 rounded-md px-2 py-1">
                    {{ showDetail ? trans('Collapse') : trans('Expand') }}
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-400 border-b border-gray-200">
                            <th class="py-1.5 pr-2 text-left font-normal">{{ trans('Platform') }}</th>
                            <th class="py-1.5 px-2 text-right font-normal">{{ trans('Orders') }}</th>
                            <th class="py-1.5 px-2 text-right font-normal" v-tooltip="trans('Orders sitting at submitted and unpaid right now, waiting for payment before the warehouse sees them. Not a period metric.')">{{ trans('Held unpaid') }}</th>
                            <th class="py-1.5 px-2 text-right font-normal">{{ trans('Sales') }}</th>
                            <th class="py-1.5 px-2 text-right font-normal">%</th>
                            <th class="py-1.5 pl-2 text-right font-normal">{{ trans('Last order') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="g in groups" :key="g.key">
                            <tr class="border-b border-gray-100 font-medium text-gray-700">
                                <td class="py-1.5 pr-2">{{ g.label }}</td>
                                <td class="py-1.5 px-2 text-right">{{ locale.number(g.number_orders) }}</td>
                                <td class="py-1.5 px-2 text-right" :class="g.number_held_unpaid > 0 ? 'text-orange-600 font-semibold' : 'text-gray-300'">{{ g.number_held_unpaid > 0 ? locale.number(g.number_held_unpaid) : '—' }}</td>
                                <td class="py-1.5 px-2 text-right">{{ money(g.total_sales) }}</td>
                                <td class="py-1.5 px-2 text-right text-gray-400">{{ share(g.total_sales) }}</td>
                                <td class="py-1.5 pl-2 text-right text-gray-400">{{ g.last_order_at ? useFormatTime(g.last_order_at) : '—' }}</td>
                            </tr>
                            <template v-if="showDetail && expandable(g)">
                                <tr v-for="c in g.children" :key="g.key + '.' + c.key" class="border-b border-gray-50 text-gray-500">
                                    <td class="py-1 pr-2 pl-6">{{ c.label }}</td>
                                    <td class="py-1 px-2 text-right">{{ locale.number(c.number_orders) }}</td>
                                    <td class="py-1 px-2 text-right" :class="c.number_held_unpaid > 0 ? 'text-orange-600 font-semibold' : 'text-gray-300'">{{ c.number_held_unpaid > 0 ? locale.number(c.number_held_unpaid) : '—' }}</td>
                                    <td class="py-1 px-2 text-right">{{ money(c.total_sales) }}</td>
                                    <td class="py-1 px-2 text-right text-gray-400">{{ share(c.total_sales) }}</td>
                                    <td class="py-1 pl-2 text-right text-gray-400">{{ c.last_order_at ? useFormatTime(c.last_order_at) : '—' }}</td>
                                </tr>
                            </template>
                        </template>
                        <tr class="text-gray-800 font-medium">
                            <td class="py-1.5 pr-2">{{ trans('All') }}</td>
                            <td class="py-1.5 px-2 text-right">{{ locale.number(totals.number_orders) }}</td>
                            <td class="py-1.5 px-2 text-right" :class="totals.number_held_unpaid > 0 ? 'text-orange-600 font-semibold' : 'text-gray-300'">{{ totals.number_held_unpaid > 0 ? locale.number(totals.number_held_unpaid) : '—' }}</td>
                            <td class="py-1.5 px-2 text-right">{{ money(totals.total_sales) }}</td>
                            <td class="py-1.5 px-2 text-right text-gray-400">100%</td>
                            <td class="py-1.5 pl-2 text-right"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
