<script setup lang="ts">
import { inject } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Button from "@/Components/Elements/Buttons/Button.vue"

export interface PreOrderShowcase {
    id: number
    state: string
    state_label: string
    is_open: boolean
    is_trade: boolean
    has_made_to_order: boolean
    order_reference: string
    parent_order_reference: string | null
    estimated_dispatch_from: string | null
    estimated_dispatch_to: string | null
    is_late: boolean
    currency_code: string
    total_amount: number
    paid_amount: number
    balance_amount: number
    free_cancellation_until: string | null
    balance_due_at: string | null
    pallet_estimate_amount: number | null
    pallet_quote_amount: number | null
    can_pay_balance: boolean
    terms: string[]
}

const props = defineProps<{
    pre_order: PreOrderShowcase
    orderId: number
    orderSlug: string
    showPayButton?: boolean
}>()

const locale = inject("locale", aikuLocaleStructure)
const formatDate = (date: string | null) => date ? new Date(date).toLocaleDateString() : ""

</script>

<template>
    <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="font-semibold">
                {{ ctrans("Pre-order") }} · {{ pre_order.state_label }}
            </div>
            <div v-if="pre_order.parent_order_reference" class="text-xs">
                {{ ctrans("Split from order :reference, whose in-stock items are sent separately.", { reference: pre_order.parent_order_reference }) }}
            </div>
        </div>

        <div v-if="pre_order.estimated_dispatch_from && pre_order.is_open">
            {{ ctrans("Estimated dispatch between :from and :to", { from: formatDate(pre_order.estimated_dispatch_from), to: formatDate(pre_order.estimated_dispatch_to) }) }}
        </div>

        <div class="flex flex-wrap gap-x-6 gap-y-1">
            <span>{{ ctrans("Paid") }}: <b>{{ locale.currencyFormat(pre_order.currency_code, pre_order.paid_amount) }}</b></span>
            <span v-if="pre_order.balance_amount > 0">{{ ctrans("Balance") }}: <b>{{ locale.currencyFormat(pre_order.currency_code, pre_order.balance_amount) }}</b></span>
            <span v-if="pre_order.balance_due_at">{{ ctrans("Due by") }}: <b>{{ formatDate(pre_order.balance_due_at) }}</b></span>
            <span v-if="pre_order.pallet_quote_amount !== null">{{ ctrans("Pallet delivery") }}: <b>{{ locale.currencyFormat(pre_order.currency_code, pre_order.pallet_quote_amount) }}</b></span>
            <span v-else-if="pre_order.pallet_estimate_amount !== null">{{ ctrans("Pallet delivery estimate") }}: <b>{{ locale.currencyFormat(pre_order.currency_code, pre_order.pallet_estimate_amount) }}</b></span>
        </div>

        <div v-if="pre_order.is_open" class="text-xs">
            {{ ctrans("To cancel this pre-order, please contact us.") }}
        </div>

        <details class="text-xs">
            <summary class="cursor-pointer underline">{{ ctrans("Pre-order terms") }}</summary>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                <li v-for="term in pre_order.terms" :key="term">{{ term }}</li>
            </ul>
        </details>

        <div v-if="pre_order.is_open" class="flex flex-wrap items-center gap-2 pt-1">
            <Link v-if="showPayButton && pre_order.can_pay_balance" :href="route('retina.ecom.orders.pay_balance', { order: orderSlug })">
                <Button type="positive" :label="ctrans('Pay the balance')" />
            </Link>
        </div>
    </div>
</template>
