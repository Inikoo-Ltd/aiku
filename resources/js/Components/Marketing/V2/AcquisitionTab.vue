<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import Select from "primevue/select"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import DeltaBadge from "@/Components/DataDisplay/Dashboard/DeltaBadge.vue"
import { BAR_TRACK_COLOR, CHANNEL_COLORS, OTHER_CHANNEL_COLOR, channelColor } from "@/Components/Marketing/V2/palette"

type WindowFigures = {
    days: number
    revenue: number
    buyers: number
    revenue_per_buyer: number | null
    state: "final" | "growing" | "not_yet"
}

type ValueFigures = {
    buyers: number
    margin_to_date: number | null
    cltv: Record<number, number | null>
    ltv_cac: Record<number, number | null>
    payback_months: number | null
    payback_beyond: boolean
}

type Totals = {
    registrations: number
    buyers: number
    paid_registrations: number
    paid_buyers: number
    paid_spend: number
    paid_cac: number | null
    no_first_touch: number
    no_first_touch_buyers: number
    paid_value: ValueFigures | null
}

type Channel = {
    type: string
    name: string
    is_paid: boolean
    registrations: number
    buyers: number
    spend: number
    cac: number | null
    windows: WindowFigures[]
    value: ValueFigures | null
}

const props = defineProps<{
    data: {
        currency_code: string
        period_label: string
        from: string | null
        to: string | null
        windows: number[]
        totals: Totals
        previous: {
            from: string | null
            to: string | null
            requested_from: string
            requested_to: string
            label: string
            before_tracking: boolean
            cut_by_tracking: boolean
            totals: Totals
            channels: Channel[]
        } | null
        value: {
            horizons: number[]
            hydrated_at: string | null
            average_margin: number | null
            cost_coverage: number | null
            cohorts: Record<number, { from: string, to: string, buyers: number }>
        } | null
        channels: Channel[]
        cohorts: {
            months: { key: string, label: string }[]
            channels: {
                type: string
                name: string
                customers: number
                is_paid: boolean
                rows: { key: string, label: string, customers: number, buyers: number, spend: number, cac: number | null, cells: WindowFigures[], value: ValueFigures | null }[]
            }[]
        }
    }
}>()

const locale = useLocaleStore()
const money = (value: number) => locale.currencyFormat(props.data.currency_code, value)
const pct = (part: number, whole: number) => whole > 0 ? (part / whole * 100).toFixed(1) + "%" : "—"

const withCustomers = computed(() => props.data.channels.filter(channel => channel.registrations > 0))

const donutSlices = computed(() => {
    const slices: { key: string, name: string, color: string, buyers: number, registrations: number }[] = []
    const other = { key: "other", name: ctrans("Other channels"), color: OTHER_CHANNEL_COLOR, buyers: 0, registrations: 0 }

    for (const channel of withCustomers.value) {
        if (channel.type === "no_first_touch" || CHANNEL_COLORS[channel.type]) {
            slices.push({ key: channel.type, name: channel.name, color: channelColor(channel.type), buyers: channel.buyers, registrations: channel.registrations })
        } else {
            other.buyers += channel.buyers
            other.registrations += channel.registrations
        }
    }

    if (other.registrations > 0) {
        slices.splice(slices.findIndex(slice => slice.key === "no_first_touch") === -1 ? slices.length : slices.findIndex(slice => slice.key === "no_first_touch"), 0, other)
    }

    return slices
})

const donutBackground = computed(() => {
    const total = donutSlices.value.reduce((sum, slice) => sum + slice.buyers, 0)
    if (total === 0) {
        return BAR_TRACK_COLOR
    }
    let start = 0
    const stops = donutSlices.value.filter(slice => slice.buyers > 0).map(slice => {
        const end = start + slice.buyers / total * 360
        const stop = `${slice.color} ${start.toFixed(2)}deg ${(end - 0.8).toFixed(2)}deg, #ffffff ${(end - 0.8).toFixed(2)}deg ${end.toFixed(2)}deg`
        start = end
        return stop
    })
    return `conic-gradient(${stops.join(", ")})`
})

const paidChannels = computed(() => props.data.channels.filter(channel => channel.is_paid))
const maxCac = computed(() => Math.max(1, ...paidChannels.value.map(channel => channel.cac ?? 0)))

const windowDays = ref(props.data.windows[0])
const windowOptions = computed(() => props.data.windows.map(days => ({ label: ctrans(":days days", { days }), value: days })))

const revenueRows = computed(() => withCustomers.value
    .map(channel => ({ ...channel, figures: channel.windows.find(window => window.days === windowDays.value)! }))
    .filter(row => row.buyers > 0))

const windowReached = computed(() => revenueRows.value.some(row => row.figures.state !== "not_yet"))
const windowGrowing = computed(() => revenueRows.value.some(row => row.figures.state === "growing"))
const maxRevenuePerBuyer = computed(() => Math.max(1, ...revenueRows.value.map(row => row.figures.revenue_per_buyer ?? 0)))

const cohortChannelOptions = computed(() => props.data.cohorts.channels.map(channel => ({ label: channel.name, value: channel.type })))
const cohortChannel = ref(
    props.data.cohorts.channels.find(channel => channel.type === "google-ads")?.type
    ?? props.data.cohorts.channels[0]?.type
)
const cohort = computed(() => props.data.cohorts.channels.find(channel => channel.type === cohortChannel.value))

const horizon = ref(props.data.value?.horizons[props.data.value.horizons.length - 1] ?? 36)
const horizonOptions = computed(() => (props.data.value?.horizons ?? []).map(months => ({
    label: months === 12 ? ctrans("1 year") : ctrans(":years years", { years: months / 12 }),
    value: months,
})))
const horizonLabel = computed(() => horizonOptions.value.find(option => option.value === horizon.value)?.label ?? "")

const valueRows = computed(() => withCustomers.value.filter(channel => (channel.value?.buyers ?? 0) > 0))

const paidValueChannelOptions = computed(() => props.data.cohorts.channels.filter(channel => channel.is_paid).map(channel => ({ label: channel.name, value: channel.type })))
const paidValueChannel = ref(
    props.data.cohorts.channels.find(channel => channel.type === "google-ads" && channel.is_paid)?.type
    ?? props.data.cohorts.channels.find(channel => channel.is_paid)?.type
)
const paidValueCohort = computed(() => props.data.cohorts.channels.find(channel => channel.type === paidValueChannel.value))

const ratioText = (ratio: number | null | undefined) => ratio === null || ratio === undefined ? "—" : `${locale.number(ratio)} : 1`
const ratioClass = (ratio: number | null | undefined) => {
    if (ratio === null || ratio === undefined) {
        return "text-gray-400"
    }
    return ratio >= 3 ? "bg-green-50 text-green-700 ring-green-200" : ratio >= 1 ? "bg-amber-50 text-amber-800 ring-amber-200" : "bg-red-50 text-red-700 ring-red-200"
}
const ratioHelp = ctrans("Lifetime gross margin per buyer over the cost per buyer. Under 1 the buyer costs more than they earn; around 3 is healthy; well above 5 can mean spending too little.")

const paybackText = (value: ValueFigures | null | undefined) => {
    if (!value || value.payback_months === null) {
        return value?.payback_beyond ? ctrans("Over :months months", { months: 36 }) : "—"
    }
    return value.payback_months < 1 ? ctrans("Under 1 month") : ctrans(":months months", { months: locale.number(value.payback_months) })
}

const percent = (value: number | null | undefined) => value === null || value === undefined ? "—" : `${locale.number(Math.round(value * 1000) / 10)}%`

const previous = computed(() => props.data.previous && !props.data.previous.before_tracking ? props.data.previous : null)
const previousChannel = (type: string) => previous.value?.channels.find(channel => channel.type === type)
const share = (part: number, whole: number) => whole > 0 ? part / whole * 100 : null
const previousShare = (part: keyof Totals, whole: keyof Totals) => previous.value ? share(previous.value.totals[part] as number, previous.value.totals[whole] as number) : null
const percentText = (value: number) => `${value.toFixed(1)}%`

const stateHelp: Record<string, string> = {
    final: ctrans("Every customer of this cohort is past this window: the figure is final."),
    growing: ctrans("Some customers of this cohort are still inside this window: the figure keeps growing."),
    not_yet: ctrans("No customer of this cohort has got this far yet."),
}
</script>

<template>
    <div class="px-4 py-5 md:px-6 space-y-6">
        <p v-if="data.from" class="text-xs text-gray-400">
            {{ data.to ? ctrans("Customers who registered from :from to :to", { from: data.from, to: data.to }) : ctrans("Customers who registered since :from", { from: data.from }) }}
        </p>
        <p v-if="data.previous" class="-mt-4 text-xs text-gray-500">
            {{ ctrans("Compared with :label, :from to :to", { label: data.previous.label.toLowerCase(), from: data.previous.requested_from, to: data.previous.requested_to }) }}<span v-if="data.previous.cut_by_tracking">{{ ctrans(", counted from :date when tracking began", { date: data.previous.from ?? "" }) }}</span><span v-if="data.previous.before_tracking" class="text-amber-700">{{ ctrans(": before tracking began, so there is nothing to compare") }}</span>.
            <span class="text-gray-400">{{ ctrans("Customers of the earlier period have had longer to buy.") }}</span>
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
                <div class="text-xs text-gray-500">{{ ctrans("Registrations") }}</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900 tabular-nums">{{ locale.number(data.totals.registrations) }}</div>
                <DeltaBadge v-if="previous" class="mt-1" :current="data.totals.registrations" :previous="previous.totals.registrations" :format="locale.number" />
            </div>
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
                <div class="text-xs text-gray-500">{{ ctrans("New buyers") }}</div>
                <div class="mt-1 flex items-baseline gap-2 tabular-nums">
                    <span class="text-3xl font-semibold text-gray-900">{{ locale.number(data.totals.buyers) }}</span>
                    <span class="text-sm text-gray-500">{{ pct(data.totals.buyers, data.totals.registrations) }}</span>
                </div>
                <DeltaBadge v-if="previous" class="mt-1" :current="data.totals.buyers" :previous="previous.totals.buyers" :format="locale.number" />
            </div>
            <div class="rounded-xl bg-[--app-accent-soft] ring-1 ring-[--app-accent-muted] p-5">
                <div class="text-xs text-[--app-accent-deep]">{{ ctrans("Brought by ads") }}</div>
                <div class="mt-1 flex items-baseline gap-2 tabular-nums text-[--app-accent-deep]">
                    <span class="text-3xl font-semibold">{{ pct(data.totals.paid_registrations, data.totals.registrations) }}</span>
                    <span class="text-sm">{{ locale.number(data.totals.paid_buyers) }} {{ ctrans("buyers") }}</span>
                </div>
                <DeltaBadge v-if="previous" class="mt-1" :current="share(data.totals.paid_registrations, data.totals.registrations)" :previous="previousShare('paid_registrations', 'registrations')" :format="percentText" />
            </div>
            <div class="rounded-xl bg-[--app-accent] p-5 text-[--app-accent-text]">
                <div class="text-xs opacity-90">{{ ctrans("Paid CAC") }}</div>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-3xl font-semibold tabular-nums">{{ data.totals.paid_cac !== null ? money(data.totals.paid_cac) : "—" }}</span>
                    <DeltaBadge v-if="previous" :current="data.totals.paid_cac" :previous="previous.totals.paid_cac" better="down" :format="money" />
                </div>
                <div class="mt-0.5 text-xs opacity-90 tabular-nums">{{ money(data.totals.paid_spend) }} {{ ctrans("ad spend") }}</div>
                <div v-if="data.totals.paid_value?.ltv_cac[horizon]" class="mt-0.5 text-xs opacity-90 tabular-nums" v-tooltip="ratioHelp">
                    {{ ctrans("LTV:CAC") }} {{ ratioText(data.totals.paid_value.ltv_cac[horizon]) }} · {{ horizonLabel }}
                    <DeltaBadge v-if="previous" class="ml-1" :current="data.totals.paid_value.ltv_cac[horizon]" :previous="previous.totals.paid_value?.ltv_cac[horizon]" :format="ratioText" />
                </div>
            </div>
            <div class="rounded-xl ring-1 ring-amber-200 bg-amber-50 p-5">
                <div class="text-xs text-amber-800" v-tooltip="ctrans('No touch in the 90 days before registering.')">{{ ctrans("No first touch") }}</div>
                <div class="mt-1 flex items-baseline gap-2 tabular-nums text-amber-800">
                    <span class="text-3xl font-semibold">{{ pct(data.totals.no_first_touch, data.totals.registrations) }}</span>
                    <span class="text-sm">{{ locale.number(data.totals.no_first_touch) }}</span>
                </div>
                <DeltaBadge v-if="previous" class="mt-1" :current="share(data.totals.no_first_touch, data.totals.registrations)" :previous="previousShare('no_first_touch', 'registrations')" better="down" :format="percentText" />
            </div>
        </div>

        <div v-if="data.totals.registrations === 0" class="rounded-xl ring-1 ring-gray-200 bg-white py-10 text-center text-sm text-gray-500">
            {{ ctrans("Nobody registered in this period") }}
        </div>

        <template v-else>
            <div class="flex flex-col gap-6 lg:flex-row">
                <section class="flex-1 min-w-0 rounded-xl ring-1 ring-gray-200 bg-white p-5" aria-labelledby="v2-donut">
                    <h2 id="v2-donut" class="text-sm font-medium text-gray-800">{{ ctrans("Buyers by acquisition channel") }}</h2>
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-8">
                        <div role="img" :aria-label="ctrans('Donut chart of new buyers by acquisition channel')"
                             class="flex h-48 w-48 shrink-0 items-center justify-center rounded-full" :style="{ background: donutBackground }">
                            <div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white">
                                <span class="text-3xl font-semibold text-gray-900 tabular-nums">{{ locale.number(data.totals.buyers) }}</span>
                                <span class="text-xs text-gray-500">{{ ctrans("buyers") }}</span>
                            </div>
                        </div>
                        <table class="text-sm">
                            <thead class="whitespace-nowrap">
                                <tr class="text-xs text-gray-400">
                                    <th class="pb-1.5 pr-4 text-left font-normal">{{ ctrans("Channel") }}</th>
                                    <th class="pb-1.5 px-2 text-right font-normal">{{ ctrans("Registrations") }}</th>
                                    <th class="pb-1.5 pl-2 text-right font-normal">{{ ctrans("Buyers") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="slice in donutSlices" :key="slice.key" class="text-gray-700">
                                    <td class="py-1 pr-4">
                                        <span class="flex items-center gap-2">
                                            <span class="h-3 w-3 shrink-0 rounded-sm" :style="{ background: slice.color }" aria-hidden="true" />
                                            <span :class="slice.key === 'no_first_touch' ? 'italic text-gray-500' : ''">{{ slice.name }}</span>
                                        </span>
                                    </td>
                                    <td class="py-1 px-2 text-right tabular-nums text-gray-500">{{ locale.number(slice.registrations) }}</td>
                                    <td class="py-1 pl-2 text-right font-medium tabular-nums">{{ locale.number(slice.buyers) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="flex-1 min-w-0 rounded-xl ring-1 ring-gray-200 bg-white p-5" aria-labelledby="v2-cac">
                    <h2 id="v2-cac" class="text-sm font-medium text-gray-800">{{ ctrans("Cost per new buyer") }}</h2>
                    <span class="text-xs text-gray-400">{{ ctrans("Ad spend over the period divided by the new buyers the channel brought in") }}</span>
                    <div v-if="paidChannels.length" class="mt-4 flex h-56 items-end justify-around gap-4">
                        <div v-for="channel in paidChannels" :key="channel.type"
                             v-tooltip="ctrans(':channel · :spend spend ÷ :buyers buyers = :cac per buyer', { channel: channel.name, spend: money(channel.spend), buyers: locale.number(channel.buyers), cac: channel.cac !== null ? money(channel.cac) : '—' })"
                             class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <span class="text-sm font-medium text-gray-800 tabular-nums">{{ channel.cac !== null ? money(channel.cac) : "—" }}</span>
                            <DeltaBadge v-if="previous" :current="channel.cac" :previous="previousChannel(channel.type)?.cac" better="down" :format="money" />
                            <div class="w-full max-w-16 rounded-t" :style="{ height: channel.cac !== null ? `${Math.max(2, channel.cac / maxCac * 150)}px` : '2px', background: channelColor(channel.type) }" />
                            <span class="text-xs text-gray-600 text-center">{{ channel.name }}</span>
                        </div>
                    </div>
                    <div v-else class="mt-4 py-10 text-center text-sm text-gray-500">{{ ctrans("No paid channel in this period") }}</div>
                </section>
            </div>

            <section class="rounded-xl ring-1 ring-gray-200 bg-white p-5" aria-labelledby="v2-cltv">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="v2-cltv" class="text-sm font-medium text-gray-800">{{ ctrans("Customer lifetime value") }}</h2>
                        <span class="text-xs text-gray-400">{{ ctrans("Projected gross margin per buyer from their first order, next to what it cost to win them") }}</span>
                    </div>
                    <SegmentedToggle v-if="data.value" v-model="horizon" :options="horizonOptions" :ariaLabel="ctrans('Lifetime')" />
                </div>
                <template v-if="data.value">
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[44rem] text-sm">
                            <thead class="whitespace-nowrap">
                                <tr class="border-b border-gray-100 text-xs text-gray-400">
                                    <th class="pb-2 pr-4 text-left font-normal">{{ ctrans("Channel") }}</th>
                                    <th class="pb-2 px-3 text-right font-normal">{{ ctrans("Buyers") }}</th>
                                    <th class="pb-2 px-3 text-right font-normal" v-tooltip="ctrans('Gross margin each buyer has brought so far')">{{ ctrans("Margin so far") }}</th>
                                    <th class="pb-2 px-3 text-right font-normal" v-tooltip="ctrans('Projected gross margin per buyer over the chosen lifetime')">{{ ctrans("CLTV") }} · {{ horizonLabel }}</th>
                                    <th class="pb-2 px-3 text-right font-normal">{{ ctrans("CAC") }}</th>
                                    <th class="pb-2 px-3 text-right font-normal" v-tooltip="ratioHelp">{{ ctrans("LTV:CAC") }}</th>
                                    <th class="pb-2 pl-3 text-right font-normal" v-tooltip="ctrans('When the projected margin per buyer covers the cost per buyer')">{{ ctrans("Payback") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="channel in valueRows" :key="channel.type" class="border-b border-gray-50 last:border-0">
                                    <td class="py-2 pr-4">
                                        <span class="flex items-center gap-2">
                                            <span class="h-3 w-3 shrink-0 rounded-sm" :style="{ background: channelColor(channel.type) }" aria-hidden="true" />
                                            <span :class="channel.type === 'no_first_touch' ? 'italic text-gray-500' : 'text-gray-700'">{{ channel.name }}</span>
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-right tabular-nums text-gray-500">{{ locale.number(channel.value!.buyers) }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums text-gray-500">{{ channel.value!.margin_to_date !== null ? money(channel.value!.margin_to_date) : "—" }}</td>
                                    <td class="py-2 px-3 text-right font-medium tabular-nums text-gray-900 whitespace-nowrap">
                                        {{ channel.value!.cltv[horizon] != null ? money(channel.value!.cltv[horizon]!) : "—" }}
                                        <DeltaBadge v-if="previous" class="ml-1" :current="channel.value!.cltv[horizon]" :previous="previousChannel(channel.type)?.value?.cltv[horizon]" :format="money" />
                                    </td>
                                    <td class="py-2 px-3 text-right tabular-nums text-gray-700 whitespace-nowrap">
                                        {{ channel.cac !== null ? money(channel.cac) : "—" }}
                                        <DeltaBadge v-if="previous" class="ml-1" :current="channel.cac" :previous="previousChannel(channel.type)?.cac" better="down" :format="money" />
                                    </td>
                                    <td class="py-2 px-3 text-right whitespace-nowrap">
                                        <span v-if="channel.value!.ltv_cac[horizon] != null" class="rounded-md px-2 py-0.5 text-xs font-medium tabular-nums ring-1" :class="ratioClass(channel.value!.ltv_cac[horizon])">
                                            {{ ratioText(channel.value!.ltv_cac[horizon]) }}
                                        </span>
                                        <span v-else class="text-gray-400">—</span>
                                        <DeltaBadge v-if="previous" class="ml-1" :current="channel.value!.ltv_cac[horizon]" :previous="previousChannel(channel.type)?.value?.ltv_cac[horizon]" :format="ratioText" />
                                    </td>
                                    <td class="py-2 pl-3 text-right tabular-nums text-gray-700 whitespace-nowrap">{{ channel.is_paid ? paybackText(channel.value) : "—" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-gray-400">
                        {{ ctrans("Projection: what each buyer earned in their first days (up to 90), grown the way this shop's buyers whose first order was between :from and :to grew. Gross margin is the price less the current product cost, without shipping; :coverage of product revenue has a cost, the rest uses the shop average of :margin. Updated :date, every month.", {
                            from: data.value.cohorts[horizon]?.from ?? "—",
                            to: data.value.cohorts[horizon]?.to ?? "—",
                            coverage: percent(data.value.cost_coverage),
                            margin: percent(data.value.average_margin),
                            date: data.value.hydrated_at ?? "—",
                        }) }}
                    </p>
                </template>
                <div v-else class="mt-5 flex min-h-24 items-center justify-center rounded-lg text-sm text-gray-500"
                     style="background: repeating-linear-gradient(135deg, #f5f5f4 0 12px, #ffffff 12px 24px)">
                    {{ ctrans("Not calculated yet for this shop: it is worked out on the 1st of every month.") }}
                </div>
            </section>

            <section v-if="data.value && paidValueCohort" class="rounded-xl ring-1 ring-gray-200 bg-white p-5" aria-labelledby="v2-cltv-month">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="v2-cltv-month" class="text-sm font-medium text-gray-800">{{ ctrans("Spend and lifetime value by month") }} · {{ paidValueCohort.name }}</h2>
                        <span class="text-xs text-gray-400">{{ ctrans("Ad spend of each month next to the buyers who registered that month, independent of the selected period") }}</span>
                    </div>
                    <Select v-model="paidValueChannel" :options="paidValueChannelOptions" optionLabel="label" optionValue="value" class="w-56" :aria-label="ctrans('Channel')" />
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead class="whitespace-nowrap">
                            <tr class="border-b border-gray-100 text-xs text-gray-400">
                                <th class="pb-2 pr-4 text-left font-normal">{{ ctrans("Registered") }}</th>
                                <th class="pb-2 px-3 text-right font-normal">{{ ctrans("Ad spend") }}</th>
                                <th class="pb-2 px-3 text-right font-normal">{{ ctrans("Buyers") }}</th>
                                <th class="pb-2 px-3 text-right font-normal">{{ ctrans("CAC") }}</th>
                                <th class="pb-2 px-3 text-right font-normal">{{ ctrans("CLTV") }} · {{ horizonLabel }}</th>
                                <th class="pb-2 pl-3 text-right font-normal" v-tooltip="ratioHelp">{{ ctrans("LTV:CAC") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in paidValueCohort.rows" :key="row.key" class="border-b border-gray-50 last:border-0">
                                <td class="py-2 pr-4 whitespace-nowrap text-gray-700">{{ row.label }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-gray-700">{{ money(row.spend) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-gray-500">{{ locale.number(row.buyers) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-gray-700">{{ row.cac !== null ? money(row.cac) : "—" }}</td>
                                <td class="py-2 px-3 text-right font-medium tabular-nums text-gray-900">{{ row.value?.cltv[horizon] != null ? money(row.value.cltv[horizon]!) : "—" }}</td>
                                <td class="py-2 pl-3 text-right">
                                    <span v-if="row.value?.ltv_cac[horizon] != null" class="rounded-md px-2 py-0.5 text-xs font-medium tabular-nums ring-1" :class="ratioClass(row.value.ltv_cac[horizon])">
                                        {{ ratioText(row.value.ltv_cac[horizon]) }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-400">{{ ctrans("A month with few buyers swings a lot: read the trend over several months, not one.") }}</p>
            </section>

            <section class="rounded-xl ring-1 ring-gray-200 bg-white p-5" aria-labelledby="v2-rpb">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="v2-rpb" class="text-sm font-medium text-gray-800">{{ ctrans("Revenue per buyer") }}</h2>
                        <span class="text-xs text-gray-400">{{ ctrans("Orders placed within the window after registering, per buyer") }}</span>
                    </div>
                    <SegmentedToggle v-model="windowDays" :options="windowOptions" :ariaLabel="ctrans('Window')" />
                </div>
                <div v-if="windowReached" class="mt-5 space-y-2.5">
                    <div v-for="row in revenueRows" :key="row.type"
                         v-tooltip="ctrans(':channel · :revenue revenue ÷ :buyers buyers = :perBuyer per buyer', { channel: row.name, revenue: money(row.figures.revenue), buyers: locale.number(row.figures.buyers), perBuyer: row.figures.revenue_per_buyer !== null ? money(row.figures.revenue_per_buyer) : '—' })"
                         class="grid grid-cols-[8rem_minmax(0,1fr)_6rem] md:grid-cols-[11rem_minmax(0,1fr)_7rem] items-center gap-3">
                        <span class="truncate text-sm" :class="row.type === 'no_first_touch' ? 'italic text-gray-500' : 'text-gray-700'">{{ row.name }}</span>
                        <div class="relative h-4 rounded-r-[4px]" :style="{ background: BAR_TRACK_COLOR }">
                            <div class="absolute inset-y-0 left-0 rounded-r-[4px]" :style="{ width: `${(row.figures.revenue_per_buyer ?? 0) / maxRevenuePerBuyer * 100}%`, background: channelColor(row.type) }" />
                        </div>
                        <span class="text-right text-sm font-medium text-gray-800 tabular-nums">{{ row.figures.revenue_per_buyer !== null ? money(row.figures.revenue_per_buyer) : "—" }}</span>
                    </div>
                    <p v-if="windowGrowing" class="pt-1 text-xs text-gray-400">{{ stateHelp.growing }}</p>
                </div>
                <div v-else class="mt-5 flex min-h-36 items-center justify-center rounded-lg text-sm font-medium text-gray-500"
                     style="background: repeating-linear-gradient(135deg, #f5f5f4 0 12px, #ffffff 12px 24px)">
                    {{ ctrans("Not reached yet") }}
                </div>
            </section>

            <section v-if="cohort" class="rounded-xl ring-1 ring-gray-200 bg-white p-5" aria-labelledby="v2-cohort">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="v2-cohort" class="text-sm font-medium text-gray-800">{{ ctrans("Cohort growth") }} · {{ cohort.name }}</h2>
                        <span class="text-xs text-gray-400">{{ ctrans("Revenue per buyer by registration month, independent of the selected period") }}</span>
                    </div>
                    <Select v-model="cohortChannel" :options="cohortChannelOptions" optionLabel="label" optionValue="value" class="w-56" :aria-label="ctrans('Channel')" />
                </div>
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[36rem] border-separate border-spacing-2 text-sm">
                        <thead class="whitespace-nowrap">
                            <tr class="text-xs text-gray-400">
                                <th class="text-left font-normal">{{ ctrans("Registered") }}</th>
                                <th v-for="days in data.windows" :key="days" class="text-center font-normal">{{ ctrans(":days days", { days }) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in cohort.rows" :key="row.key">
                                <td class="whitespace-nowrap pr-2 text-gray-700">
                                    {{ row.label }}
                                    <span class="ml-1 text-xs text-gray-400 tabular-nums">{{ locale.number(row.customers) }}</span>
                                </td>
                                <td v-for="cell in row.cells" :key="cell.days" v-tooltip="stateHelp[cell.state]"
                                    class="h-12 rounded-lg text-center font-medium tabular-nums"
                                    :class="{
                                        'bg-[--app-accent] text-[--app-accent-text]': cell.state === 'final',
                                        'bg-[--app-accent-soft] text-[--app-accent-deep]': cell.state === 'growing',
                                        'border-2 border-dashed border-gray-200': cell.state === 'not_yet',
                                    }">
                                    {{ cell.state !== "not_yet" && cell.revenue_per_buyer !== null ? money(cell.revenue_per_buyer) : "" }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-gray-600">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-[--app-accent]" />{{ ctrans("Final") }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-[--app-accent-soft] ring-1 ring-[--app-accent-muted]" />{{ ctrans("Growing") }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm border-2 border-dashed border-gray-300" />{{ ctrans("Not yet") }}</span>
                </div>
            </section>
        </template>
    </div>
</template>
