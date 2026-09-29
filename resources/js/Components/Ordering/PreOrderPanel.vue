<script setup lang="ts">
import { inject, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
    pre_order: {
        state: string
        state_label: string
        is_open: boolean
        is_trade: boolean
        has_back_order: boolean
        has_made_to_order: boolean
        has_pallet_delivery: boolean
        parent_order_reference: string | null
        estimated_dispatch_from: string | null
        estimated_dispatch_to: string | null
        is_late: boolean
        currency_code: string
        total_amount: number
        paid_amount: number
        balance_amount: number
        made_to_order_deposit: number
        free_cancellation_until: string | null
        supplier_ordered_at: string | null
        goods_arrived_at: string | null
        balance_requested_at: string | null
        balance_due_at: string | null
        cancelled_at: string | null
        cancellation_reason: string | null
        pallet_estimate_amount: number | null
        pallet_quote_amount: number | null
        pallet_quote_over_estimate: boolean
        terms: string[]
        update_route: routeType
        cancellation_reasons: { value: string, label: string }[]
    }
}>()

const locale = inject("locale", aikuLocaleStructure)
const formatDate = (date: string | null) => date ? new Date(date).toLocaleDateString() : "—"

const openForm = ref<null | "pallet_quote" | "dispatch_dates" | "cancel">(null)
const palletQuote = ref<number | null>(props.pre_order.pallet_quote_amount ?? props.pre_order.pallet_estimate_amount)
const dispatchFrom = ref(props.pre_order.estimated_dispatch_from ?? "")
const dispatchTo = ref(props.pre_order.estimated_dispatch_to ?? "")
const dispatchReason = ref("")
const cancellationReason = ref("customer_request")
const cancellationNotes = ref("")
const isSubmitting = ref(false)

const submit = (operation: string, data: Record<string, unknown> = {}) => {
    router[props.pre_order.update_route.method ?? "patch"](
        route(props.pre_order.update_route.name, props.pre_order.update_route.parameters),
        { operation, ...data },
        {
            preserveScroll: true,
            onStart: () => (isSubmitting.value = true),
            onFinish: () => (isSubmitting.value = false),
            onSuccess: () => (openForm.value = null),
        }
    )
}
</script>

<template>
    <div class="border-b border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 space-y-2">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-semibold">{{ ctrans("Pre-order") }}</span>
            <span class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-semibold">{{ pre_order.state_label }}</span>
            <span v-if="pre_order.has_made_to_order" class="text-xs">{{ ctrans("Made to order") }}</span>
            <span v-if="pre_order.has_back_order" class="text-xs">{{ ctrans("Back-order") }}</span>
            <span v-if="pre_order.has_pallet_delivery" class="text-xs">{{ ctrans("Pallet delivery") }}</span>
            <span v-if="pre_order.is_late" class="text-xs font-semibold text-red-700">{{ ctrans("Late: the customer may cancel for a full refund") }}</span>
            <span v-if="pre_order.parent_order_reference" class="text-xs">{{ ctrans("Split from :reference", { reference: pre_order.parent_order_reference }) }}</span>
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-1 md:grid-cols-4 text-xs">
            <div>{{ ctrans("Estimated dispatch") }}: <b>{{ formatDate(pre_order.estimated_dispatch_from) }} – {{ formatDate(pre_order.estimated_dispatch_to) }}</b></div>
            <div>{{ ctrans("Paid") }}: <b>{{ locale.currencyFormat(pre_order.currency_code, pre_order.paid_amount) }}</b> / {{ locale.currencyFormat(pre_order.currency_code, pre_order.total_amount) }}</div>
            <div v-if="pre_order.made_to_order_deposit">{{ ctrans("Non-refundable deposit once ordered") }}: <b>{{ locale.currencyFormat(pre_order.currency_code, pre_order.made_to_order_deposit) }}</b></div>
            <div>{{ ctrans("Free cancellation until") }}: <b>{{ formatDate(pre_order.free_cancellation_until) }}</b></div>
            <div>{{ ctrans("Supplier ordered") }}: <b>{{ formatDate(pre_order.supplier_ordered_at) }}</b></div>
            <div>{{ ctrans("Goods arrived") }}: <b>{{ formatDate(pre_order.goods_arrived_at) }}</b></div>
            <div>{{ ctrans("Balance requested") }}: <b>{{ formatDate(pre_order.balance_requested_at) }}</b></div>
            <div>{{ ctrans("Balance due") }}: <b>{{ formatDate(pre_order.balance_due_at) }}</b></div>
            <div v-if="pre_order.has_pallet_delivery">
                {{ ctrans("Pallet estimate / quote") }}:
                <b>{{ pre_order.pallet_estimate_amount !== null ? locale.currencyFormat(pre_order.currency_code, pre_order.pallet_estimate_amount) : "—" }}</b>
                /
                <b :class="pre_order.pallet_quote_over_estimate ? 'text-red-700' : ''">{{ pre_order.pallet_quote_amount !== null ? locale.currencyFormat(pre_order.currency_code, pre_order.pallet_quote_amount) : "—" }}</b>
            </div>
            <div v-if="pre_order.cancelled_at">{{ ctrans("Cancelled") }}: <b>{{ formatDate(pre_order.cancelled_at) }}</b> {{ pre_order.cancellation_reason }}</div>
        </div>

        <div v-if="pre_order.is_open" class="flex flex-wrap gap-2">
            <Button v-if="!pre_order.supplier_ordered_at && pre_order.state === 'waiting_for_goods'" v-tooltip="ctrans('Mark the goods as ordered from the supplier. Make the deposit are no longer refundable')" size="xs" type="secondary" :label="ctrans('Supplier ordered')" :loading="isSubmitting" @click="submit('supplier_ordered')" />
            <Button v-if="pre_order.state === 'waiting_for_goods'" v-tooltip="ctrans('Record that the goods are in the warehouse and ask the customer to pay the balance. Trade pallet deliveries wait for the pallet quote first')" size="xs" type="secondary" :label="ctrans('Goods arrived')" :loading="isSubmitting" @click="submit('goods_arrived')" />
            <Button v-if="pre_order.has_pallet_delivery" v-tooltip="ctrans('Enter the final pallet delivery cost. It replaces the shipping on the order and, if the goods have arrived, the balance is requested from the customer')" size="xs" type="secondary" :label="ctrans('Pallet quote')" @click="openForm = 'pallet_quote'" />
            <Button v-tooltip="ctrans('Change the estimated dispatch window. The customer is emailed the new dates')" size="xs" type="secondary" :label="ctrans('Change dispatch dates')" @click="openForm = 'dispatch_dates'" />
            <Button v-if="pre_order.balance_amount <= 0 && pre_order.state !== 'waiting_for_goods'" v-tooltip="ctrans('The pre-order is fully paid: close it and send the order to the warehouse to be picked and dispatched')" size="xs" type="positive" :label="ctrans('Send to warehouse')" :loading="isSubmitting" @click="submit('release')" />
            <Button v-tooltip="ctrans('Choose a reason and cancel the pre-order. The refund follows the terms the customer accepted at checkout')" size="xs" type="negative" :label="ctrans('Cancel pre-order')" @click="openForm = 'cancel'" />
        </div>

        <div v-if="openForm === 'pallet_quote'" class="flex flex-wrap items-end gap-2">
            <label class="text-xs">{{ ctrans("Final pallet delivery cost") }}
                <input v-model.number="palletQuote" type="number" step="0.01" min="0" class="block rounded border-gray-300 text-sm" />
            </label>
            <Button v-tooltip="ctrans('Save the pallet cost as the order shipping and, if the goods have arrived, ask the customer for the balance')" size="xs" :label="ctrans('Save and request balance')" :loading="isSubmitting" @click="submit('pallet_quote', { amount: palletQuote })" />
        </div>

        <div v-if="openForm === 'dispatch_dates'" class="flex flex-wrap items-end gap-2">
            <label class="text-xs">{{ ctrans("From") }} <input v-model="dispatchFrom" type="date" class="block rounded border-gray-300 text-sm" /></label>
            <label class="text-xs">{{ ctrans("To") }} <input v-model="dispatchTo" type="date" class="block rounded border-gray-300 text-sm" /></label>
            <label class="text-xs grow">{{ ctrans("Message to the customer (optional)") }} <input v-model="dispatchReason" type="text" class="block w-full rounded border-gray-300 text-sm" /></label>
            <Button v-tooltip="ctrans('Save the new dispatch dates and email them to the customer with your message')" size="xs" :label="ctrans('Save and email the customer')" :loading="isSubmitting" @click="submit('dispatch_dates', { from: dispatchFrom, to: dispatchTo, reason: dispatchReason })" />
        </div>

        <div v-if="openForm === 'cancel'" class="flex flex-wrap items-end gap-2">
            <label class="text-xs">{{ ctrans("Reason") }}
                <select v-model="cancellationReason" class="block rounded border-gray-300 text-sm">
                    <option v-for="reason in pre_order.cancellation_reasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
                </select>
            </label>
            <label class="text-xs grow">{{ ctrans("Notes") }} <input v-model="cancellationNotes" type="text" class="block w-full rounded border-gray-300 text-sm" /></label>
            <Button v-tooltip="ctrans('Cancel the order, refund what the terms allow for this reason and email the customer')" size="xs" type="negative" :label="ctrans('Cancel and refund per the terms')" :loading="isSubmitting" @click="submit('cancel', { cancellation_reason: cancellationReason, notes: cancellationNotes })" />
        </div>
    </div>
</template>
