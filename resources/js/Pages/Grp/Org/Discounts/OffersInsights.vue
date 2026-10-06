<!--
    -  Author: stewicca <stewicalf@gmail.com>
    -  Copyright (c) 2026, Steven Wicca Alfredo
-->

<script setup lang="ts">
import { computed, inject, provide, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import Select from "primevue/select"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Table as TableTS } from "@/types/Table"
import { Intervals } from "@/types/Components/Dashboard"
import { RouteParams } from "@/types/route-params"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAnalytics, faBadgePercent, faShoppingCart, faUsers, faCoin, faPiggyBank, faPercent, faTrophy, faThumbsDown, faTags, faClock, faChevronDown } from "@fal"

library.add(faAnalytics, faBadgePercent, faShoppingCart, faUsers, faCoin, faPiggyBank, faPercent, faTrophy, faThumbsDown, faTags, faChevronDown)

interface OfferPerformance {
    slug: string
    code: string
    name: string
    redemptions: number
    customers: number
    revenue_net_amount: number
    discounted_amount: number
}

interface OffersInsightsSuperBlock {
    id: string
    intervals: Intervals
    insights: {
        currency_code: string
        offer_counts: {
            total: number
            active: number
            in_process: number
            finished: number
            suspended: number
            redeemed: number
        }
        totals: {
            redemptions: number
            customers: number
            revenue_gross_amount: number
            revenue_net_amount: number
            discounted_amount: number
            avg_discount: number
            avg_savings_per_customer: number
            discount_rate: number
            conversion_rate: number
        }
        trend: {
            period: string
            redemptions: number
            discounted_amount: number
            revenue_net_amount: number
        }[]
        top_offers: OfferPerformance[]
        least_offers: OfferPerformance[]
    }
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    filters: {
        campaigns: { slug: string, name: string, type: string }[]
        types: string[]
        campaign: string | null
        type: string | null
    }
    offers: TableTS
    dashboard: { super_blocks: OffersInsightsSuperBlock[] }
}>()

const superBlock = computed(() => props.dashboard.super_blocks[0])
const insights = computed(() => superBlock.value.insights)
const intervals = computed(() => superBlock.value.intervals)

const locale = inject("locale", aikuLocaleStructure)

const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)

const selectedCampaign = ref<string | null>(props.filters.campaign)
const selectedType = ref<string | null>(props.filters.type)
const isLoadingFilter = ref(false)

const campaignOptions = computed(() => props.filters.campaigns.map(campaign => ({ label: campaign.name, value: campaign.slug })))

const typeOptions = computed(() => props.filters.types.map(type => ({ label: type, value: type })))

const applyFilters = () => {
    isLoadingFilter.value = true
    router.get(
        route("grp.org.shops.show.discounts.insights", [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
        ]),
        {
            ...(selectedCampaign.value ? { campaign: selectedCampaign.value } : {}),
            ...(selectedType.value ? { type: selectedType.value } : {}),
        },
        {
            preserveScroll: true,
            onFinish: () => { isLoadingFilter.value = false },
        }
    )
}

const currency = (value: number) => locale.currencyFormat(insights.value.currency_code, value || 0)

const kpis = computed(() => [
    {
        label: ctrans("Redemptions (orders)"),
        icon: "fal fa-shopping-cart",
        color: "#6366f1",
        value: locale.number(insights.value.totals.redemptions),
        tooltip: ctrans("Orders where a coupon or voucher discount was actually applied"),
    },
    {
        label: ctrans("Customers redeeming"),
        icon: "fal fa-users",
        color: "#3b82f6",
        value: locale.number(insights.value.totals.customers),
        subtitle: ctrans("Avg savings per customer") + ": " + currency(insights.value.totals.avg_savings_per_customer),
    },
    {
        label: ctrans("Revenue influenced (net)"),
        icon: "fal fa-coin",
        color: "#8b5cf6",
        value: currency(insights.value.totals.revenue_net_amount),
        subtitle: ctrans("Gross") + ": " + currency(insights.value.totals.revenue_gross_amount),
    },
    {
        label: ctrans("Discount given"),
        icon: "fal fa-piggy-bank",
        color: "#10b981",
        value: currency(insights.value.totals.discounted_amount),
        subtitle: ctrans("Avg per redemption") + ": " + currency(insights.value.totals.avg_discount),
    },
    {
        label: ctrans("Margin impact"),
        icon: "fal fa-percent",
        color: "#8b5cf6",
        value: insights.value.totals.discount_rate + "%",
        tooltip: ctrans("Discount given as percentage of revenue before discount"),
    },
    {
        label: ctrans("Conversion rate"),
        icon: "fal fa-badge-percent",
        color: "#3b82f6",
        value: insights.value.totals.conversion_rate + "%",
        subtitle: `${locale.number(insights.value.offer_counts.redeemed)} / ${locale.number(insights.value.offer_counts.total)} ` + ctrans("coupons redeemed"),
        tooltip: ctrans("Coupons redeemed at least once divided by total coupons"),
    },
])

const statusBreakdown = computed(() => [
    { label: ctrans("Active"), value: insights.value.offer_counts.active, bar: "bg-emerald-500" },
    { label: ctrans("Not yet started"), value: insights.value.offer_counts.in_process, bar: "bg-amber-400" },
    { label: ctrans("Expired / finished"), value: insights.value.offer_counts.finished, bar: "bg-gray-300" },
    { label: ctrans("Suspended"), value: insights.value.offer_counts.suspended, bar: "bg-red-400" },
])

const statusShare = (value: number) => insights.value.offer_counts.total > 0 ? (value / insights.value.offer_counts.total) * 100 : 0

const isTopOffersOpen = ref(true)
const isLeastOffersOpen = ref(true)

const activeMetric = ref<"redemptions" | "discounted_amount">("redemptions")

const trendBars = computed(() => {
    const trend = insights.value.trend
    const metric = activeMetric.value
    const max = Math.max(...trend.map(record => record[metric]), 1)

    return trend.map(record => ({
        label: record.period,
        height: `${(record[metric] / max) * 100}%`,
        tooltip: metric === "redemptions"
            ? `${record.period} — ${locale.number(record.redemptions)} ${ctrans("redemptions")}`
            : `${record.period} — ${currency(record.discounted_amount)}`,
    }))
})

const offerRoute = (offer: { slug: string }, extraParams?: {}) => {
    return route("grp.org.shops.show.discounts.offers.show", {
        organisation: (route().params as RouteParams).organisation,
        shop: (route().params as RouteParams).shop,
        offer: offer.slug,
        ...extraParams
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-6">
        <DashboardSettings :intervals="intervals" :settings="{}" currentTab="insights" />
    </div>

    <!-- Section: campaign & type filters -->
    <div class="px-6 pt-1 pb-2 flex flex-wrap items-center gap-2">
        <Select
            v-model="selectedCampaign"
            :options="campaignOptions"
            optionLabel="label"
            optionValue="value"
            :filter="filters.campaigns.length > 8"
            :placeholder="ctrans('All campaigns')"
            showClear
            size="small"
            class="w-64"
            @change="applyFilters" />

        <Select
            v-model="selectedType"
            :options="typeOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="ctrans('All coupon types')"
            showClear
            size="small"
            class="w-56"
            @change="applyFilters" />

        <LoadingIcon v-if="isLoadingFilter" class="text-[--app-accent-strong]" />
    </div>

    <div class="relative px-6 pb-6 flex flex-col gap-y-4">
        <div v-if="isLoadingOnTable" class="absolute inset-0 bg-white/50 flex items-center justify-center z-20 rounded">
            <LoadingIcon class="text-[--app-accent-strong] text-3xl" />
        </div>

        <!-- Section: KPI -->
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(kpi, idxKpi) in kpis"
                :key="idxKpi"
                v-tooltip="kpi.tooltip"
                class="flex items-start gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg"
                    :style="{ backgroundColor: `color-mix(in srgb, ${kpi.color} 12%, white)`, color: kpi.color }">
                    <FontAwesomeIcon :icon="kpi.icon" class="text-lg" fixed-width aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <dt class="truncate text-xs font-medium uppercase tracking-wide text-gray-500">{{ kpi.label }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tracking-tight tabular-nums text-gray-900">{{ kpi.value }}</dd>
                    <div v-if="kpi.subtitle" class="mt-1 truncate text-xs text-gray-500">{{ kpi.subtitle }}</div>
                </div>
            </div>
        </dl>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Card: coupon status breakdown -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-baseline justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Coupon status") }}</h3>
                    <span class="text-xs text-gray-500 tabular-nums">{{ ctrans("Total") }} {{ locale.number(insights.offer_counts.total) }}</span>
                </div>
                <div class="mt-4 flex h-2.5 gap-px overflow-hidden rounded-full bg-gray-100">
                    <div v-for="(status, idxStatus) in statusBreakdown" :key="idxStatus"
                        v-tooltip.top="`${status.label}: ${locale.number(status.value)} (${statusShare(status.value).toFixed(1)}%)`"
                        class="h-full cursor-pointer transition-opacity hover:opacity-70"
                        :class="status.bar" :style="{ width: statusShare(status.value) + '%' }" />
                </div>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li v-for="(status, idxStatus) in statusBreakdown" :key="idxStatus" class="flex items-center justify-between gap-x-2">
                        <span class="flex items-center gap-2 text-gray-600">
                            <span class="h-2.5 w-2.5 rounded-full" :class="status.bar" />
                            {{ status.label }}
                        </span>
                        <span class="tabular-nums">
                            <span class="font-semibold text-gray-900">{{ locale.number(status.value) }}</span>
                            <span class="ml-2 inline-block w-12 text-right text-xs text-gray-400">{{ statusShare(status.value).toFixed(1) }}%</span>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Section: redemption trend -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Trend") }}</h3>
                    <div class="flex rounded-lg bg-gray-100 p-1">
                        <button
                            type="button"
                            @click="activeMetric = 'redemptions'"
                            class="rounded-md px-3 py-1.5 text-left transition"
                            :class="activeMetric === 'redemptions' ? 'bg-white shadow-sm' : 'opacity-60 hover:opacity-100'">
                            <div class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                <span class="inline-block h-2 w-2 rounded-full bg-[--app-accent]" />
                                {{ ctrans("Redemptions") }}
                            </div>
                            <div class="text-lg font-semibold tabular-nums text-gray-900">{{ locale.number(insights.totals.redemptions) }}</div>
                        </button>
                        <button
                            type="button"
                            @click="activeMetric = 'discounted_amount'"
                            class="rounded-md px-3 py-1.5 text-left transition"
                            :class="activeMetric === 'discounted_amount' ? 'bg-white shadow-sm' : 'opacity-60 hover:opacity-100'">
                            <div class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                <span class="inline-block h-2 w-2 rounded-full bg-emerald-500" />
                                {{ ctrans("Discount given") }}
                            </div>
                            <div class="text-lg font-semibold tabular-nums text-gray-900">{{ currency(insights.totals.discounted_amount) }}</div>
                        </button>
                    </div>
                </div>

                <div v-if="insights.trend.length" class="mt-5">
                    <div class="relative h-44">
                        <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
                            <div v-for="gridLine in 4" :key="gridLine" class="border-t border-dashed border-gray-100" />
                        </div>
                        <div class="relative flex h-full items-end gap-1 border-b border-gray-200">
                            <div
                                v-for="(bar, idxBar) in trendBars"
                                :key="idxBar"
                                v-tooltip.top="bar.tooltip"
                                class="group flex h-full min-w-0 flex-1 cursor-pointer items-end rounded-t hover:bg-gray-50">
                                <div
                                    class="w-full rounded-t opacity-80 transition group-hover:opacity-100"
                                    :class="activeMetric === 'redemptions' ? 'bg-[--app-accent]' : 'bg-emerald-500'"
                                    :style="{ height: bar.height }" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-gray-400 tabular-nums">
                        <span>{{ trendBars[0].label }}</span>
                        <span>{{ trendBars[trendBars.length - 1].label }}</span>
                    </div>
                </div>
                <div v-else class="mt-6 flex h-44 items-center justify-center text-sm text-gray-400">
                    {{ ctrans("No redemptions in the selected period") }}
                </div>
            </div>
        </div>

        <!-- Section: top & least effective -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <button type="button" @click="isTopOffersOpen = !isTopOffersOpen"
                    class="flex w-full items-center justify-between text-sm font-medium text-gray-400 hover:text-gray-600"
                    :class="{ 'mb-2': isTopOffersOpen }">
                    <span>
                        <FontAwesomeIcon icon="fal fa-trophy" class="mr-1 text-amber-500" fixed-width aria-hidden="true" />
                        {{ ctrans("Top performing coupons") }}
                    </span>
                    <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-transform" :class="{ '-rotate-180': isTopOffersOpen }" fixed-width aria-hidden="true" />
                </button>
                <template v-if="isTopOffersOpen">
                <ul v-if="insights.top_offers.length" class="divide-y divide-gray-100">
                    <li v-for="offer in insights.top_offers" :key="offer.slug" class="py-2 flex items-center justify-between gap-x-4">
                        <div class="min-w-0">
                            <Link :href="offerRoute(offer)" class="primaryLink">{{ offer.name }}</Link>
                            <div class="text-xs text-gray-400">{{ locale.number(offer.redemptions) }} {{ ctrans("redemptions") }} · {{ locale.number(offer.customers) }} {{ ctrans("customers") }}</div>
                        </div>
                        <div class="text-right tabular-nums shrink-0">
                            <div class="font-semibold">{{ currency(offer.revenue_net_amount) }}</div>
                            <div class="text-xs text-red-400">-{{ currency(offer.discounted_amount) }}</div>
                        </div>
                    </li>
                </ul>
                <div v-else class="py-6 text-center text-sm text-gray-400">{{ ctrans("No redemptions in the selected period") }}</div>
                </template>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <button type="button" @click="isLeastOffersOpen = !isLeastOffersOpen"
                    class="flex w-full items-center justify-between text-sm font-medium text-gray-400 hover:text-gray-600"
                    :class="{ 'mb-2': isLeastOffersOpen }">
                    <span>
                        <FontAwesomeIcon icon="fal fa-thumbs-down" class="mr-1 text-gray-500" fixed-width aria-hidden="true" />
                        {{ ctrans("Least effective active coupons") }}
                    </span>
                    <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-transform" :class="{ '-rotate-180': isLeastOffersOpen }" fixed-width aria-hidden="true" />
                </button>
                <template v-if="isLeastOffersOpen">
                <ul v-if="insights.least_offers.length" class="divide-y divide-gray-100">
                    <li v-for="offer in insights.least_offers" :key="offer.slug" class="py-2 flex items-center justify-between gap-x-4">
                        <div class="min-w-0">
                            <Link :href="offerRoute(offer)" class="primaryLink">{{ offer.name }}</Link>
                            <div class="text-xs text-gray-400">{{ locale.number(offer.redemptions) }} {{ ctrans("redemptions") }} · {{ locale.number(offer.customers) }} {{ ctrans("customers") }}</div>
                        </div>
                        <div class="text-right tabular-nums shrink-0">
                            <div class="font-semibold">{{ currency(offer.revenue_net_amount) }}</div>
                            <div class="text-xs text-red-400">-{{ currency(offer.discounted_amount) }}</div>
                        </div>
                    </li>
                </ul>
                <div v-else class="py-6 text-center text-sm text-gray-400">{{ ctrans("No active coupons") }}</div>
                </template>
            </div>
        </div>

        <!-- Section: per-coupon table -->
        <Table :resource="offers" :useTopPagination="true">
            <template #cell(name)="{ item: offer }">
                <Link :href="offerRoute(offer)" class="primaryLink">
                    {{ offer.name }}
                </Link>
                <div v-if="offer.offer_campaign_name" class="text-xs text-gray-400">{{ offer.offer_campaign_name }}</div>
            </template>

            <template #cell(discounted_amount)="{ item: offer }">
                <span class="tabular-nums">{{ currency(offer.discounted_amount) }}</span>
            </template>

            <template #cell(avg_discount)="{ item: offer }">
                <span class="tabular-nums">{{ currency(offer.avg_discount) }}</span>
            </template>

            <template #cell(revenue_net_amount)="{ item: offer }">
                <span class="tabular-nums">{{ currency(offer.revenue_net_amount) }}</span>
            </template>

            <template #cell(last_used_at)="{ item: offer }">
                <span v-if="offer.last_used_at">{{ useFormatTime(offer.last_used_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}</span>
                <span v-else class="text-gray-400">-</span>
            </template>

            <template #cell(created_by)="{ item }">
                <Link :href="offerRoute(item, {tab: 'history'})" class="hover:opacity-80 transition text-black primaryLink">
                    <FontAwesomeIcon
                        :icon="faClock" fixed-width
                    />
                </Link>
                {{ item.created_by ?? ctrans('System') }}
            </template>
        </Table>
    </div>
</template>
