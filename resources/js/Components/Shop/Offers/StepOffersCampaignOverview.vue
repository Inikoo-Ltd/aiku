<script setup lang='ts'>
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSortAmountUp } from '@fal'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import { trans } from "laravel-vue-i18n"

library.add(faSortAmountUp)

interface Step {
    min_quantity: number
    percentage_off: number
    is_popular?: boolean
}

const props = defineProps<{
    data: {
        tabsBox?: {}[]
        currency_code: string
        ladder?: {
            min_quantity: number
            number_products: number
            avg_percentage_off: number
            min_percentage_off: number
            max_percentage_off: number
        }[]
        top_offers?: {
            slug: string
            code: string
            product_code: string
            product_name: string
            steps: Step[]
            number_orders: number
        }[]
    }
}>()

const percent = (value: number) => `${Math.round(value * 1000) / 10}%`
</script>

<template>
    <div class="bg-white px-2 sm:px-3 md:px-4 pb-4">
        <TabsBoxDisplay v-if="data.tabsBox" :tabs_box="data.tabsBox" />

        <div class="grid md:grid-cols-2 gap-4 pt-4 items-start">
            <div class="rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100 text-sm font-medium text-gray-700">
                    <FontAwesomeIcon icon="fal fa-sort-amount-up" class="mr-1.5 text-gray-400" fixed-width aria-hidden="true" />
                    {{ trans('Discount ladder') }}
                </div>
                <div v-if="data.ladder?.length" class="max-h-[28rem] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-400 text-left">
                            <th class="px-4 py-2 font-normal">{{ trans('From quantity') }}</th>
                            <th class="px-4 py-2 font-normal text-right">{{ trans('Products') }}</th>
                            <th class="px-4 py-2 font-normal text-right">{{ trans('Avg discount') }}</th>
                            <th class="px-4 py-2 font-normal text-right">{{ trans('Range') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rung in data.ladder" :key="rung.min_quantity" class="border-t border-gray-100">
                            <td class="px-4 py-2">≥ {{ rung.min_quantity }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ rung.number_products }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ rung.avg_percentage_off }}%</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-500">
                                {{ rung.min_percentage_off }}% – {{ rung.max_percentage_off }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div v-else class="px-4 py-6 text-sm text-gray-400">{{ trans('No active step offers') }}</div>
            </div>

            <div class="rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100 text-sm font-medium text-gray-700">
                    {{ trans('Most used step offers') }}
                </div>
                <table v-if="data.top_offers?.length" class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-400 text-left">
                            <th class="px-4 py-2 font-normal">{{ trans('Product') }}</th>
                            <th class="px-4 py-2 font-normal">{{ trans('Steps') }}</th>
                            <th class="px-4 py-2 font-normal text-right">{{ trans('Orders') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="offer in data.top_offers" :key="offer.slug" class="border-t border-gray-100 align-top">
                            <td class="px-4 py-2">
                                <div>{{ offer.product_code ?? offer.code }}</div>
                                <div class="text-xs text-gray-400 max-w-[16rem] truncate">{{ offer.product_name }}</div>
                            </td>
                            <td class="px-4 py-2">
                                <span v-for="step in offer.steps" :key="step.min_quantity"
                                    class="inline-block mr-1 mb-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs tabular-nums"
                                    :class="step.is_popular ? 'ring-1 ring-gray-400' : ''">
                                    {{ step.min_quantity }}+ → {{ percent(step.percentage_off) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ offer.number_orders }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="px-4 py-6 text-sm text-gray-400">{{ trans('No active step offers') }}</div>
            </div>
        </div>
    </div>
</template>
