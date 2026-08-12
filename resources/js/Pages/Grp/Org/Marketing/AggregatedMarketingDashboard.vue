<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 07 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from '@/Composables/useFormatTime'
import { route } from 'ziggy-js'
import { PageHeadingTypes } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import { Intervals, Settings } from '@/types/Components/Dashboard'
import DashboardSettings from '@/Components/DataDisplay/Dashboard/DashboardSettings.vue'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    intervals: Intervals
    settings: Settings
    overview: {
        scope: 'group' | 'organisation'
        children_label: string
        currency_code: string
        period: string
        period_label: string
        totals: {
            spend: number
            spend_ads: number
            spend_email: number
            revenue: number
            pending: number
            registrations: number
            unsubscribed: number
            orders: number
            roas: number | null
            cac: number | null
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
            spend: number
            spend_is_estimated?: boolean
            unsubscribed: number
            visits: number
            revenue: number
            pending: number
            registrations: number
            orders: number
            roas: number | null
        }[]
        attribution_started_at: string | null
        from: string | null
        to: string | null
        referrers: {
            host: string
            kind: 'site' | 'search'
            visitors: number
            revenue: number
        }[]
        baseline: {
            registrations: number
            orders: number
            revenue: number
        }
        children: {
            name: string
            slug: string
            revenue: number
            pending: number
            revenue_total: number
            registrations: number
            registrations_total: number
            orders: number
            orders_total: number
            top_channel: string | null
            route: { name: string, parameters: string[] }
        }[]
    }
}>()

const locale = useLocaleStore()

const money = (value: number) => locale.currencyFormat(props.overview.currency_code, value)
/* A column with one fractional figure in it carries the decimals on every figure: 2 beside 34.83
   reads as a different kind of number, when it is the same count arrived at without a split order. */
const count = (value: number, decimals = false) =>
    decimals || !Number.isInteger(value) ? value.toFixed(2) : value.toString()

const hasDecimals = (values: number[]) => values.some(value => !Number.isInteger(value))

/* The share of all trade that marketing can claim. Without it, "0 registrations" reads as a quiet
   period rather than as every ad and mailshot having earned nobody. */
/* Only a warning when the period actually reaches back further than we were recording. Selecting a
   period that starts after capture began is a complete picture, and flagging it would train people to
   ignore the notice. */
const periodPredatesAttribution = computed(() => {
    if (!props.overview.attribution_started_at) return false
    if (!props.overview.from) return true

    return new Date(props.overview.from) < new Date(props.overview.attribution_started_at)
})

/* Sent as ISO8601 with its offset, so this renders in the reader's own timezone rather than in
   whatever the server happens to run on - and says which timezone that is. */
const attributionStarted = computed(() =>
    props.overview.attribution_started_at
        ? useFormatTime(props.overview.attribution_started_at, { formatTime: 'aiku' })
        : ''
)

/* The label says the window the figures actually cover. "last 30 days" is a lie while attribution has
   only been recording since this morning, and it is the lie that made the numbers look like failure
   rather than like a short history. */
const measuredSince = computed(() => {
    const startedAt = props.overview.attribution_started_at
    const from = props.overview.from

    const effective = periodPredatesAttribution.value ? startedAt : from

    return effective
        ? trans('since') + ' ' + useFormatTime(effective, { formatTime: 'aiku' })
        : props.overview.period_label.toLowerCase()
})

/* Nineteen channel rows is a list nobody reads. Grouped, the table answers the question people
   actually arrive with: did the paid stuff work, is search bringing anyone, is email carrying us.
   Each group carries its own totals, so the summary is readable without the detail. */
/* Collapsed shows four group totals and nothing else, which is the whole table for anybody who only
   wants to know whether paid worked. Expanded is the default because the detail is why the screen
   exists; the toggle is for reading it, not for hiding it. */
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

const share = (part: number, whole: number) =>
    whole > 0 ? Math.round((part / whole) * 100) + '%' : '—'

const unsubscribedHelp = trans('People who left our mailing lists over the same period. Shown beside the sign-ups rather than taken off them: an unsubscribe costs permission to email somebody, not the customer, and a mailshot that wins ten sign-ups while losing fifty subscribers is not a mailshot that won ten.')

const netRegistrations = (registrations: number, unsubscribed: number, decimals = false) =>
    count(registrations - unsubscribed, decimals).replace('-', '−')

const netRegistrationsHelp = computed(() =>
    count(props.overview.totals.registrations) + ' ' + trans('sign-ups') + ' − '
    + count(props.overview.totals.unsubscribed) + ' ' + trans('unsubscribed') + ' = '
    + netRegistrations(props.overview.totals.registrations, props.overview.totals.unsubscribed)
    + '. ' + unsubscribedHelp)

/* Summed from the groups above rather than read off the totals card, so the last row always adds up
   to the rows a reader can see. */
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
    registrations     : hasDecimals([...props.overview.channels.map(channel => channel.registrations), channelTotals.value.registrations]),
    orders            : hasDecimals([...props.overview.channels.map(channel => channel.orders), channelTotals.value.orders]),
    childRegistrations: hasDecimals(props.overview.children.flatMap(child => [child.registrations, child.registrations_total])),
    childOrders       : hasDecimals(props.overview.children.flatMap(child => [child.orders, child.orders_total])),
}))

/* Every column says how it was arrived at. These figures each carry a rule that is not guessable
   from the label - what counts as a visit, why revenue lags, which spend is estimated - and a
   dashboard nobody can interrogate gets mistrusted the first time a number looks odd. */
const columnHelp: Record<string, string> = {
    visits: trans('People who arrived from this channel, how many of them bought, and the rate between the two. Counted once per channel per day, so somebody who arrives from a search in the morning and a mailshot in the afternoon is one visit for each - the group total therefore counts them twice, because both channels did send them. A storefront arrival is counted when the referrer names the channel; an email click is counted when it is clicked, since by the time the reader lands there is nothing left to identify.'),
    spend: trans('Ad spend imported for this channel over the period. Newsletter spend is estimated from the emails actually sent, at our per-message price, and marked est.'),
    awaiting: trans('Value of orders already placed but not invoiced yet. Invoicing runs a day or two behind, so this is what the channel has sold that has not become revenue yet. It moves into Revenue as invoices are raised, and drops if an order is cancelled.'),
    revenue: trans('Invoiced sales credited to this channel. Touched, not necessarily caused - a regular who was going to order anyway still counts if they arrived through it. An order only counts if it was placed after the touch and within the attribution window, so a click cannot claim an order that was already on its way.'),
    registrations: trans('Customers who signed up after arriving through this channel. A red figure beside it is subscribers lost over the same emails - not subtracted, because an unsubscribe costs permission to email somebody, not the customer. Touched, not necessarily won - somebody who would have found us anyway still counts if they came through it. Shared between channels when someone arrived more than one way, so a customer is never counted twice.'),
    orders: trans('Orders placed after a touch from this channel, counted when the order is placed rather than when it ships. Touched, not necessarily caused: a customer who would have reordered anyway and clicked a mailshot first still counts here.'),
    roas: trans('Revenue divided by spend. Blank while money is still awaiting invoice, since a channel that has sold but not yet invoiced has not returned nothing - it has not finished being measured.'),
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="pt-3">
        <DashboardSettings
            :intervals="intervals"
            :settings="settings"
            currentTab="marketing"
            :reloadOnly="['overview', 'intervals']"
        />
    </div>

    <!-- Capped: wider than this and the columns drift so far apart the rows stop reading as rows.
         The space left over carries the referrers list instead. -->
    <div class="px-4 py-4 space-y-4 max-w-[1600px]">
        <div class="flex items-start justify-between gap-4">
            <p v-if="periodPredatesAttribution" class="text-xs text-amber-600 max-w-3xl order-last flex items-start gap-1.5">
                <span class="shrink-0 mt-px inline-flex items-center justify-center w-4 h-4 rounded-full border border-amber-500 text-[10px] font-semibold leading-none">!</span>
                <span>{{ trans('Recording from') }} {{ attributionStarted }}. {{ trans('Anything before that is not counted.') }}</span>
            </p>
            <p class="text-xs text-gray-500 max-w-3xl">
                {{ trans('Everything here counts what marketing touched: sales and sign-ups from people who arrived through an ad, a search, a mailshot or a link from another site, credited to that channel. Touched, not caused — a regular who was going to order anyway still counts if they came through one. It is not the shop\'s total trade.') }}
                <span class="text-gray-400">{{ trans('All figures in') }} {{ overview.currency_code }}.</span>
            </p>
        </div>

        <!-- Headline: the four numbers management asks for -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                <div class="text-xs text-gray-400">{{ trans('Revenue marketing touched') }}</div>
                <div class="mt-1 text-lg tabular-nums">{{ money(overview.totals.revenue) }}</div>
                <div class="mt-0.5 text-xs text-gray-400">
                    {{ trans('of') }} {{ money(overview.baseline.revenue) }} {{ trans('total') }} · {{ share(overview.totals.revenue, overview.baseline.revenue) }}
                </div>
                <div v-if="overview.totals.pending > 0" class="mt-0.5 text-xs text-[#006300]">
                    + {{ money(overview.totals.pending) }} {{ trans('sold, awaiting invoice') }}
                </div>
            </div>
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                <div class="text-xs text-gray-400">{{ trans('New customers marketing touched') }}</div>
                <div class="mt-1 text-lg tabular-nums flex items-baseline gap-1.5">
                    <span>{{ count(overview.totals.registrations) }}</span>
                    <template v-if="overview.totals.unsubscribed > 0">
                        <span v-tooltip="unsubscribedHelp" class="text-[#d03b3b] cursor-help">
                            − {{ count(overview.totals.unsubscribed) }}
                        </span>
                        <span class="text-gray-300">=</span>
                        <span v-tooltip="netRegistrationsHelp" class="cursor-help"
                              :class="overview.totals.registrations - overview.totals.unsubscribed < 0 ? 'text-[#d03b3b]' : 'text-gray-600'">
                            {{ netRegistrations(overview.totals.registrations, overview.totals.unsubscribed) }}
                        </span>
                    </template>
                </div>
                <div class="mt-0.5 text-xs" :class="overview.baseline.registrations > 0 && overview.totals.registrations === 0 ? 'text-[#d03b3b]' : 'text-gray-400'">
                    {{ trans('of') }} {{ count(overview.baseline.registrations) }} {{ trans('who signed up') }} · {{ share(overview.totals.registrations, overview.baseline.registrations) }}
                </div>
            </div>
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                <div class="text-xs text-gray-400">{{ trans('Orders marketing touched') }}</div>
                <div class="mt-1 text-lg tabular-nums">{{ count(overview.totals.orders) }}</div>
                <div class="mt-0.5 text-xs text-gray-400">
                    {{ trans('of') }} {{ count(overview.baseline.orders) }} {{ trans('placed') }} · {{ share(overview.totals.orders, overview.baseline.orders) }}
                </div>
            </div>
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                <div class="text-xs text-gray-400">{{ trans('What it cost') }}</div>
                <div class="mt-1 text-lg tabular-nums">{{ money(overview.totals.spend) }}</div>
                <!-- Ads are invoiced by the platform; email is our own estimate. Kept apart so an
                     estimate is never mistaken for a bill. -->
                <div class="mt-0.5 text-xs text-gray-400 tabular-nums">
                    {{ trans('ads') }} {{ money(overview.totals.spend_ads) }} ·
                    {{ trans('email') }} {{ money(overview.totals.spend_email) }} {{ trans('est.') }}
                </div>
                <div class="mt-0.5 text-xs" :class="overview.totals.roas === null ? 'text-gray-300' : overview.totals.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                    {{ trans('ROAS') }} {{ overview.totals.roas !== null ? overview.totals.roas.toFixed(2) + '×' : '—' }}
                </div>
            </div>
        </div>

        <!-- Channels: the whole point of the aggregate, which channel earns across every shop -->
        <div v-if="overview.channels.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <span class="text-sm font-medium text-gray-800">{{ trans('Where it came from') }}</span>
                    <span class="ml-2 text-xs text-gray-400">
                        {{ trans('ads, searches, mailshots and referring sites') }} · {{ measuredSince }}
                    </span>
                </div>
                <button type="button" @click="showChannelDetail = !showChannelDetail"
                        class="shrink-0 text-xs text-gray-500 hover:text-gray-800 border border-gray-200 rounded-md px-2 py-1">
                    {{ showChannelDetail ? trans('Collapse') : trans('Expand') }}
                </button>
            </div>

            <table class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Channel') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">
                            {{ trans('Visits') }}<sup v-tooltip="columnHelp.visits" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                        </th>
                        <th class="text-right font-normal py-1.5 px-2">
                            {{ trans('Spend') }}<sup v-tooltip="columnHelp.spend" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                        </th>
                        <!-- Awaiting invoice sits before Revenue: that is the order things happen in,
                             an order is placed and then invoiced. -->
                        <th class="text-right font-normal py-1.5 px-2">
                            {{ trans('Awaiting invoice') }}<sup v-tooltip="columnHelp.awaiting" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                        </th>
                        <th class="text-right font-normal py-1.5 px-2">
                            {{ trans('Revenue') }}<sup v-tooltip="columnHelp.revenue" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                        </th>
                        <th class="text-right font-normal py-1.5 px-2">
                            {{ trans('Registrations') }}<sup v-tooltip="columnHelp.registrations" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                        </th>
                        <th class="text-right font-normal py-1.5 px-2">
                            {{ trans('Orders') }}<sup v-tooltip="columnHelp.orders" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                        </th>
                        <th class="text-right font-normal py-1.5 pl-2">
                            {{ trans('ROAS') }}<sup v-tooltip="columnHelp.roas" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                        </th>
                    </tr>
                </thead>
                <tbody v-for="group in groupedChannels" :key="group.key">
                    <tr class="text-gray-900 bg-gray-100/80 border-t-2 border-b border-gray-300 font-medium leading-tight">
                        <td class="py-1 pr-2 text-xs leading-tight">{{ group.label }}</td>
                        <td class="text-right px-2 tabular-nums whitespace-nowrap">
                            <span class="inline-grid grid-cols-[3.5rem_6.5rem_2.75rem]">
                                <span>{{ group.visits > 0 ? locale.number(group.visits) : '' }}</span>
                                <span class="text-xs font-normal" :class="group.orders > 0 ? 'text-[#006300]' : 'text-gray-500'">
                                    <template v-if="group.visits > 0">· {{ count(group.orders, decimalColumns.orders) }} {{ trans('bought') }}</template>
                                </span>
                                <span class="text-xs font-normal" :class="group.orders > 0 ? 'text-[#006300]' : 'text-gray-500'">
                                    <template v-if="group.visits > 0">· {{ share(group.orders, group.visits) }}</template>
                                </span>
                            </span>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ money(group.spend) }}</td>
                        <td class="text-right px-2 tabular-nums text-gray-500">
                            {{ group.pending > 0 ? money(group.pending) : '' }}
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ money(group.revenue) }}</td>
                        <td class="text-right px-2 tabular-nums whitespace-nowrap"
                            :class="group.registrations - group.unsubscribed < 0 ? 'text-[#d03b3b]' : ''">
                            <span class="inline-grid grid-cols-[3.5rem_2.75rem]">
                                <span>{{ netRegistrations(group.registrations, group.unsubscribed, decimalColumns.registrations) }}</span>
                                <span></span>
                            </span>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ count(group.orders, decimalColumns.orders) }}</td>
                        <td class="text-right pl-2 tabular-nums">
                            {{ group.spend > 0 && group.revenue > 0 ? (group.revenue / group.spend).toFixed(2) + '×' : '' }}
                        </td>
                    </tr>
                    <tr v-for="channel in (showChannelDetail ? group.channels : [])" :key="channel.type"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2 pl-5">
                            <Link :href="route(channel.route.name, channel.route.parameters)"
                                  class="text-gray-500 hover:text-gray-900 hover:underline">{{ channel.name }}</Link>
                        </td>
                        <!-- Visits it sent, and how many of them bought. The pair is the point: people
                             arrived and nobody ordered is the case worth seeing. -->
                        <td class="text-right px-2 tabular-nums whitespace-nowrap">
                            <span class="inline-grid grid-cols-[3.5rem_6.5rem_2.75rem]">
                                <span :class="channel.visits > 0 ? '' : 'text-gray-300'">
                                    {{ channel.visits > 0 ? locale.number(channel.visits) : '—' }}
                                </span>
                                <span class="text-xs" :class="channel.orders > 0 ? 'text-[#006300]' : ''">
                                    <template v-if="channel.visits > 0">· {{ count(channel.orders, decimalColumns.orders) }} {{ trans('bought') }}</template>
                                </span>
                                <span class="text-xs" :class="channel.orders > 0 ? 'text-[#006300]' : ''">
                                    <template v-if="channel.visits > 0">· {{ share(channel.orders, channel.visits) }}</template>
                                </span>
                            </span>
                        </td>
                        <!-- The qualifier sits left of the figure so the amounts stay aligned on their
                             right edge, whether or not one of them is estimated. -->
                        <td class="text-right px-2 tabular-nums whitespace-nowrap">
                            <span v-if="channel.spend_is_estimated" class="text-xs text-gray-400 mr-1"
                                  :title="trans('Estimated from emails sent, at the SES per-message price')">{{ trans('est.') }}</span>{{ money(channel.spend) }}
                        </td>
                        <td class="text-right px-2 tabular-nums" :class="channel.pending > 0 ? 'text-gray-400' : 'text-gray-300'">{{ money(channel.pending) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(channel.revenue) }}</td>
                        <!-- Unsubscribes sit beside registrations, never netted off them: losing
                             permission to email somebody is not losing the customer. -->
                        <td class="text-right px-2 tabular-nums whitespace-nowrap">
                            <span class="inline-grid grid-cols-[3.5rem_2.75rem]">
                                <span>
                                    <Link v-if="channel.registrations > 0"
                                          :href="route(channel.registrations_route.name, channel.registrations_route.parameters)"
                                          class="hover:text-gray-900 hover:underline">{{ count(channel.registrations, decimalColumns.registrations) }}</Link>
                                    <template v-else>{{ count(channel.registrations, decimalColumns.registrations) }}</template>
                                </span>
                                <span class="text-[#d03b3b]">
                                    <template v-if="channel.unsubscribed > 0">−{{ locale.number(channel.unsubscribed) }}</template>
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
                                    · {{ count(channelTotals.orders, decimalColumns.orders) }} {{ trans('bought') }}
                                </span>
                                <span class="text-xs font-normal" :class="channelTotals.orders > 0 ? 'text-[#006300]' : 'text-gray-500'">
                                    · {{ share(channelTotals.orders, channelTotals.visits) }}
                                </span>
                            </span>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ money(channelTotals.spend) }}</td>
                        <td class="text-right px-2 tabular-nums text-gray-500">{{ money(channelTotals.pending) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(channelTotals.revenue) }}</td>
                        <td class="text-right px-2 tabular-nums whitespace-nowrap"
                            :class="channelTotals.registrations - channelTotals.unsubscribed < 0 ? 'text-[#d03b3b]' : ''">
                            <span class="inline-grid grid-cols-[3.5rem_2.75rem]">
                                <span>{{ netRegistrations(channelTotals.registrations, channelTotals.unsubscribed, decimalColumns.registrations) }}</span>
                                <span></span>
                            </span>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ count(channelTotals.orders, decimalColumns.orders) }}</td>
                        <td class="text-right pl-2 tabular-nums">
                            {{ channelTotals.spend > 0 && channelTotals.revenue > 0 ? (channelTotals.revenue / channelTotals.spend).toFixed(2) + '×' : '' }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <p class="mt-3 text-xs text-gray-400">
                {{ trans('Visits count everyone a channel sent, whether or not they bought - not unique people: each browser counts once per channel per day, so the same person on two days counts twice. Only counted since the visit counter was switched on, so a channel with history but no visits simply predates it.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-4 items-start">

        <!-- The drill-down: link down a level rather than repeat that level's dashboard here -->
        <div v-if="overview.children.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div>
                <span class="text-sm font-medium text-gray-800">{{ overview.children_label }}</span>
                <span class="ml-2 text-xs text-gray-400">
                    {{ trans('what marketing touched in each one') }} · {{ measuredSince }}
                </span>
            </div>
            <p class="mt-1 text-xs text-gray-400">
                {{ trans('Open one to see its channels, campaigns and mailshots.') }}
            </p>

            <table class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Name') }}</th>
                        <th class="text-left font-normal py-1.5 px-2 whitespace-nowrap">{{ trans('Best channel') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Revenue touched') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Registrations') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Orders') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="child in overview.children" :key="child.slug"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2">
                            <Link :href="route(child.route.name, child.route.parameters)"
                                  class="text-gray-700 hover:text-indigo-600">
                                {{ child.name }}
                            </Link>
                        </td>
                        <td class="px-2 text-gray-500">{{ child.top_channel ?? '—' }}</td>
                        <!-- Invoiced, plus what is still awaiting invoice, against everything the
                             business took: the share is the point, not the figure on its own. -->
                        <td class="text-right px-2 tabular-nums whitespace-nowrap">
                            <span class="inline-grid grid-cols-[4.75rem_5.25rem_5.5rem_2.75rem]">
                                <span>{{ money(child.revenue) }}</span>
                                <span class="text-[#006300]">
                                    <template v-if="child.pending > 0">+ {{ money(child.pending) }}</template>
                                </span>
                                <span class="text-gray-400">/ {{ money(child.revenue_total) }}</span>
                                <span class="text-gray-400">· {{ share(child.revenue + child.pending, child.revenue_total) }}</span>
                            </span>
                        </td>
                        <!-- Against the total, so a zero says marketing reached nobody rather than
                             that nothing happened. -->
                        <td class="text-right px-2 tabular-nums whitespace-nowrap"
                            :class="child.registrations_total > 0 && child.registrations === 0 ? 'text-[#d03b3b]' : ''">
                            <span class="inline-grid grid-cols-[3.25rem_3.5rem_3rem]">
                                <span>{{ count(child.registrations, decimalColumns.childRegistrations) }}</span>
                                <span class="text-gray-400">/ {{ count(child.registrations_total, decimalColumns.childRegistrations) }}</span>
                                <span class="text-gray-400">· {{ share(child.registrations, child.registrations_total) }}</span>
                            </span>
                        </td>
                        <td class="text-right pl-2 tabular-nums whitespace-nowrap">
                            <span class="inline-grid grid-cols-[3.25rem_3.5rem_3rem]">
                                <span>{{ count(child.orders, decimalColumns.childOrders) }}</span>
                                <span class="text-gray-400">/ {{ count(child.orders_total, decimalColumns.childOrders) }}</span>
                                <span class="text-gray-400">· {{ share(child.orders, child.orders_total) }}</span>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Sites sending people to any shop underneath, pooled by host -->
        <div v-if="overview.referrers?.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <span class="text-sm font-medium text-gray-800">{{ trans('Who sends us people') }}</span>
            <p class="mt-1 text-xs text-gray-400">
                {{ trans('Sites linking to us and search engines finding us. A search engine sending people is the case for advertising on it.') }}
            </p>

            <table class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Site') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('People') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Revenue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="referrer in overview.referrers" :key="referrer.host"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2 text-gray-700 truncate max-w-[12rem]">
                            {{ referrer.host }}
                            <span v-if="referrer.kind === 'search'" class="text-gray-400">{{ trans('search') }}</span>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ count(referrer.visitors) }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ money(referrer.revenue) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        </div>

        <div v-if="!overview.channels.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5 text-xs text-gray-500">
            <span v-if="overview.baseline.registrations > 0 || overview.baseline.orders > 0">
                {{ trans('Nothing in this period can be traced back to marketing, yet the business took') }}
                {{ count(overview.baseline.orders) }} {{ trans('orders and') }}
                {{ count(overview.baseline.registrations) }} {{ trans('sign-ups. That trade arrived on its own.') }}
            </span>
            <span v-else>{{ trans('No marketing activity in this period yet.') }}</span>
        </div>
    </div>
</template>
