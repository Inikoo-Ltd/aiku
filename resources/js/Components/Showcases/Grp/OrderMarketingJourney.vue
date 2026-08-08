<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBullseyeArrow, faCartPlus, faShoppingBasket, faUserPlus, faCheckCircle } from '@fal'
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

library.add(faBullseyeArrow, faCartPlus, faShoppingBasket, faUserPlus, faCheckCircle)

interface JourneyEvent {
    id: string
    type: 'touch' | 'registration' | 'order' | 'product'
    datetime: string
    label: string
    is_paid?: boolean
    campaign_name?: string | null
    attributed?: boolean
    quantity?: number
}

const props = defineProps<{
    data?: {
        is_first_order: boolean
        events: JourneyEvent[]
        attribution: { label: string, share: number, campaign: string | null }[]
        currency_code: string
    }
}>()

const icon = (event: JourneyEvent) => ({
    touch: 'fal fa-bullseye-arrow',
    registration: 'fal fa-user-plus',
    product: 'fal fa-cart-plus',
    order: event.id === 'order-submitted' ? 'fal fa-check-circle' : 'fal fa-shopping-basket',
}[event.type])

const dotClass = (event: JourneyEvent) => ({
    touch: event.attributed ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400',
    registration: 'bg-amber-100 text-amber-600',
    product: 'bg-gray-100 text-gray-500',
    order: event.id === 'order-submitted' ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-500',
}[event.type])
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
            <div v-else class="mt-1 text-sm text-gray-400">
                {{ trans('No channel credited for this order') }}
            </div>
            <div v-if="data.is_first_order" class="mt-1 text-xs text-amber-600">
                {{ trans("This customer's first order - the timeline starts with the touches that led to registration.") }}
            </div>
        </div>

        <div v-if="!data.events.length" class="text-sm text-gray-400">
            {{ trans('No marketing activity recorded for this order') }}
        </div>

        <ol v-else class="relative border-l border-gray-200 ml-3">
            <li v-for="event in data.events" :key="event.id" class="mb-5 ml-6">
                <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white"
                      :class="dotClass(event)">
                    <FontAwesomeIcon :icon="icon(event)" class="text-xs" fixed-width />
                </span>

                <div class="flex items-baseline justify-between gap-4">
                    <div :class="event.type === 'touch' && !event.attributed ? 'text-gray-400' : 'text-gray-700'">
                        <span class="text-sm">{{ event.label }}</span>
                        <span v-if="event.type === 'product' && event.quantity" class="ml-1 text-xs text-gray-400">
                            × {{ event.quantity }}
                        </span>
                        <span v-if="event.campaign_name" class="ml-1 text-xs text-gray-400">{{ event.campaign_name }}</span>
                        <span v-if="event.type === 'touch' && event.attributed"
                              class="ml-1 text-xs text-indigo-500">{{ trans('credited') }}</span>
                    </div>
                    <div class="whitespace-nowrap text-xs tabular-nums text-gray-400">
                        {{ useFormatTime(event.datetime, { formatTime: 'hms' }) }}
                    </div>
                </div>
            </li>
        </ol>
    </div>
</template>
