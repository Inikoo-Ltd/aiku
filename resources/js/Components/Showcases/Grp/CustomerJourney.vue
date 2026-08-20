<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 07 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBullseyeArrow, faFileInvoiceDollar, faRoute } from '@fal'
import { useFormatTime } from '@/Composables/useFormatTime'
import { useLocaleStore } from '@/Stores/locale'
import { trans } from 'laravel-vue-i18n'

library.add(faBullseyeArrow, faFileInvoiceDollar, faRoute)

interface JourneyEvent {
    id: string
    type: 'touch' | 'invoice'
    datetime: string
    label: string
    channel?: string
    is_paid?: boolean
    campaign_ref?: string | null
    campaign_name?: string | null
    in_window?: boolean
    days_to_next_purchase?: number | null
    net_amount?: number
}

const props = defineProps<{
    data?: {
        events: JourneyEvent[]
        omitted_events: number
        attribution: { label: string, share: number, campaign: string | null }[]
        attribution_window_days: number
        currency_code: string
    }
}>()

const locale = useLocaleStore()
</script>

<template>
    <div v-if="data" class="px-4 py-6 max-w-4xl">
        <div class="mb-6 border-b border-gray-200 pb-4">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ trans('Attribution') }}</div>
            <div v-if="data.attribution.length" class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span v-for="(item, index) in data.attribution" :key="index" class="text-sm text-gray-700">
                    {{ item.label }}<span v-if="item.campaign" class="text-gray-400"> / {{ item.campaign }}</span>
                    <span class="ml-1 tabular-nums text-gray-500">{{ item.share.toFixed(2) }}</span>
                </span>
            </div>
            <div v-else class="mt-1 text-sm text-gray-400">{{ trans('No attribution recorded') }}</div>
            <div class="mt-1 text-xs text-gray-400">
                {{ trans('Attribution window') }}: {{ data.attribution_window_days }} {{ trans('days') }}
            </div>
        </div>

        <div v-if="!data.events.length" class="text-sm text-gray-400">
            {{ trans('No marketing touches or invoices recorded for this customer') }}
        </div>

        <div v-if="data.omitted_events > 0" class="mb-4 text-xs text-gray-400">
            {{ trans('Showing the most recent :shown events, :omitted older ones are not listed', { shown: data.events.length, omitted: data.omitted_events }) }}
        </div>

        <ol v-if="data.events.length" class="relative border-l border-gray-200 ml-3">
            <li v-for="event in data.events" :key="event.id" class="mb-6 ml-6">
                <span
                    class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white"
                    :class="event.type === 'invoice' ? 'bg-emerald-100 text-emerald-600' : (event.in_window ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400')">
                    <FontAwesomeIcon :icon="event.type === 'invoice' ? 'fal fa-file-invoice-dollar' : 'fal fa-bullseye-arrow'" class="text-xs" fixed-width />
                </span>

                <div class="flex items-baseline justify-between gap-4">
                    <div :class="event.type === 'touch' && !event.in_window ? 'text-gray-400' : 'text-gray-700'">
                        <span class="text-sm">{{ event.label }}</span>
                        <span v-if="event.campaign_name || event.campaign_ref" class="ml-1 text-xs text-gray-400">
                            {{ event.campaign_name ?? event.campaign_ref }}
                        </span>
                    </div>
                    <div class="whitespace-nowrap text-xs tabular-nums text-gray-400">
                        <span v-if="event.type === 'invoice'" class="mr-2 text-sm text-emerald-600">
                            {{ locale.currencyFormat(data.currency_code, event.net_amount ?? 0) }}
                        </span>
                        {{ useFormatTime(event.datetime) }}
                    </div>
                </div>

                <div v-if="event.type === 'touch' && !event.in_window" class="text-xs text-gray-400">
                    {{ trans('Outside attribution window') }}
                </div>
            </li>
        </ol>
    </div>
</template>
