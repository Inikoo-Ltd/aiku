<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGift, faBoxOpen } from "@fal"
import { trans } from "laravel-vue-i18n"

interface UpcomingTransaction {
    id: number
    product_code: string | null
    product_name: string | null
    quantity: number | string
    public_notes: string | null
    type: 'gift' | 'follow_on'
    state: string
}

defineProps<{
    upcomingTransactions: {
        data: UpcomingTransaction[]
    }
}>()

const typeAppearances = {
    gift: {
        icon: faGift,
        label: trans('Gift'),
        iconClass: 'text-rose-500 bg-rose-100',
        cardClass: 'border-rose-200 bg-rose-50/50'
    },
    follow_on: {
        icon: faBoxOpen,
        label: trans('Follow on'),
        iconClass: 'text-sky-500 bg-sky-50',
        cardClass: 'border-sky-200 bg-sky-50/50'
    }
}

const appearanceOf = (type: UpcomingTransaction['type']) => typeAppearances[type] ?? typeAppearances.follow_on

const formatQuantity = (quantity: UpcomingTransaction['quantity']) => Number(quantity)
</script>

<template>
    <div v-if="upcomingTransactions?.data?.length" class="border-t border-gray-300 pt-4 col-span-2 px-1">
        <div class="text-sm font-semibold text-gray-700 mb-2">
            {{ trans("Will included with your order") }}
            <span class="text-gray-400 font-normal">({{ upcomingTransactions.data.length }})</span>
        </div>

        <div class="grid sm:grid-cols-2 gap-2">
            <div
                v-for="upcomingTransaction in upcomingTransactions.data"
                :key="upcomingTransaction.id"
                class="flex items-start gap-x-3 rounded-md border px-3 py-2"
                :class="appearanceOf(upcomingTransaction.type).cardClass"
            >
                <div
                    class="flex-none h-8 w-8 rounded-full flex items-center justify-center"
                    :class="appearanceOf(upcomingTransaction.type).iconClass"
                >
                    <FontAwesomeIcon :icon="appearanceOf(upcomingTransaction.type).icon" fixed-width aria-hidden="true" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-x-2">
                        <span class="text-xs uppercase tracking-wide text-gray-500">
                            {{ appearanceOf(upcomingTransaction.type).label }}
                        </span>
                        <span v-if="upcomingTransaction.product_code" class="text-xs text-gray-400 truncate">
                            {{ upcomingTransaction.product_code }}
                        </span>
                    </div>

                    <div v-tooltip="upcomingTransaction.product_name" class="text-sm text-gray-800 truncate">
                        {{ upcomingTransaction.product_name }}
                    </div>

                    <div v-if="upcomingTransaction.public_notes" class="text-xs text-gray-500 mt-0.5">
                        {{ upcomingTransaction.public_notes }}
                    </div>
                </div>

                <div class="flex-none text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded px-2 py-0.5">
                    ×{{ formatQuantity(upcomingTransaction.quantity) }}
                </div>
            </div>
        </div>
    </div>
</template>
