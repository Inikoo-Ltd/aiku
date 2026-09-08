<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faGoogle } from '@fortawesome/free-brands-svg-icons'
library.add(faGoogle)
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    campaign: {
        reference: string
        name: string
        status: string | null
        channel_type: string | null
        budget_amount: number | null
        currency: string
        fetched_at: string | null
        spend_30d: number
        spend_total: number
        shop_currency: string
    }
    ad_groups: {
        id: string
        name: string | null
        status: string | null
        ads: {
            id: string
            type: string | null
            status: string | null
            final_urls: string[]
            headlines: string[]
            descriptions: string[]
        }[]
        keywords: { text: string | null, match_type: string | null, status: string | null }[]
    }[]
    metrics_30d: { impressions: number, clicks: number, cost: number, conversions: number, conversions_value: number } | null
    metrics_daily: { date: string, impressions: number, clicks: number, cost: number, conversions: number }[]
    attribution: { customers: number, purchases: number, revenue: number, cost: number, roas: number | null }
    costs: { date: string, amount: number }[]
}>()

const locale = useLocaleStore()
const money = (currency: string, value: number) => locale.currencyFormat(currency, value)

const ctr = (impressions: number, clicks: number) => impressions > 0 ? `${((clicks / impressions) * 100).toFixed(2)}%` : '—'
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>

    <div class="px-4 py-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="text-sm font-medium text-gray-800">{{ campaign.name }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ campaign.reference }}</div>

            <div class="mt-5 space-y-3 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-400">{{ trans('Status') }}</span>
                    <span v-if="!campaign.status" class="text-gray-300">—</span>
                    <span v-else :class="campaign.status === 'ENABLED' ? 'text-green-600' : 'text-gray-500'">
                        {{ campaign.status }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">{{ trans('Channel') }}</span>
                    <span class="text-gray-700">{{ campaign.channel_type ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">{{ trans('Daily budget') }}</span>
                    <span class="text-gray-700 tabular-nums">
                        {{ campaign.budget_amount !== null ? money(campaign.currency, campaign.budget_amount) : '—' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">{{ trans('Last fetched') }}</span>
                    <span class="text-gray-700">{{ campaign.fetched_at ?? '—' }}</span>
                </div>
            </div>
        </div>

        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="text-sm font-medium text-gray-800">{{ trans('Spend') }}</div>
            <div class="mt-5 grid grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-gray-400">{{ trans('Last 30 days') }}</div>
                    <div class="text-lg text-gray-900 tabular-nums">{{ money(campaign.shop_currency, campaign.spend_30d) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ trans('Total') }}</div>
                    <div class="text-lg text-gray-900 tabular-nums">{{ money(campaign.shop_currency, campaign.spend_total) }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="text-sm font-medium text-gray-800">{{ trans('Performance (30 days)') }}</div>
            <div v-if="metrics_30d" class="mt-5 grid grid-cols-2 gap-4 text-xs">
                <div>
                    <div class="text-gray-400">{{ trans('Impressions') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ metrics_30d.impressions }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('Clicks') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ metrics_30d.clicks }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('CTR') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ ctr(metrics_30d.impressions, metrics_30d.clicks) }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('Cost') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ money(campaign.currency, metrics_30d.cost) }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('Conversions') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ metrics_30d.conversions }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('Conversion value') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ money(campaign.currency, metrics_30d.conversions_value) }}</div>
                </div>
            </div>
            <div v-else class="mt-5 text-xs text-gray-400">{{ trans('No data from Google yet, the nightly fetch fills this in.') }}</div>
        </div>

        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="text-sm font-medium text-gray-800">{{ trans('Aiku attribution') }}</div>
            <div class="mt-5 grid grid-cols-2 gap-4 text-xs">
                <div>
                    <div class="text-gray-400">{{ trans('Customers') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ attribution.customers }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('Purchases') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ attribution.purchases }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('Revenue') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ money(campaign.shop_currency, attribution.revenue) }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('ROAS') }}</div>
                    <div class="text-gray-900 tabular-nums">{{ attribution.roas !== null ? attribution.roas.toFixed(2) : '—' }}</div>
                </div>
            </div>
        </div>

        <div class="md:col-span-2 rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="text-sm font-medium text-gray-800">{{ trans('Ads') }}</div>
            <div v-if="ad_groups.length" class="mt-3 space-y-4">
                <div v-for="group in ad_groups" :key="group.id" class="rounded-lg ring-1 ring-gray-100 p-3">
                    <div class="text-xs font-medium text-gray-700">
                        {{ group.name ?? group.id }}
                        <span class="text-gray-400 font-normal">{{ group.status }}</span>
                    </div>
                    <div v-for="ad in group.ads" :key="ad.id" class="mt-2 pl-3 border-l border-gray-100 text-xs">
                        <div class="text-gray-400">{{ ad.type }} · {{ ad.status }}</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <span v-for="(headline, i) in ad.headlines" :key="i" class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600">{{ headline }}</span>
                        </div>
                        <div v-for="(description, i) in ad.descriptions" :key="i" class="mt-1 text-gray-500">{{ description }}</div>
                        <a v-for="(url, i) in ad.final_urls" :key="i" :href="url" target="_blank" class="mt-1 block text-blue-600 truncate">{{ url }}</a>
                    </div>
                </div>
            </div>
            <div v-else class="mt-3 text-xs text-gray-400">{{ trans('No data from Google yet, the nightly fetch fills this in.') }}</div>
        </div>

        <div class="md:col-span-2 rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="text-sm font-medium text-gray-800">{{ trans('Keywords') }}</div>
            <table v-if="ad_groups.some(group => group.keywords.length)" class="mt-3 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Text') }}</th>
                        <th class="text-left font-normal py-1.5 px-2">{{ trans('Match type') }}</th>
                        <th class="text-left font-normal py-1.5 pl-2">{{ trans('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="group in ad_groups" :key="group.id">
                        <tr v-for="(keyword, i) in group.keywords" :key="i" class="border-b border-gray-50 text-gray-600">
                            <td class="py-2 pr-2">{{ keyword.text }}</td>
                            <td class="px-2">{{ keyword.match_type }}</td>
                            <td class="pl-2">{{ keyword.status }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div v-else class="mt-3 text-xs text-gray-400">{{ trans('No data from Google yet, the nightly fetch fills this in.') }}</div>
        </div>

        <div class="md:col-span-2 rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="text-sm font-medium text-gray-800">
                {{ metrics_daily.length ? trans('Daily performance (last 30 days)') : trans('Daily spend (last 90 days)') }}
            </div>
            <table v-if="metrics_daily.length" class="mt-3 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Date') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Impressions') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Clicks') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Cost') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Conversions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="day in metrics_daily" :key="day.date" class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2">{{ day.date }}</td>
                        <td class="text-right px-2 tabular-nums">{{ day.impressions }}</td>
                        <td class="text-right px-2 tabular-nums">{{ day.clicks }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(campaign.currency, day.cost) }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ day.conversions }}</td>
                    </tr>
                </tbody>
            </table>
            <table v-else class="mt-3 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Date') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="cost in costs" :key="cost.date" class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2">{{ cost.date }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ money(campaign.shop_currency, cost.amount) }}</td>
                    </tr>
                    <tr v-if="!costs.length">
                        <td colspan="2" class="py-4 text-center text-gray-400">{{ trans('No spend recorded.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
