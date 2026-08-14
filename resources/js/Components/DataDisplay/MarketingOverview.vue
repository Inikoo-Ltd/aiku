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
            pending: number
            registrations: number
            unsubscribed: number
            invoices: number
            roas: number | null
            cac: number | null
        }
        baseline: {
            registrations: number
            orders: number
            revenue: number
        }
        channels: {
            name: string
            type: string
            route: routeType
            registrations_route: routeType
            orders_route: routeType
            group: string
            group_label: string
            group_position: number
            unsubscribed: number
            orders: number
            spend: number
            spend_is_estimated?: boolean
            visits: number
            orders: number
            revenue: number
            registrations: number
            roas: number | null
        }[]
        period: string
        period_label: string
        from: string | null
        to: string | null
        referrers: {
            host: string
            kind: 'site' | 'search'
            visitors: number
            registrations: number
            revenue: number
        }[]
        campaigns: {
            name: string
            channel: string
            spend: number
            revenue: number
            registrations: number
            roas: number | null
        }[]
        spend_by_day: { date: string, amount: number }[]
        email: {
            totals: {
                sent: number
                opened: number
                clicked: number
                unsubscribed: number
                estimated_cost: number
                attributed_revenue: number
                attributed_customers: number
            }
            mailshots: {
                id: number
                subject: string
                type: string
                sent_at: string | null
                sent: number
                opened: number
                clicked: number
                unsubscribed: number
                estimated_cost: number
                attributed_revenue: number
                attributed_customers: number
                prospects_registered: number
            }[]
        }
        mailshots_route: routeType
    }
}>()

const locale = useLocaleStore()
const money = (value: number) => locale.currencyFormat(props.overview.currency_code, value)

/* Revenue is the accent, spend the neutral reference it is read against. */
const REVENUE_COLOR = '#006300'
const SPEND_COLOR = '#6b7280'
const BAR_TRACK_COLOR = '#f1f1ef'

const maxBarValue = computed(() =>
    Math.max(1, ...props.overview.channels.flatMap(c => [c.spend, c.revenue]))
)
const barWidth = (value: number) => `${Math.max(value > 0 ? 1.5 : 0, (value / maxBarValue.value) * 100)}%`

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

/* The same grouping and the same explanations the org and group dashboards carry: a shop manager
   reading this screen should not have to learn a second vocabulary to compare with the level above. */
/* Same toggle as the organisation and group screens. */
const showChannelDetail = ref(true)

const groupedChannels = computed(() => {
    const groups: Record<string, any> = {}

    for (const channel of props.overview.channels) {
        const key = channel.group ?? 'other'

        groups[key] ??= {
            key,
            label: channel.group_label ?? key,
            position: channel.group_position ?? 9,
            channels: [],
            visits: 0, orders: 0, spend: 0, pending: 0, revenue: 0, registrations: 0, unsubscribed: 0,
        }

        const g = groups[key]
        g.channels.push(channel)
        g.visits += channel.visits ?? 0
        g.orders += channel.orders ?? 0
        g.spend += channel.spend ?? 0
        g.pending += channel.pending ?? 0
        g.revenue += channel.revenue ?? 0
        g.registrations += channel.registrations ?? 0
        g.unsubscribed += channel.unsubscribed ?? 0
    }

    return Object.values(groups).sort((a: any, b: any) => a.position - b.position)
})

const columnHelp: Record<string, string> = {
    visits: trans('Arrivals from this channel, how many of them bought, and the rate between the two. Not unique people: each browser counts once per channel per day, so the same person on two days, or on phone and laptop, counts each time - the same way the ad platforms count their clicks. A storefront arrival is counted when the referrer names the channel; an email click is counted when it is clicked.'),
    spend: trans('Ad spend imported for this channel over the period. Email spend is estimated from the emails actually sent, at our per-message price, and marked est.'),
    awaiting: trans('Value of orders already placed but not invoiced yet. It moves into Revenue as invoices are raised, and drops if an order is cancelled.'),
    revenue: trans('Invoiced sales credited to this channel. Touched, not necessarily caused - a regular who was going to order anyway still counts if they arrived through it.'),
    registrations: trans('Customers who signed up after arriving through this channel. A red figure beside it is subscribers lost over the same emails, not subtracted from it.'),
    orders: trans('Orders placed after a touch from this channel, counted when the order is placed rather than when it ships.'),
    roas: trans('Revenue divided by spend. Blank while money is still awaiting invoice.'),
}

/* A column with one fractional figure in it carries the decimals on every figure: 2 beside 34.83
   reads as a different kind of number, when it is the same count arrived at without a split order. */
const count = (value: number, decimals = false) =>
    decimals || !Number.isInteger(value) ? value.toFixed(2) : value.toString()

const hasDecimals = (values: number[]) => values.some(value => !Number.isInteger(value))

const unsubscribedHelp = trans('People who left our mailing lists over the same period. Shown beside the sign-ups rather than taken off them: an unsubscribe costs permission to email somebody, not the customer, and a mailshot that wins ten sign-ups while losing fifty subscribers is not a mailshot that won ten.')
const pctOf = (part: number, whole: number) => whole > 0 ? Math.round((part / whole) * 100) + '%' : '—'

/* Kept to two decimals: share-weighted orders against visits rounds to 0% below half a percent,
   which reads as nobody having bought when somebody did. */
const conversionRate = (orders: number, visits: number) =>
    visits > 0 ? (orders / visits * 100).toFixed(2) + '%' : '—'
const netRegistrations = (registrations: number, unsubscribed: number, decimals = false) =>
    count(registrations - unsubscribed, decimals).replace('-', '−')

const netRegistrationsHelp = computed(() =>
    count(props.overview.totals.registrations) + ' ' + trans('sign-ups') + ' − '
    + count(props.overview.totals.unsubscribed ?? 0) + ' ' + trans('unsubscribed') + ' = '
    + netRegistrations(props.overview.totals.registrations, props.overview.totals.unsubscribed ?? 0)
    + '. ' + unsubscribedHelp)

/* Summed from the groups above rather than read off the KPI row, so the last row always adds up to
   the rows a reader can see. */
const channelTotals = computed(() => groupedChannels.value.reduce((totals: any, group: any) => ({
    visits: totals.visits + group.visits,
    spend: totals.spend + group.spend,
    pending: totals.pending + group.pending,
    revenue: totals.revenue + group.revenue,
    registrations: totals.registrations + group.registrations,
    unsubscribed: totals.unsubscribed + group.unsubscribed,
    orders: totals.orders + group.orders,
}), { visits: 0, spend: 0, pending: 0, revenue: 0, registrations: 0, unsubscribed: 0, orders: 0 }))

const decimalColumns = computed(() => ({
    registrations         : hasDecimals([...props.overview.channels.map(channel => channel.registrations), channelTotals.value.registrations]),
    orders                : hasDecimals([...props.overview.channels.map(channel => channel.orders), channelTotals.value.orders]),
    campaignRegistrations : hasDecimals(props.overview.campaigns.map(campaign => campaign.registrations)),
    referrerVisitors      : hasDecimals((props.overview.referrers ?? []).map(referrer => referrer.visitors)),
    referrerRegistrations : hasDecimals((props.overview.referrers ?? []).map(referrer => referrer.registrations)),
}))

const roasIsGood = computed(() => (props.overview.totals.roas ?? 0) >= 1)

const pct = (part: number, whole: number) => whole > 0 ? `${((part / whole) * 100).toFixed(1)}%` : '—'

/* Registrations are share-weighted, so a channel can legitimately hold 12.5 of them. */
const fmtShare = (value: number) => Number.isInteger(value) ? locale.number(value) : value.toFixed(1)

const typeLabel: Record<string, string> = {
    newsletter: trans('Newsletter'),
    marketing: trans('Mailshot'),
    invite: trans('Prospects'),
}
</script>

<template>
    <div class="px-4 py-5 md:px-6 space-y-6">

        <p v-if="overview.from" class="text-xs text-gray-400">
            {{ trans('measured since') }} {{ overview.from }}<span v-if="overview.to"> {{ trans('to') }} {{ overview.to }}</span>
        </p>

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
                <div v-if="sparkline" class="mt-0.5 text-xs text-gray-400">{{ trans('daily, recent') }}</div>
            </div>

            <div class="bg-white p-5">
                <div class="text-xs text-gray-500">{{ trans('Revenue marketing touched') }}</div>
                <div class="mt-1 text-2xl font-medium text-gray-800">{{ money(overview.totals.revenue) }}</div>
                <div class="mt-0.5 text-xs text-gray-400">
                    {{ trans('of') }} {{ money(overview.baseline?.revenue ?? 0) }} {{ trans('taken in total') }}
                </div>
                <!-- Invoicing runs a day or two behind orders; this is what today's marketing already sold -->
                <div v-if="overview.totals.pending > 0" class="mt-0.5 text-xs text-[#006300]">
                    + {{ money(overview.totals.pending) }} {{ trans('placed, awaiting invoice') }}
                </div>
            </div>

            <div class="bg-white p-5">
                <div class="text-xs text-gray-500">{{ trans('Cost per customer') }}</div>
                <div class="mt-1 text-2xl font-medium text-gray-800">
                    {{ overview.totals.cac !== null ? money(overview.totals.cac) : '—' }}
                </div>
            </div>

            <div class="bg-white p-5">
                <div class="text-xs text-gray-500">{{ trans('New customers marketing touched') }}</div>
                <div class="mt-1 text-2xl font-medium text-gray-800 flex items-baseline gap-1.5">
                    <span>{{ fmtShare(overview.totals.registrations) }}</span>
                    <template v-if="(overview.totals.unsubscribed ?? 0) > 0">
                        <span v-tooltip="unsubscribedHelp" class="text-[#d03b3b] cursor-help">
                            − {{ locale.number(overview.totals.unsubscribed) }}
                        </span>
                        <span class="text-gray-300">=</span>
                        <span v-tooltip="netRegistrationsHelp" class="cursor-help"
                              :class="overview.totals.registrations - overview.totals.unsubscribed < 0 ? 'text-[#d03b3b]' : 'text-gray-600'">
                            {{ netRegistrations(overview.totals.registrations, overview.totals.unsubscribed) }}
                        </span>
                    </template>
                    <span class="text-sm text-gray-400">{{ trans('of') }} {{ overview.baseline?.registrations ?? 0 }}</span>
                </div>
                <!-- Sign-ups nobody in marketing can claim: the trade that arrives regardless -->
                <div v-if="(overview.baseline?.registrations ?? 0) > 0 && overview.totals.registrations === 0"
                     class="mt-0.5 text-xs text-[#d03b3b]">
                    {{ trans('none of this period\'s sign-ups came through marketing') }}
                </div>
            </div>
        </div>

        <!-- Channel performance: spend vs revenue, one pair of bars per source -->
        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-800">{{ trans('Channel performance') }}</span>
                    <span class="ml-2 text-xs text-gray-400">{{ overview.period_label.toLowerCase() }}, {{ overview.currency_code }}</span>
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

            <div v-if="overview.channels.length" class="mt-4">
                <Link v-for="channel in overview.channels" :key="channel.type"
                    :href="route(channel.route.name, channel.route.parameters)"
                    class="relative grid grid-cols-[7rem_minmax(0,1fr)_5.5rem_3.5rem] md:grid-cols-[11rem_minmax(0,1fr)_7rem_4rem] items-center gap-x-3 rounded-lg px-2 py-2.5 hover:bg-gray-50"
                    @mouseenter="hoveredChannel = channel.type" @mouseleave="hoveredChannel = null">

                    <div class="min-w-0">
                        <div class="text-sm text-gray-700 truncate">{{ channel.name }}</div>
                        <div v-if="channel.registrations > 0" class="text-xs text-gray-400 tabular-nums">
                            {{ fmtShare(channel.registrations) }} {{ trans('registrations') }}
                        </div>
                        <div v-if="channel.visits > 0" class="text-xs tabular-nums"
                             :class="channel.orders > 0 ? 'text-[#006300]' : 'text-gray-400'">
                            {{ locale.number(channel.visits) }} {{ trans('visits') }} ·
                            {{ fmtShare(channel.orders ?? 0) }} {{ trans('bought') }}
                        </div>
                    </div>

                    <!-- Both bars grow from the same baseline on one shared scale, so the pair can be
                         read against each other and across channels without an axis. -->
                    <div class="space-y-1">
                        <div class="h-4 flex items-center">
                            <div class="relative h-2 w-full rounded-r-[3px]" :style="{ background: BAR_TRACK_COLOR }">
                                <div class="absolute inset-y-0 left-0 rounded-r-[3px]"
                                    :style="{ width: barWidth(channel.revenue), background: REVENUE_COLOR }" />
                            </div>
                        </div>
                        <div class="h-4 flex items-center">
                            <div class="relative h-2 w-full rounded-r-[3px]" :style="{ background: BAR_TRACK_COLOR }">
                                <div class="absolute inset-y-0 left-0 rounded-r-[3px]"
                                    :style="{ width: barWidth(channel.spend), background: SPEND_COLOR }" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs tabular-nums whitespace-nowrap">
                        <div class="h-4 flex items-center justify-end"
                            :class="channel.revenue > 0 ? 'text-gray-700' : 'text-gray-300'">
                            {{ channel.revenue > 0 ? money(channel.revenue) : '—' }}
                        </div>
                        <div class="h-4 flex items-center justify-end"
                            :class="channel.spend > 0 ? 'text-gray-500' : 'text-gray-300'">
                            {{ channel.spend > 0 ? money(channel.spend) : '—' }}
                        </div>
                    </div>

                    <div class="text-right text-sm tabular-nums"
                        :class="channel.roas === null ? 'text-gray-300' : channel.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                        {{ channel.roas !== null ? channel.roas.toFixed(2) + '×' : '—' }}
                    </div>

                    <!-- Opens downward: anchored above, the first row's tooltip fell outside the card. -->
                    <div v-if="hoveredChannel === channel.type"
                        class="absolute left-2 top-full z-20 -mt-1 rounded-md bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg pointer-events-none whitespace-nowrap">
                        {{ channel.name }} · {{ fmtShare(channel.registrations) }} {{ trans('registrations') }}
                        · {{ trans('spend') }} {{ money(channel.spend) }} · {{ trans('revenue') }} {{ money(channel.revenue) }}
                    </div>
                </Link>

                <p class="mt-2 px-2 text-xs text-gray-400">
                    {{ trans('One shared scale across every channel; the widest bar is') }} {{ money(maxBarValue) }}
                </p>
            </div>

            <!-- The detail under the bars: same table, same wording and same tooltips as the
                 organisation and group dashboards, so the levels can be read against each other. -->
            <div v-if="overview.channels.length" class="mt-5">
                <div class="flex justify-end">
                    <button type="button" @click="showChannelDetail = !showChannelDetail"
                            class="text-xs text-gray-500 hover:text-gray-800 border border-gray-200 rounded-md px-2 py-1">
                        {{ showChannelDetail ? trans('Collapse') : trans('Expand') }}
                    </button>
                </div>
                <table class="mt-2 w-full text-xs overflow-x-auto">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100">
                            <th class="text-left font-normal py-1.5 pr-2">{{ trans('Channel') }}</th>
                            <th class="text-right font-normal py-1.5 px-2">{{ trans('Visits') }}<sup v-tooltip="columnHelp.visits" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                            <th class="text-right font-normal py-1.5 px-2">{{ trans('Spend') }}<sup v-tooltip="columnHelp.spend" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                            <th class="text-right font-normal py-1.5 px-2">{{ trans('Awaiting invoice') }}<sup v-tooltip="columnHelp.awaiting" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                            <th class="text-right font-normal py-1.5 px-2">{{ trans('Revenue') }}<sup v-tooltip="columnHelp.revenue" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                            <th class="text-right font-normal py-1.5 px-2">{{ trans('Registrations') }}<sup v-tooltip="columnHelp.registrations" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                            <th class="text-right font-normal py-1.5 px-2">{{ trans('Orders') }}<sup v-tooltip="columnHelp.orders" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                            <th class="text-right font-normal py-1.5 pl-2">{{ trans('ROAS') }}<sup v-tooltip="columnHelp.roas" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                        </tr>
                    </thead>
                    <tbody v-for="group in groupedChannels" :key="group.key">
                        <tr class="text-gray-900 bg-gray-100/80 border-t-2 border-b border-gray-300 font-medium leading-tight">
                            <td class="py-1 pr-2 text-xs leading-tight">{{ group.label }}</td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid grid-cols-[3.5rem_6.5rem_2.75rem]">
                                    <span>{{ group.visits > 0 ? locale.number(group.visits) : '' }}</span>
                                    <span class="text-xs font-normal" :class="group.orders > 0 ? 'text-[#006300]' : 'text-gray-500'">
                                        <template v-if="group.visits > 0">{{ count(group.orders, decimalColumns.orders) }} {{ trans('bought') }}</template>
                                    </span>
                                    <span class="text-xs font-normal" :class="group.orders > 0 ? 'text-[#006300]' : 'text-gray-500'">
                                        <template v-if="group.visits > 0">{{ conversionRate(group.orders, group.visits) }}</template>
                                    </span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid" :class="showChannelDetail ? '' : 'grid-cols-[5rem_2.75rem]'">
                                    <span>{{ money(group.spend) }}</span>
                                    <span v-if="!showChannelDetail" class="font-normal text-gray-400">{{ pctOf(group.spend, channelTotals.spend) }}</span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap text-gray-500">
                                <span class="inline-grid" :class="showChannelDetail ? '' : 'grid-cols-[5.5rem_2.75rem]'">
                                    <span>{{ group.pending > 0 ? money(group.pending) : '' }}</span>
                                    <span v-if="!showChannelDetail && group.pending > 0" class="font-normal text-gray-400">{{ pctOf(group.pending, channelTotals.pending) }}</span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid" :class="showChannelDetail ? '' : 'grid-cols-[5.5rem_2.75rem]'">
                                    <span>{{ money(group.revenue) }}</span>
                                    <span v-if="!showChannelDetail" class="font-normal text-gray-400">{{ pctOf(group.revenue, channelTotals.revenue) }}</span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap"
                                :class="group.registrations - group.unsubscribed < 0 ? 'text-[#d03b3b]' : ''">
                                <span class="inline-grid grid-cols-[3.5rem_2.75rem]">
                                    <span>{{ netRegistrations(group.registrations, group.unsubscribed, decimalColumns.registrations) }}</span>
                                    <span></span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid" :class="showChannelDetail ? '' : 'grid-cols-[3.5rem_2.75rem]'">
                                    <span>{{ count(group.orders, decimalColumns.orders) }}</span>
                                    <span v-if="!showChannelDetail" class="font-normal text-gray-400">{{ pctOf(group.orders, channelTotals.orders) }}</span>
                                </span>
                            </td>
                            <td class="text-right pl-2 tabular-nums">
                                {{ group.spend > 0 && group.revenue > 0 ? (group.revenue / group.spend).toFixed(2) + '×' : '' }}
                            </td>
                        </tr>
                        <tr v-for="channel in (showChannelDetail ? group.channels : [])" :key="channel.type" class="border-b border-gray-50 text-gray-600">
                            <td class="py-2 pr-2 pl-5">
                                <Link :href="route(channel.route.name, channel.route.parameters)"
                                      class="text-gray-500 hover:text-gray-900 hover:underline">{{ channel.name }}</Link>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid grid-cols-[3.5rem_6.5rem_2.75rem]">
                                    <span :class="channel.visits > 0 ? '' : 'text-gray-300'">
                                        {{ channel.visits > 0 ? locale.number(channel.visits) : '—' }}
                                    </span>
                                    <span class="text-xs" :class="channel.orders > 0 ? 'text-[#006300]' : ''">
                                        <template v-if="channel.visits > 0">{{ count(channel.orders, decimalColumns.orders) }} {{ trans('bought') }}</template>
                                    </span>
                                    <span class="text-xs" :class="channel.orders > 0 ? 'text-[#006300]' : ''">
                                        <template v-if="channel.visits > 0">{{ conversionRate(channel.orders, channel.visits) }}</template>
                                    </span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span v-if="channel.spend_is_estimated" class="text-xs text-gray-400 mr-1"
                                      :title="trans('Estimated from emails sent')">{{ trans('est.') }}</span>{{ money(channel.spend) }}
                            </td>
                            <td class="text-right px-2 tabular-nums" :class="channel.pending > 0 ? 'text-gray-400' : 'text-gray-300'">
                                {{ money(channel.pending) }}
                            </td>
                            <td class="text-right px-2 tabular-nums">{{ money(channel.revenue) }}</td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid grid-cols-[3.5rem_2.75rem]">
                                    <span>
                                        <Link v-if="channel.registrations > 0"
                                              :href="route(channel.registrations_route.name, channel.registrations_route.parameters)"
                                              class="hover:text-gray-900 hover:underline">{{ count(channel.registrations, decimalColumns.registrations) }}</Link>
                                        <template v-else>{{ count(channel.registrations, decimalColumns.registrations) }}</template>
                                    </span>
                                    <span class="text-[#d03b3b]">
                                        <template v-if="channel.unsubscribed > 0">−{{ count(channel.unsubscribed, true) }}</template>
                                    </span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums">
                                <Link v-if="channel.orders > 0"
                                      :href="route(channel.orders_route.name, channel.orders_route.parameters)"
                                      class="hover:text-gray-900 hover:underline">{{ count(channel.orders, decimalColumns.orders) }}</Link>
                                <template v-else>{{ count(channel.orders, decimalColumns.orders) }}</template>
                            </td>
                            <td class="text-right pl-2 tabular-nums"
                                :class="channel.roas === null ? 'text-gray-300' : channel.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                                {{ channel.roas !== null ? channel.roas.toFixed(2) + '×' : '—' }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-gray-900 border-t-2 border-gray-400 font-semibold">
                            <td class="py-1.5 pr-2">{{ trans('All channels') }}</td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid grid-cols-[3.5rem_6.5rem_2.75rem]">
                                    <span>{{ locale.number(channelTotals.visits) }}</span>
                                    <span class="text-xs font-normal" :class="channelTotals.orders > 0 ? 'text-[#006300]' : 'text-gray-500'">
                                        {{ count(channelTotals.orders, decimalColumns.orders) }} {{ trans('bought') }}
                                    </span>
                                    <span class="text-xs font-normal" :class="channelTotals.orders > 0 ? 'text-[#006300]' : 'text-gray-500'">
                                        {{ conversionRate(channelTotals.orders, channelTotals.visits) }}
                                    </span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid" :class="showChannelDetail ? '' : 'grid-cols-[5rem_2.75rem]'">
                                    <span>{{ money(channelTotals.spend) }}</span>
                                    <span v-if="!showChannelDetail"></span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap text-gray-500">
                                <span class="inline-grid" :class="showChannelDetail ? '' : 'grid-cols-[5.5rem_2.75rem]'">
                                    <span>{{ money(channelTotals.pending) }}</span>
                                    <span v-if="!showChannelDetail"></span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid" :class="showChannelDetail ? '' : 'grid-cols-[5.5rem_2.75rem]'">
                                    <span>{{ money(channelTotals.revenue) }}</span>
                                    <span v-if="!showChannelDetail"></span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap"
                                :class="channelTotals.registrations - channelTotals.unsubscribed < 0 ? 'text-[#d03b3b]' : ''">
                                <span class="inline-grid grid-cols-[3.5rem_2.75rem]">
                                    <span>{{ netRegistrations(channelTotals.registrations, channelTotals.unsubscribed, decimalColumns.registrations) }}</span>
                                    <span></span>
                                </span>
                            </td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                <span class="inline-grid" :class="showChannelDetail ? '' : 'grid-cols-[3.5rem_2.75rem]'">
                                    <span>{{ count(channelTotals.orders, decimalColumns.orders) }}</span>
                                    <span v-if="!showChannelDetail"></span>
                                </span>
                            </td>
                            <td class="text-right pl-2 tabular-nums">
                                {{ channelTotals.spend > 0 && channelTotals.revenue > 0 ? (channelTotals.revenue / channelTotals.spend).toFixed(2) + '×' : '' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-else class="mt-4 py-8 text-center">
                <div class="text-sm text-gray-500">{{ trans('No channel activity yet') }}</div>
                <div class="mt-1 text-xs text-gray-400">
                    {{ trans('Attribution fills this in as visitors register and ad spend is imported') }}
                </div>
            </div>
        </div>

        <!-- Side by side while there is room for both, stacked once there is not -->
        <div class="flex flex-col xl:flex-row gap-6 items-start">

        <!-- Campaign performance: which individual campaigns earn their spend -->
        <div v-if="overview.campaigns.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5 flex-1 w-full min-w-0">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-800">{{ trans('Campaign performance') }}</span>
                    <span class="ml-2 text-xs text-gray-400">{{ overview.period_label.toLowerCase() }}</span>
                </div>
            </div>

            <table class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Campaign') }}</th>
                        <th class="text-left font-normal py-1.5 px-2">{{ trans('Channel') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Spend') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Revenue') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Registrations') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('ROAS') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="campaign in overview.campaigns" :key="campaign.name + campaign.channel"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2 text-gray-700 truncate max-w-[18rem]">{{ campaign.name }}</td>
                        <td class="px-2 text-gray-500">{{ campaign.channel }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(campaign.spend) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(campaign.revenue) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ count(campaign.registrations, decimalColumns.campaignRegistrations) }}</td>
                        <td class="text-right pl-2 tabular-nums"
                            :class="campaign.roas === null ? 'text-gray-300' : campaign.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                            {{ campaign.roas !== null ? campaign.roas.toFixed(2) + '×' : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Who sends us people: the sites linking to us and the search engines finding us -->
        <div v-if="overview.referrers?.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5 flex-1 w-full min-w-0">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-800">{{ trans('Who sends us people') }}</span>
                    <span class="ml-2 text-xs text-gray-400">{{ overview.period_label.toLowerCase() }}</span>
                </div>
            </div>
            <p class="mt-1 text-xs text-gray-400">
                {{ trans('Sites linking to us and search engines finding us. A search engine sending people is the case for advertising on it.') }}
            </p>

            <table class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Site') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Visitors') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Registrations') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Revenue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="referrer in overview.referrers" :key="referrer.host"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2 text-gray-700 truncate max-w-[18rem]">
                            {{ referrer.host }}
                            <span v-if="referrer.kind === 'search'" class="text-gray-400">{{ trans('search') }}</span>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ count(referrer.visitors, decimalColumns.referrerVisitors) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ count(referrer.registrations, decimalColumns.referrerRegistrations) }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ money(referrer.revenue) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        </div>

        <!-- Email marketing: does what we send earn sales, or just unsubscribes? -->
        <div v-if="overview.email" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-800">{{ trans('Email marketing') }}</span>
                    <span class="ml-2 text-xs text-gray-400">{{ trans('sent') }} {{ overview.period_label.toLowerCase() }} · {{ trans('cost estimated from SES') }}</span>
                </div>
                <Link :href="route(overview.mailshots_route.name, overview.mailshots_route.parameters)"
                    class="text-xs text-gray-500 hover:text-gray-800">{{ trans('All mailshots') }} →</Link>
            </div>

            <div class="mt-4 flex flex-wrap gap-x-8 gap-y-2">
                <div>
                    <span class="text-xs text-gray-500">{{ trans('Sent') }}</span>
                    <div class="text-sm text-gray-800 tabular-nums">{{ locale.number(overview.email.totals.sent) }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500">{{ trans('Opened') }}</span>
                    <div class="text-sm text-gray-800 tabular-nums">{{ pct(overview.email.totals.opened, overview.email.totals.sent) }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500">{{ trans('Clicked') }}</span>
                    <div class="text-sm text-gray-800 tabular-nums">{{ pct(overview.email.totals.clicked, overview.email.totals.sent) }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500">{{ trans('Unsubscribed') }}</span>
                    <div class="text-sm text-gray-800 tabular-nums">{{ locale.number(overview.email.totals.unsubscribed) }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500">{{ trans('Est. cost') }}</span>
                    <div class="text-sm text-gray-800 tabular-nums">{{ money(overview.email.totals.estimated_cost) }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500">{{ trans('Revenue marketing touched') }}</span>
                    <div class="text-sm tabular-nums"
                        :class="overview.email.totals.attributed_revenue >= overview.email.totals.estimated_cost ? 'text-[#006300]' : 'text-[#d03b3b]'">
                        {{ money(overview.email.totals.attributed_revenue) }}
                    </div>
                </div>
            </div>

            <table v-if="overview.email.mailshots.length" class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Mailshot') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Sent') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Opened') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Clicked') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Unsub') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Est. cost') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="mailshot in overview.email.mailshots" :key="mailshot.id"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="truncate max-w-[16rem] text-gray-700">{{ mailshot.subject }}</span>
                                <span class="shrink-0 rounded px-1.5 py-px bg-gray-100 text-gray-500">{{ typeLabel[mailshot.type] ?? mailshot.type }}</span>
                            </div>
                            <div v-if="mailshot.sent_at" class="text-gray-400">{{ mailshot.sent_at }}</div>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ locale.number(mailshot.sent) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ pct(mailshot.opened, mailshot.sent) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ pct(mailshot.clicked, mailshot.sent) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ mailshot.unsubscribed > 0 ? locale.number(mailshot.unsubscribed) : '—' }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(mailshot.estimated_cost) }}</td>
                        <td class="text-right pl-2 tabular-nums whitespace-nowrap">
                            <template v-if="mailshot.type === 'invite'">
                                {{ mailshot.prospects_registered }} {{ trans('registered') }}
                            </template>
                            <template v-else-if="mailshot.attributed_revenue > 0">
                                {{ money(mailshot.attributed_revenue) }}
                            </template>
                            <template v-else>
                                <span class="text-gray-300">—</span>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-else class="mt-4 py-6 text-center text-xs text-gray-400">
                {{ trans('No mailshots sent yet') }}
            </div>
        </div>
    </div>
</template>
