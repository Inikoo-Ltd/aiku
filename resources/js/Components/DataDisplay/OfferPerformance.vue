<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 07 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { useLocaleStore } from '@/Stores/locale'

const props = defineProps<{
    data: {
        period_label: string
        currency_code: string
        reach: { emailed: number, customers: number }
        offers: {
            name: string
            code: string | null
            orders: number
            customers: number
            discount: number
            revenue: number
            emailed_customers: number
            uptake_emailed: number | null
            uptake_rest: number | null
            lift: number | null
        }[]
    }
}>()

const locale = useLocaleStore()
const money = (value: number) => locale.currencyFormat(props.data.currency_code, value)
const pct = (value: number | null) => value === null ? '—' : value.toFixed(2) + '%'

/* Below 1 the offer was redeemed no more often by the people we emailed than by everybody else,
   which means the email did not sell it. */
const liftClass = (lift: number | null) =>
    lift === null ? 'text-gray-300' : lift >= 1.2 ? 'text-[#006300]' : lift < 1 ? 'text-[#d03b3b]' : 'text-gray-600'
</script>

<template>
    <div class="px-4 py-4 space-y-3">
        <p class="text-xs text-gray-500 max-w-3xl">
            {{ trans('What each offer was redeemed for, and whether emailing about it made any difference. Uptake compares the customers we emailed in this period against every other customer of the shop — an offer redeemed just as often by people we did not email was not sold by the email.') }}
        </p>
        <p class="text-xs text-gray-400 max-w-3xl">
            {{ trans('The comparison group is all other customers, not only those eligible for the offer, so read the lift as a signal rather than a measurement. Emailed this period:') }}
            {{ locale.number(data.reach.emailed) }} {{ trans('of') }} {{ locale.number(data.reach.customers) }}.
        </p>

        <div v-if="data.offers.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5 overflow-x-auto">
            <span class="text-sm font-medium text-gray-800">{{ trans('Offers redeemed') }}</span>
            <span class="ml-2 text-xs text-gray-400">{{ data.period_label.toLowerCase() }}</span>

            <table class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Offer') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Orders') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Customers') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Given away') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Order value') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Uptake emailed') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Uptake others') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Lift') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="offer in data.offers" :key="offer.name + (offer.code ?? '')"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2 text-gray-700">
                            {{ offer.name }}
                            <span v-if="offer.code" class="text-gray-400">· {{ offer.code }}</span>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ locale.number(offer.orders) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ locale.number(offer.customers) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(offer.discount) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(offer.revenue) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ pct(offer.uptake_emailed) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ pct(offer.uptake_rest) }}</td>
                        <td class="text-right pl-2 tabular-nums" :class="liftClass(offer.lift)">
                            {{ offer.lift !== null ? offer.lift.toFixed(2) + '×' : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="rounded-xl ring-1 ring-gray-200 bg-white p-5 text-xs text-gray-500">
            {{ trans('No offer was redeemed in this period.') }}
        </div>
    </div>
</template>
