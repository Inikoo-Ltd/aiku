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
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Table as TableTS } from "@/types/Table"
import { Intervals } from "@/types/Components/Dashboard"
import { RouteParams } from "@/types/route-params"

import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler } from "chart.js"
import { Line } from "vue-chartjs"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAnalytics, faBadgePercent, faShoppingCart, faUsers, faCoin, faPiggyBank, faPercent, faTrophy, faThumbsDown, faTags } from "@fal"

library.add(faAnalytics, faBadgePercent, faShoppingCart, faUsers, faCoin, faPiggyBank, faPercent, faTrophy, faThumbsDown, faTags)

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler)

interface OfferPerformance {
    slug: string
    code: string
    name: string
    redemptions: number
    customers: number
    revenue_net_amount: number
    discounted_amount: number
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    intervals: Intervals
    filters: {
        campaigns: { slug: string, name: string, type: string }[]
        types: string[]
        campaign: string | null
        type: string | null
    }
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
    offers: TableTS
}>()

const locale = inject("locale", aikuLocaleStructure)

const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)

const selectedCampaign = ref(props.filters.campaign ?? "")
const selectedType = ref(props.filters.type ?? "")
const isLoadingFilter = ref(false)

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

const currency = (value: number) => locale.currencyFormat(props.insights.currency_code, value || 0)

const kpis = computed(() => [
    {
        label: trans("Redemptions (orders)"),
        icon: "fal fa-shopping-cart",
        color: "#6366f1",
        value: locale.number(props.insights.totals.redemptions),
        tooltip: trans("Orders where a coupon or voucher discount was actually applied"),
    },
    {
        label: trans("Customers redeeming"),
        icon: "fal fa-users",
        color: "#10b981",
        value: locale.number(props.insights.totals.customers),
        subtitle: trans("Avg savings per customer") + ": " + currency(props.insights.totals.avg_savings_per_customer),
    },
    {
        label: trans("Revenue influenced (net)"),
        icon: "fal fa-coin",
        color: "#f59e0b",
        value: currency(props.insights.totals.revenue_net_amount),
        subtitle: trans("Gross") + ": " + currency(props.insights.totals.revenue_gross_amount),
    },
    {
        label: trans("Discount given"),
        icon: "fal fa-piggy-bank",
        color: "#ef4444",
        value: currency(props.insights.totals.discounted_amount),
        subtitle: trans("Avg per redemption") + ": " + currency(props.insights.totals.avg_discount),
    },
    {
        label: trans("Margin impact"),
        icon: "fal fa-percent",
        color: "#8b5cf6",
        value: props.insights.totals.discount_rate + "%",
        tooltip: trans("Discount given as percentage of revenue before discount"),
    },
    {
        label: trans("Conversion rate"),
        icon: "fal fa-badge-percent",
        color: "#3b82f6",
        value: props.insights.totals.conversion_rate + "%",
        subtitle: `${locale.number(props.insights.offer_counts.redeemed)} / ${locale.number(props.insights.offer_counts.total)} ` + trans("coupons redeemed"),
        tooltip: trans("Coupons redeemed at least once divided by total coupons"),
    },
])

const statusBreakdown = computed(() => [
    { label: trans("Active"), value: props.insights.offer_counts.active, class: "text-green-600" },
    { label: trans("Not yet started"), value: props.insights.offer_counts.in_process, class: "text-amber-500" },
    { label: trans("Expired / finished"), value: props.insights.offer_counts.finished, class: "text-gray-500" },
    { label: trans("Suspended"), value: props.insights.offer_counts.suspended, class: "text-red-500" },
])

const chartData = computed(() => ({
    labels: props.insights.trend.map(record => record.period),
    datasets: [
        {
            label: trans("Redemptions"),
            data: props.insights.trend.map(record => record.redemptions),
            borderColor: "#6366f1",
            backgroundColor: "#6366f133",
            fill: true,
            tension: 0.3,
            yAxisID: "y",
        },
        {
            label: trans("Discount given"),
            data: props.insights.trend.map(record => record.discounted_amount),
            borderColor: "#ef4444",
            backgroundColor: "#ef444433",
            fill: false,
            tension: 0.3,
            yAxisID: "y1",
        },
    ],
}))

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: "index" as const,
        intersect: false,
    },
    plugins: {
        legend: {
            position: "top" as const,
        },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    if (context.dataset.yAxisID === "y1") {
                        return `${context.dataset.label}: ${currency(context.parsed.y)}`
                    }
                    return `${context.dataset.label}: ${locale.number(context.parsed.y)}`
                },
            },
        },
    },
    scales: {
        y: {
            type: "linear" as const,
            position: "left" as const,
            beginAtZero: true,
            title: { display: true, text: trans("Redemptions") },
        },
        y1: {
            type: "linear" as const,
            position: "right" as const,
            beginAtZero: true,
            grid: { drawOnChartArea: false },
            title: { display: true, text: trans("Discount given") },
        },
    },
}))

const offerRoute = (offer: { slug: string }) => {
    return route("grp.org.shops.show.discounts.offers.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).shop,
        offer.slug,
    ])
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
        <select
            v-model="selectedCampaign"
            @change="applyFilters"
            class="rounded border-gray-300 text-sm py-1.5 pr-8 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">{{ trans("All campaigns") }}</option>
            <option v-for="campaign in filters.campaigns" :key="campaign.slug" :value="campaign.slug">
                {{ campaign.name }}
            </option>
        </select>

        <select
            v-model="selectedType"
            @change="applyFilters"
            class="rounded border-gray-300 text-sm py-1.5 pr-8 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">{{ trans("All coupon types") }}</option>
            <option v-for="type in filters.types" :key="type" :value="type">
                {{ type }}
            </option>
        </select>

        <LoadingIcon v-if="isLoadingFilter" class="text-indigo-500" />
    </div>

    <div class="relative px-6 pb-6 flex flex-col gap-y-4">
        <div v-if="isLoadingOnTable" class="absolute inset-0 bg-white/50 flex items-center justify-center z-20 rounded">
            <LoadingIcon class="text-indigo-500 text-3xl" />
        </div>

        <!-- Section: KPI -->
        <dl class="grid grid-cols-1 gap-2 lg:gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(kpi, idxKpi) in kpis"
                :key="idxKpi"
                v-tooltip="kpi.tooltip"
                class="relative overflow-hidden rounded-lg border border-gray-200 bg-white px-4 py-5 shadow-sm sm:p-6 sm:pb-4">
                <dt class="truncate text-sm font-medium text-gray-400">{{ kpi.label }}</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-tight tabular-nums flex gap-x-2 items-center" :style="{ color: kpi.color }">
                    <FontAwesomeIcon :icon="kpi.icon" class="text-xl" fixed-width aria-hidden="true" />
                    {{ kpi.value }}
                </dd>
                <div v-if="kpi.subtitle" class="mt-1 text-sm font-medium text-gray-500 truncate">{{ kpi.subtitle }}</div>
            </div>

            <!-- Card: coupon status breakdown -->
            <div class="relative overflow-hidden rounded-lg border border-gray-200 bg-white px-4 py-5 shadow-sm sm:p-6 sm:pb-4">
                <dt class="truncate text-sm font-medium text-gray-400">{{ trans("Coupon status") }}</dt>
                <dd class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm tabular-nums">
                    <div v-for="(status, idxStatus) in statusBreakdown" :key="idxStatus" class="flex justify-between gap-x-2">
                        <span class="text-gray-500">{{ status.label }}</span>
                        <span class="font-semibold" :class="status.class">{{ locale.number(status.value) }}</span>
                    </div>
                </dd>
                <div class="mt-2 text-sm font-medium text-gray-500">
                    {{ trans("Total") }}: {{ locale.number(insights.offer_counts.total) }}
                </div>
            </div>
        </dl>

        <!-- Section: redemption trend -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-medium text-gray-400 mb-2">
                <FontAwesomeIcon icon="fal fa-analytics" class="mr-1" fixed-width aria-hidden="true" />
                {{ trans("Redemptions over time") }}
            </h3>
            <div v-if="insights.trend.length" class="h-72">
                <Line :data="chartData" :options="chartOptions" />
            </div>
            <div v-else class="h-32 flex items-center justify-center text-sm text-gray-400">
                {{ trans("No redemptions in the selected period") }}
            </div>
        </div>

        <!-- Section: top & least effective -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-medium text-gray-400 mb-2">
                    <FontAwesomeIcon icon="fal fa-trophy" class="mr-1 text-amber-500" fixed-width aria-hidden="true" />
                    {{ trans("Top performing coupons") }}
                </h3>
                <ul v-if="insights.top_offers.length" class="divide-y divide-gray-100">
                    <li v-for="offer in insights.top_offers" :key="offer.slug" class="py-2 flex items-center justify-between gap-x-4">
                        <div class="min-w-0">
                            <Link :href="offerRoute(offer)" class="primaryLink">{{ offer.name }}</Link>
                            <div class="text-xs text-gray-400">{{ locale.number(offer.redemptions) }} {{ trans("redemptions") }} · {{ locale.number(offer.customers) }} {{ trans("customers") }}</div>
                        </div>
                        <div class="text-right tabular-nums shrink-0">
                            <div class="font-semibold">{{ currency(offer.revenue_net_amount) }}</div>
                            <div class="text-xs text-red-400">-{{ currency(offer.discounted_amount) }}</div>
                        </div>
                    </li>
                </ul>
                <div v-else class="py-6 text-center text-sm text-gray-400">{{ trans("No redemptions in the selected period") }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-medium text-gray-400 mb-2">
                    <FontAwesomeIcon icon="fal fa-thumbs-down" class="mr-1 text-gray-500" fixed-width aria-hidden="true" />
                    {{ trans("Least effective active coupons") }}
                </h3>
                <ul v-if="insights.least_offers.length" class="divide-y divide-gray-100">
                    <li v-for="offer in insights.least_offers" :key="offer.slug" class="py-2 flex items-center justify-between gap-x-4">
                        <div class="min-w-0">
                            <Link :href="offerRoute(offer)" class="primaryLink">{{ offer.name }}</Link>
                            <div class="text-xs text-gray-400">{{ locale.number(offer.redemptions) }} {{ trans("redemptions") }} · {{ locale.number(offer.customers) }} {{ trans("customers") }}</div>
                        </div>
                        <div class="text-right tabular-nums shrink-0">
                            <div class="font-semibold">{{ currency(offer.revenue_net_amount) }}</div>
                            <div class="text-xs text-red-400">-{{ currency(offer.discounted_amount) }}</div>
                        </div>
                    </li>
                </ul>
                <div v-else class="py-6 text-center text-sm text-gray-400">{{ trans("No active coupons") }}</div>
            </div>
        </div>

        <!-- Section: per-coupon table -->
        <Table :resource="offers">
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
        </Table>
    </div>
</template>
