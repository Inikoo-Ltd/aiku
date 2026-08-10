<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBullseyeArrow, faCartPlus, faShoppingBasket, faUserPlus, faCheckCircle, faChevronDown, faChevronRight } from '@fal'
import { useFormatTime } from '@/Composables/useFormatTime'
import { useLocaleStore } from '@/Stores/locale'
import { trans } from 'laravel-vue-i18n'

library.add(faBullseyeArrow, faCartPlus, faShoppingBasket, faUserPlus, faCheckCircle, faChevronDown, faChevronRight)

interface JourneyEvent {
    id: string
    type: 'touch' | 'registration' | 'order' | 'product'
    kind?: 'add' | 'up' | 'down' | 'remove'
    datetime: string
    label: string
    is_paid?: boolean
    campaign_name?: string | null
    attributed?: boolean
    quantity?: number
    basket?: number | null
}

interface ProductGroup {
    type: 'product_group'
    id: string
    datetime: string
    adds: number
    removals: number
    delta: number | null
    basket: number | null
    items: JourneyEvent[]
}

const props = defineProps<{
    data?: {
        is_first_order: boolean
        events: JourneyEvent[]
        attribution: { label: string, share: number, campaign: string | null }[]
        currency_code: string
    }
}>()

const locale = useLocaleStore()
const expanded = ref<Record<string, boolean>>({})

/* Consecutive basket changes collapse into one row - "+5 −3 products" with the value change - so a
   month of basket-building doesn't drown the two touches that matter. */
const rows = computed<(JourneyEvent | ProductGroup)[]>(() => {
    const out: (JourneyEvent | ProductGroup)[] = []
    let run: JourneyEvent[] = []
    let basketBeforeRun: number | null = null
    let lastKnownBasket: number | null = null

    const flush = () => {
        if (!run.length) return
        if (run.length === 1) {
            out.push(run[0])
        } else {
            const last = [...run].reverse().find(e => e.basket !== null && e.basket !== undefined)
            const endBasket = last?.basket ?? null
            out.push({
                type: 'product_group',
                id: 'group-' + run[0].id,
                datetime: run[run.length - 1].datetime,
                adds: run.filter(e => (e.quantity ?? 0) > 0).length,
                removals: run.filter(e => (e.quantity ?? 0) < 0).length,
                delta: endBasket !== null && basketBeforeRun !== null ? endBasket - basketBeforeRun : null,
                basket: endBasket,
                items: run,
            })
        }
        run = []
    }

    for (const event of props.data?.events ?? []) {
        if (event.type === 'product') {
            if (!run.length) basketBeforeRun = lastKnownBasket
            run.push(event)
            if (event.basket !== null && event.basket !== undefined) lastKnownBasket = event.basket
        } else {
            flush()
            out.push(event)
        }
    }
    flush()

    return out
})

const kindLabel: Record<string, string> = {
    add: trans('added'),
    up: trans('more'),
    down: trans('fewer'),
    remove: trans('removed'),
}

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

const money = (value: number, signed = false) =>
    (signed && value > 0 ? '+' : '') + locale.currencyFormat(props.data?.currency_code ?? 'GBP', value)
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
            <li v-for="row in rows" :key="row.id" class="mb-5 ml-6">
                <template v-if="row.type === 'product_group'">
                    <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white bg-gray-100 text-gray-500">
                        <FontAwesomeIcon icon="fal fa-cart-plus" class="text-xs" fixed-width />
                    </span>
                    <button type="button" class="flex w-full items-baseline justify-between gap-4 text-left"
                            @click="expanded[row.id] = !expanded[row.id]">
                        <div class="text-sm text-gray-700">
                            <FontAwesomeIcon :icon="expanded[row.id] ? 'fal fa-chevron-down' : 'fal fa-chevron-right'"
                                             class="mr-1 text-xs text-gray-400" fixed-width />
                            <span v-if="row.adds" class="text-emerald-600">+{{ row.adds }}</span>
                            <span v-if="row.removals" class="ml-1 text-red-500">−{{ row.removals }}</span>
                            {{ trans('products') }}
                            <span v-if="row.delta !== null" class="ml-1 tabular-nums"
                                  :class="row.delta >= 0 ? 'text-emerald-600' : 'text-red-500'">{{ money(row.delta, true) }}</span>
                            <span v-if="row.basket !== null" class="ml-1 text-xs text-gray-400">
                                {{ trans('basket') }} {{ money(row.basket) }}
                            </span>
                        </div>
                        <div class="whitespace-nowrap text-xs tabular-nums text-gray-400">
                            {{ useFormatTime(row.datetime, { formatTime: 'hms' }) }}
                        </div>
                    </button>
                    <ol v-if="expanded[row.id]" class="mt-2 space-y-1">
                        <li v-for="item in row.items" :key="item.id"
                            class="flex items-baseline justify-between gap-4 pl-5 text-xs text-gray-500">
                            <div>
                                <span :class="(item.quantity ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                      class="tabular-nums">{{ (item.quantity ?? 0) > 0 ? '+' : '' }}{{ item.quantity }}</span>
                                {{ item.label }}
                                <span v-if="item.kind && item.kind !== 'add'" class="text-gray-400">· {{ kindLabel[item.kind] }}</span>
                                <span v-if="item.basket !== null && item.basket !== undefined" class="text-gray-400">
                                    · {{ trans('basket') }} {{ money(item.basket) }}
                                </span>
                            </div>
                            <div class="whitespace-nowrap tabular-nums text-gray-400">
                                {{ useFormatTime(item.datetime, { formatTime: 'hms' }) }}
                            </div>
                        </li>
                    </ol>
                </template>

                <template v-else>
                    <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white"
                          :class="dotClass(row as JourneyEvent)">
                        <FontAwesomeIcon :icon="icon(row as JourneyEvent)" class="text-xs" fixed-width />
                    </span>
                    <div class="flex items-baseline justify-between gap-4">
                        <div :class="row.type === 'touch' && !(row as JourneyEvent).attributed ? 'text-gray-400' : 'text-gray-700'">
                            <span class="text-sm">{{ row.label }}</span>
                            <span v-if="row.type === 'product' && (row as JourneyEvent).quantity" class="ml-1 text-xs tabular-nums"
                                  :class="((row as JourneyEvent).quantity ?? 0) >= 0 ? 'text-gray-400' : 'text-red-500'">
                                {{ ((row as JourneyEvent).quantity ?? 0) > 0 ? '+' : '' }}{{ (row as JourneyEvent).quantity }}
                                <template v-if="(row as JourneyEvent).kind && (row as JourneyEvent).kind !== 'add'">· {{ kindLabel[(row as JourneyEvent).kind!] }}</template>
                            </span>
                            <span v-if="row.type === 'product' && (row as JourneyEvent).basket !== null && (row as JourneyEvent).basket !== undefined"
                                  class="ml-1 text-xs text-gray-400">
                                {{ trans('basket') }} {{ money((row as JourneyEvent).basket!) }}
                            </span>
                            <span v-if="(row as JourneyEvent).campaign_name" class="ml-1 text-xs text-gray-400">{{ (row as JourneyEvent).campaign_name }}</span>
                            <span v-if="row.type === 'touch' && (row as JourneyEvent).attributed"
                                  class="ml-1 text-xs text-indigo-500">{{ trans('credited') }}</span>
                        </div>
                        <div class="whitespace-nowrap text-xs tabular-nums text-gray-400">
                            {{ useFormatTime(row.datetime, { formatTime: 'hms' }) }}
                        </div>
                    </div>
                </template>
            </li>
        </ol>
    </div>
</template>
