<script setup lang="ts">
import { trans, transChoice } from 'laravel-vue-i18n'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationTriangle } from '@fas'
import { faBell, faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

library.add(faExclamationTriangle, faBell, faTimes)

export interface StockIssueLine {
    transaction_id: number
    product_id: number
    code: string
    name: string
    quantity_ordered: number
    held_quantity: number
    available_quantity: number
}

export interface StockIssues {
    low_stock: StockIssueLine[]
    out_of_stock: StockIssueLine[]
}

defineProps<{
    stock_issues?: StockIssues
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div v-if="stock_issues?.out_of_stock?.length" class="rounded border border-red-300 bg-red-50 text-red-700 px-4 py-3 text-sm space-y-1">
        <div class="font-semibold flex items-center gap-x-2">
            <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
            {{ transChoice(':count item is out of stock|:count items are out of stock', stock_issues.out_of_stock.length, { count: stock_issues.out_of_stock.length }) }}
        </div>
        <ul class="list-disc pl-5">
            <li v-for="line in stock_issues.out_of_stock" :key="line.transaction_id" class="flex flex-wrap items-center gap-x-2">
                <span><span class="font-mono text-xs">{{ line.code }}</span> {{ line.name }}, {{ trans(':count kept', { count: locale.number(line.held_quantity || line.quantity_ordered) }) }}</span>
                <Link :href="route('retina.models.remind_back_in_stock.store', { product: line.product_id })" method="post" as="button" preserve-scroll class="underline text-xs">
                    <FontAwesomeIcon icon="fal fa-bell" fixed-width aria-hidden="true" /> {{ trans('Notify me when back') }}
                </Link>
                <Link :href="route('retina.models.transaction.delete', { transaction: line.transaction_id })" method="delete" as="button" preserve-scroll class="underline text-xs">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" /> {{ trans('Remove from basket') }}
                </Link>
            </li>
        </ul>
        <div>{{ trans('These items went out of stock, so their quantity is set to 0 and they are not charged. You can still place the order. If they are back in stock before you do, your quantity is put back automatically.') }}</div>
    </div>

    <div v-if="stock_issues?.low_stock?.length" class="rounded border border-amber-300 bg-amber-50 text-amber-800 px-4 py-3 text-sm space-y-1">
        <div class="font-semibold flex items-center gap-x-2">
            <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
            {{ transChoice('Low stock on :count item|Low stock on :count items', stock_issues.low_stock.length, { count: stock_issues.low_stock.length }) }}
        </div>
        <ul class="list-disc pl-5">
            <li v-for="line in stock_issues.low_stock" :key="line.transaction_id">
                <span class="font-mono text-xs">{{ line.code }}</span> {{ line.name }},
                {{ trans(':ordered ordered, :available in stock', { ordered: locale.number(line.quantity_ordered), available: locale.number(line.available_quantity) }) }}
            </li>
        </ul>
        <div>{{ trans('We will do our best to send everything. If we cannot, we will try to contact you to offer a replacement. Anything still missing is credited to your balance.') }}</div>
    </div>
</template>
