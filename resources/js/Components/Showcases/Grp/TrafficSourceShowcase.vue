<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Tue, 11 Aug 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { route } from 'ziggy-js'
import { useLocaleStore } from '@/Stores/locale'
import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    data: {
        currency_code: string
        channel: {
            name: string
            type: string
            type_label: string
            group_label: string
            is_paid: boolean
            status: boolean
        }
        stats: {
            visits: number
            customers: number
            purchases: number
            revenue: number
            pending: number
            cost: number
            cost_is_estimated: boolean
            roas: number | null
            cac: number | null
            per_customer: number | null
        }
        activity: {
            first_visit: string | null
            last_visit: string | null
        }
        campaigns?: {
            name: string
            reference: string
            owner?: string
            customers: number
            purchases: number
            revenue: number
            cost: number
            roas: number | null
        }[]
        campaigns_total?: number
        children?: {
            name: string
            slug: string
            revenue: number
            pending: number
            registrations: number
            orders: number
            visits: number
            route: routeType | null
        }[]
        children_label?: string
    }
}>()

const locale = useLocaleStore()
const money = (value: number) => locale.currencyFormat(props.data.currency_code, value)

/* Share-weighted counts: a customer touched by three channels is a third of one here. */
const share = (value: number) => Number.isInteger(value) ? locale.number(value) : value.toFixed(1)

/* Above the shop a campaign needs saying whose it is; a shop's own page already knows. */
const campaignsHaveOwner = computed(() => props.data.campaigns?.some(campaign => campaign.owner) ?? false)

const campaignsAreTruncated = computed(
    () => (props.data.campaigns_total ?? 0) > (props.data.campaigns?.length ?? 0)
)
</script>

<template>
    <div class="px-4 py-5 space-y-4">
        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm font-medium text-gray-800">{{ data.channel.name }}</div>
                    <div class="mt-1 text-xs text-gray-400">
                        {{ data.channel.group_label }} · {{ data.channel.is_paid ? trans('Paid') : trans('Unpaid') }}
                    </div>
                </div>
                <span class="text-xs rounded-md px-2 py-1"
                      :class="data.channel.status ? 'bg-gray-100 text-gray-600' : 'bg-gray-50 text-gray-400'">
                    {{ data.channel.status ? trans('Active') : trans('Inactive') }}
                </span>
            </div>

            <!-- Everything here is since attribution started recording, not a period: the marketing
                 dashboard answers the period question and this page would only disagree with it. -->
            <div class="mt-5 grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4">
                <div>
                    <div class="text-xs text-gray-400">{{ trans('Visits') }}</div>
                    <div class="text-lg text-gray-900 tabular-nums">{{ locale.number(data.stats.visits) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ trans('Customers') }}</div>
                    <div class="text-lg text-gray-900 tabular-nums">{{ share(data.stats.customers) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ trans('Orders') }}</div>
                    <div class="text-lg text-gray-900 tabular-nums">{{ share(data.stats.purchases) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ trans('Revenue') }}</div>
                    <div class="text-lg tabular-nums text-[#006300]">{{ money(data.stats.revenue) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ trans('Awaiting invoice') }}</div>
                    <div class="text-lg text-gray-500 tabular-nums">{{ money(data.stats.pending) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ trans('Spend') }}</div>
                    <div class="text-lg text-gray-900 tabular-nums">
                        <span v-if="data.stats.cost_is_estimated" class="text-xs text-gray-400 mr-1"
                              :title="trans('Estimated from emails sent')">{{ trans('est.') }}</span>{{ money(data.stats.cost) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">{{ trans('ROAS') }}</div>
                    <div class="text-lg tabular-nums"
                         :class="data.stats.roas === null ? 'text-gray-300' : data.stats.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                        {{ data.stats.roas !== null ? data.stats.roas.toFixed(2) + '×' : '—' }}
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-gray-100 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div>
                    <div class="text-gray-400">{{ trans('Cost per customer') }}</div>
                    <div class="mt-0.5 text-gray-700 tabular-nums">
                        {{ data.stats.cac !== null ? money(data.stats.cac) : '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('Revenue per customer') }}</div>
                    <div class="mt-0.5 text-gray-700 tabular-nums">
                        {{ data.stats.per_customer !== null ? money(data.stats.per_customer) : '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('First visit') }}</div>
                    <div class="mt-0.5 text-gray-700 tabular-nums">{{ data.activity.first_visit ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-gray-400">{{ trans('Last visit') }}</div>
                    <div class="mt-0.5 text-gray-700 tabular-nums">{{ data.activity.last_visit ?? '—' }}</div>
                </div>
            </div>
        </div>

        <!-- The level below, each row landing on the same channel one step down. -->
        <div v-if="data.children?.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <span class="text-sm font-medium text-gray-800">{{ data.children_label ?? trans('Breakdown') }}</span>
            <table class="mt-3 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ data.children_label ?? trans('Breakdown') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Visits') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Awaiting invoice') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Revenue') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Registrations') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Orders') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="child in data.children" :key="child.slug" class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2">
                            <Link v-if="child.route" :href="route(child.route.name, child.route.parameters)"
                                  class="text-gray-700 hover:text-gray-900 hover:underline">{{ child.name }}</Link>
                            <span v-else class="text-gray-700">{{ child.name }}</span>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ locale.number(child.visits) }}</td>
                        <td class="text-right px-2 tabular-nums text-gray-400">{{ money(child.pending) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(child.revenue) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ share(child.registrations) }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ share(child.orders) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="data.campaigns?.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <span class="text-sm font-medium text-gray-800">{{ trans('Campaigns') }}</span>
            <span v-if="campaignsAreTruncated" class="ml-2 text-xs text-gray-400">
                {{ trans('top :shown of :total', { shown: data.campaigns.length, total: data.campaigns_total }) }}
            </span>
            <table class="mt-3 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Campaign') }}</th>
                        <th v-if="campaignsHaveOwner" class="text-left font-normal py-1.5 px-2">{{ trans('Shop') }}</th>
                        <th class="text-left font-normal py-1.5 px-2">{{ trans('Reference') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Spend') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Revenue') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Customers') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Orders') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('ROAS') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="campaign in data.campaigns" :key="campaign.reference"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2 text-gray-700">{{ campaign.name }}</td>
                        <td v-if="campaignsHaveOwner" class="px-2 text-gray-500">{{ campaign.owner }}</td>
                        <td class="px-2 text-gray-400">{{ campaign.reference }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(campaign.cost) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(campaign.revenue) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ share(campaign.customers) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ share(campaign.purchases) }}</td>
                        <td class="text-right pl-2 tabular-nums"
                            :class="campaign.roas === null ? 'text-gray-300' : campaign.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                            {{ campaign.roas !== null ? campaign.roas.toFixed(2) + '×' : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
