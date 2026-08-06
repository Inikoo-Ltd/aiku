<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 06 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useLocaleStore } from '@/Stores/locale'
import { routeType } from '@/types/route'
import { route } from 'ziggy-js'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    overview: {
        currency_code: string
        totals: {
            spend: number
            revenue: number
            registrations: number
            purchases: number
            roas: number | null
            cac: number | null
        }
        channels: {
            name: string
            type: string
            spend: number
            revenue: number
            registrations: number
            roas: number | null
        }[]
        spend_by_day: { date: string, amount: number }[]
        traffic_sources_route: routeType
    }
}>()

const locale = useLocaleStore()
const money = (value: number) => locale.currencyFormat(props.overview.currency_code, value)

/* Series colors: categorical slots 1 (revenue) and 2 (spend) of the validated palette. */
const REVENUE_COLOR = '#2a78d6'
const SPEND_COLOR = '#eb6834'

const maxBarValue = computed(() =>
    Math.max(1, ...props.overview.channels.flatMap(c => [c.spend, c.revenue]))
)
const barWidth = (value: number) => `${Math.max(value > 0 ? 1.2 : 0, (value / maxBarValue.value) * 100)}%`

const hoveredChannel = ref<string | null>(null)

/* 30 days of spend folded into a 30-point sparkline path on a fixed 120x32 viewBox. */
const sparkline = computed(() => {
    const days = props.overview.spend_by_day
    if (days.length < 2) return null

    const max = Math.max(...days.map(d => d.amount), 1)
    const stepX = 120 / (days.length - 1)
    const y = (amount: number) => 29 - (amount / max) * 26

    return {
        path: days.map((d, i) => `${i === 0 ? 'M' : 'L'}${(i * stepX).toFixed(1)},${y(d.amount).toFixed(1)}`).join(' '),
        endX: 120,
        endY: y(days[days.length - 1].amount),
    }
})

const roasIsGood = computed(() => (props.overview.totals.roas ?? 0) >= 1)
</script>

<template>
    <div class="px-4 py-5 md:px-6 space-y-6">

        <!-- KPI row: ROAS is the hero, everything else supports it -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-px rounded-xl overflow-hidden bg-gray-200 ring-1 ring-gray-200">
            <div class="col-span-2 lg:col-span-1 lg:row-span-2 bg-white p-5 flex flex-col justify-center items-start">
                <div class="text-xs text-gray-500">{{ trans('Return on ad spend') }}</div>
                <template v-if="overview.totals.roas !== null">
                    <div class="mt-1 text-5xl font-semibold tracking-tight"
                        :class="roasIsGood ? 'text-[#006300]' : 'text-[#d03b3b]'">
                        {{ overview.totals.roas.toFixed(2) }}<span class="text-3xl">×</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ roasIsGood ? '▲' : '▼' }}
                        {{ roasIsGood ? trans('every unit spent comes back') : trans('spend is not paying back yet') }}
                    </div>
                </template>
                <template v-else>
                    <div class="mt-1 text-5xl font-semibold tracking-tight text-gray-300">—</div>
                    <div class="mt-1 text-xs text-gray-400">{{ trans('no ad spend recorded') }}</div>
                </template>
            </div>

            <div class="bg-white p-5">
                <div class="text-xs text-gray-500">{{ trans('Ad spend') }}</div>
                <div class="mt-1 flex items-end justify-between gap-2">
                    <div class="text-2xl font-medium text-gray-800">{{ money(overview.totals.spend) }}</div>
                    <svg v-if="sparkline" viewBox="0 0 124 32" class="w-24 h-8 shrink-0" aria-hidden="true">
                        <path :d="sparkline.path" fill="none" stroke="#c3c2b7" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" />
                        <circle :cx="sparkline.endX" :cy="sparkline.endY" r="4" :fill="SPEND_COLOR"
                            stroke="#ffffff" stroke-width="2" />
                    </svg>
                </div>
                <div v-if="sparkline" class="mt-0.5 text-xs text-gray-400">{{ trans('last 30 days') }}</div>
            </div>

            <div class="bg-white p-5">
                <div class="text-xs text-gray-500">{{ trans('Attributed revenue') }}</div>
                <div class="mt-1 text-2xl font-medium text-gray-800">{{ money(overview.totals.revenue) }}</div>
            </div>

            <div class="bg-white p-5">
                <div class="text-xs text-gray-500">{{ trans('Cost per customer') }}</div>
                <div class="mt-1 text-2xl font-medium text-gray-800">
                    {{ overview.totals.cac !== null ? money(overview.totals.cac) : '—' }}
                </div>
            </div>

            <div class="bg-white p-5">
                <div class="text-xs text-gray-500">{{ trans('Attributed customers') }}</div>
                <div class="mt-1 text-2xl font-medium text-gray-800">
                    {{ locale.number(overview.totals.registrations) }}
                    <span class="text-sm text-gray-400">· {{ locale.number(overview.totals.purchases) }} {{ trans('orders') }}</span>
                </div>
            </div>
        </div>

        <!-- Channel performance: spend vs revenue, one pair of bars per source -->
        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-800">{{ trans('Channel performance') }}</span>
                    <span class="ml-2 text-xs text-gray-400">{{ trans('all time, shop currency') }}</span>
                </div>
                <div class="flex items-center gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: REVENUE_COLOR }" />{{ trans('Revenue') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: SPEND_COLOR }" />{{ trans('Spend') }}
                    </span>
                </div>
            </div>

            <div v-if="overview.channels.length" class="mt-4 space-y-1">
                <Link v-for="channel in overview.channels" :key="channel.type"
                    :href="route(overview.traffic_sources_route.name, overview.traffic_sources_route.parameters)"
                    class="relative grid grid-cols-[8rem_1fr_4.5rem] md:grid-cols-[11rem_1fr_5rem] items-center gap-x-3 rounded-lg px-2 py-2 hover:bg-gray-50"
                    @mouseenter="hoveredChannel = channel.type" @mouseleave="hoveredChannel = null">

                    <div class="text-sm text-gray-700 truncate">{{ channel.name }}</div>

                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <div class="h-2.5 rounded-r bg-[#2a78d6] min-w-0"
                                :style="{ width: barWidth(channel.revenue) }" />
                            <span v-if="channel.revenue > 0"
                                class="text-xs text-gray-500 tabular-nums whitespace-nowrap">{{ money(channel.revenue) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="h-2.5 rounded-r bg-[#eb6834] min-w-0"
                                :style="{ width: barWidth(channel.spend) }" />
                            <span v-if="channel.spend > 0"
                                class="text-xs text-gray-500 tabular-nums whitespace-nowrap">{{ money(channel.spend) }}</span>
                        </div>
                    </div>

                    <div class="text-right text-sm tabular-nums"
                        :class="channel.roas === null ? 'text-gray-300' : channel.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                        {{ channel.roas !== null ? channel.roas.toFixed(2) + '×' : '—' }}
                    </div>

                    <div v-if="hoveredChannel === channel.type"
                        class="absolute left-2 -top-9 z-10 rounded-md bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg pointer-events-none whitespace-nowrap">
                        {{ channel.name }} · {{ locale.number(channel.registrations) }} {{ trans('customers') }}
                        · {{ trans('spend') }} {{ money(channel.spend) }} · {{ trans('revenue') }} {{ money(channel.revenue) }}
                    </div>
                </Link>
            </div>

            <div v-else class="mt-4 py-8 text-center">
                <div class="text-sm text-gray-500">{{ trans('No channel activity yet') }}</div>
                <div class="mt-1 text-xs text-gray-400">
                    {{ trans('Attribution fills this in as visitors register and ad spend is imported') }}
                </div>
            </div>
        </div>
    </div>
</template>
