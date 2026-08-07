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
import { router } from '@inertiajs/vue3'

const props = defineProps<{
    overview: {
        currency_code: string
        totals: {
            spend: number
            revenue: number
            pending: number
            registrations: number
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
        period_options: { value: string, label: string }[]
        referrers: {
            host: string
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
        traffic_sources_route: routeType
        mailshots_route: routeType
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
            visits: 0, orders: 0, spend: 0, pending: 0, revenue: 0, registrations: 0,
        }

        const g = groups[key]
        g.channels.push(channel)
        g.visits += channel.visits ?? 0
        g.orders += channel.orders ?? 0
        g.spend += channel.spend ?? 0
        g.pending += channel.pending ?? 0
        g.revenue += channel.revenue ?? 0
        g.registrations += channel.registrations ?? 0
    }

    return Object.values(groups).sort((a: any, b: any) => a.position - b.position)
})

const columnHelp: Record<string, string> = {
    visits: trans('People who arrived from this channel, how many of them bought, and the rate between the two. A storefront arrival is counted when the referrer names the channel; an email click is counted when it is clicked.'),
    spend: trans('Ad spend imported for this channel over the period. Email spend is estimated from the emails actually sent, at our per-message price, and marked est.'),
    awaiting: trans('Value of orders already placed but not invoiced yet. It moves into Revenue as invoices are raised, and drops if an order is cancelled.'),
    revenue: trans('Invoiced sales credited to this channel. Touched, not necessarily caused - a regular who was going to order anyway still counts if they arrived through it.'),
    registrations: trans('Customers who signed up after arriving through this channel. A red figure beside it is subscribers lost over the same emails, not subtracted from it.'),
    orders: trans('Orders placed after a touch from this channel, counted when the order is placed rather than when it ships.'),
    roas: trans('Revenue divided by spend. Blank while money is still awaiting invoice.'),
}

const count = (value: number) => Number.isInteger(value) ? value.toString() : value.toFixed(2)
const pctOf = (part: number, whole: number) => whole > 0 ? Math.round((part / whole) * 100) + '%' : '—'

const roasIsGood = computed(() => (props.overview.totals.roas ?? 0) >= 1)

/* Period lives in the URL so a filtered view can be shared, bookmarked and reloaded. */
const selectPeriod = (period: string) => router.get(
    window.location.pathname,
    { period },
    { preserveScroll: true, preserveState: true, replace: true }
)

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

        <!-- Period filter: one row above everything it governs -->
        <div class="flex flex-wrap items-center gap-1">
            <button v-for="option in overview.period_options" :key="option.value"
                type="button"
                class="px-2.5 py-1 text-xs rounded-md border transition-colors"
                :class="option.value === overview.period
                    ? 'bg-gray-800 text-white border-gray-800'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'"
                @click="selectPeriod(option.value)">
                {{ option.label }}
            </button>
            <span v-if="overview.from" class="ml-2 text-xs text-gray-400">
                {{ trans('since') }} {{ overview.from }}
            </span>
        </div>

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
                <div v-if="overview.totals.pending > 0" class="mt-0.5 text-xs text-amber-600">
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
                <div class="mt-1 text-2xl font-medium text-gray-800">
                    {{ fmtShare(overview.totals.registrations) }}
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

            <div v-if="overview.channels.length" class="mt-4 space-y-1">
                <Link v-for="channel in overview.channels" :key="channel.type"
                    :href="route(overview.traffic_sources_route.name, overview.traffic_sources_route.parameters)"
                    class="relative grid grid-cols-[8rem_1fr_4.5rem] md:grid-cols-[11rem_1fr_5rem] items-center gap-x-3 rounded-lg px-2 py-2 hover:bg-gray-50"
                    @mouseenter="hoveredChannel = channel.type" @mouseleave="hoveredChannel = null">

                    <div class="min-w-0">
                        <div class="text-sm text-gray-700 truncate">{{ channel.name }}</div>
                        <div v-if="channel.registrations > 0" class="text-xs text-gray-400 tabular-nums">
                            {{ fmtShare(channel.registrations) }} {{ trans('registrations') }}
                        </div>
                        <!-- Visits it sent against how many bought: people arrived and nobody ordered
                             is the case worth seeing, so it is the one in red. -->
                        <div v-if="channel.visits > 0" class="text-xs tabular-nums"
                             :class="channel.orders > 0 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                            {{ locale.number(channel.visits) }} {{ trans('visits') }} ·
                            {{ fmtShare(channel.orders ?? 0) }} {{ trans('bought') }}
                        </div>
                    </div>

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
                        {{ channel.name }} · {{ fmtShare(channel.registrations) }} {{ trans('registrations') }}
                        · {{ trans('spend') }} {{ money(channel.spend) }} · {{ trans('revenue') }} {{ money(channel.revenue) }}
                    </div>
                </Link>
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
                            <td class="text-right px-2 tabular-nums">{{ group.visits > 0 ? locale.number(group.visits) : '' }}</td>
                            <td class="text-right px-2 tabular-nums">{{ money(group.spend) }}</td>
                            <td class="text-right px-2 tabular-nums" :class="group.pending > 0 ? 'text-amber-700' : ''">
                                {{ group.pending > 0 ? money(group.pending) : '' }}
                            </td>
                            <td class="text-right px-2 tabular-nums">{{ money(group.revenue) }}</td>
                            <td class="text-right px-2 tabular-nums">{{ count(group.registrations) }}</td>
                            <td class="text-right px-2 tabular-nums">{{ count(group.orders) }}</td>
                            <td class="text-right pl-2 tabular-nums">
                                {{ group.spend > 0 && group.revenue > 0 ? (group.revenue / group.spend).toFixed(2) + '×' : '' }}
                            </td>
                        </tr>
                        <tr v-for="channel in (showChannelDetail ? group.channels : [])" :key="channel.type" class="border-b border-gray-50 text-gray-600">
                            <td class="py-2 pr-2 pl-5 text-gray-500">{{ channel.name }}</td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap"
                                :class="channel.visits > 0 && channel.orders === 0 ? 'text-[#d03b3b]' : ''">
                                <template v-if="channel.visits > 0">
                                    {{ locale.number(channel.visits) }}
                                    <span class="text-xs" :class="channel.orders > 0 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                                        · {{ count(channel.orders) }} {{ trans('bought') }} · {{ pctOf(channel.orders, channel.visits) }}
                                    </span>
                                </template>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="text-right px-2 tabular-nums">
                                <span v-if="channel.spend_is_estimated" class="text-xs text-gray-400 mr-1"
                                      :title="trans('Estimated from emails sent')">{{ trans('est.') }}</span>{{ money(channel.spend) }}
                            </td>
                            <td class="text-right px-2 tabular-nums" :class="channel.pending > 0 ? 'text-amber-600' : 'text-gray-300'">
                                {{ money(channel.pending) }}
                            </td>
                            <td class="text-right px-2 tabular-nums">{{ money(channel.revenue) }}</td>
                            <td class="text-right px-2 tabular-nums whitespace-nowrap">
                                {{ count(channel.registrations) }}<span v-if="channel.unsubscribed > 0" class="text-[#d03b3b]"> −{{ locale.number(channel.unsubscribed) }}</span>
                            </td>
                            <td class="text-right px-2 tabular-nums">{{ count(channel.orders) }}</td>
                            <td class="text-right pl-2 tabular-nums"
                                :class="channel.roas === null ? 'text-gray-300' : channel.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                                {{ channel.roas !== null ? channel.roas.toFixed(2) + '×' : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="mt-4 py-8 text-center">
                <div class="text-sm text-gray-500">{{ trans('No channel activity yet') }}</div>
                <div class="mt-1 text-xs text-gray-400">
                    {{ trans('Attribution fills this in as visitors register and ad spend is imported') }}
                </div>
            </div>
        </div>

        <!-- Campaign performance: which individual campaigns earn their spend -->
        <div v-if="overview.campaigns.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
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
                        <td class="text-right px-2 tabular-nums">{{ fmtShare(campaign.registrations) }}</td>
                        <td class="text-right pl-2 tabular-nums"
                            :class="campaign.roas === null ? 'text-gray-300' : campaign.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                            {{ campaign.roas !== null ? campaign.roas.toFixed(2) + '×' : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Top referrers: the sites sending people here, invisible before the referral channel existed -->
        <div v-if="overview.referrers?.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-800">{{ trans('Top referrers') }}</span>
                    <span class="ml-2 text-xs text-gray-400">{{ overview.period_label.toLowerCase() }}</span>
                </div>
            </div>

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
                        <td class="py-2 pr-2 text-gray-700 truncate max-w-[18rem]">{{ referrer.host }}</td>
                        <td class="text-right px-2 tabular-nums">{{ fmtShare(referrer.visitors) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ fmtShare(referrer.registrations) }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ money(referrer.revenue) }}</td>
                    </tr>
                </tbody>
            </table>
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
