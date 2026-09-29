<script setup lang="ts">
import { inject, ref } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"

export interface BasketPreOrderLine {
    transaction_id: number
    product_id: number
    code: string
    name: string
    type: "back_order" | "made_to_order"
    type_label: string
    dispatch_label: string
    deposit_percentage: number
    is_pallet_delivery: boolean
    quantity_ordered: number
    in_stock_quantity: number
    pre_order_quantity: number
}

export interface BasketPreOrders {
    signature: string
    is_accepted: boolean
    hold_together: boolean
    lines: Record<string, BasketPreOrderLine>
    has_pre_orders: boolean
    has_in_stock_lines: boolean
    has_made_to_order: boolean
    has_pallet_delivery: boolean
    pallet_estimate_label: string | null
    deferred_amount: number
    pay_now_amount: number
    terms: string[]
}

const props = defineProps<{
    pre_orders: BasketPreOrders
    orderId?: number
    isInCheckout?: boolean
    currencyCode?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const acceptTerms = ref(false)
const holdTogether = ref(props.pre_orders.hold_together)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const submit = () => {
    router.patch(
        route("retina.models.order.accept_pre_order_terms", { order: props.orderId }),
        { accept_terms: acceptTerms.value, hold_together: holdTogether.value },
        {
            preserveScroll: true,
            onStart: () => (isSubmitting.value = true),
            onFinish: () => (isSubmitting.value = false),
            onError: (errors) => (errorMessage.value = Object.values(errors)[0] as string),
        }
    )
}
</script>

<template>
    <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 space-y-3">
        <div class="font-semibold">
            {{ ctrans("Pre-order items in this order") }}
        </div>

        <ul class="space-y-1">
            <li v-for="line in pre_orders.lines" :key="line.transaction_id" class="flex flex-wrap items-center gap-x-2">
                <span class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-semibold">{{ line.type_label }}</span>
                <span class="font-medium">{{ line.code }}</span>
                <span class="text-amber-800">{{ line.name }}</span>
                <span>·</span>
                <span v-if="line.in_stock_quantity > 0">
                    {{ ctrans(":in_stock sent now, :pre_order later", { in_stock: String(line.in_stock_quantity), pre_order: String(line.pre_order_quantity) }) }} ·
                </span>
                <span>{{ line.dispatch_label }}</span>
                <span v-if="line.deposit_percentage < 100">· {{ ctrans(":percentage% deposit", { percentage: String(line.deposit_percentage) }) }}</span>
            </li>
        </ul>

        <div v-if="pre_orders.pallet_estimate_label" class="text-xs font-medium">
            {{ pre_orders.pallet_estimate_label }}. {{ ctrans("The final pallet cost is confirmed when the goods arrive.") }}
        </div>

        <div v-if="pre_orders.deferred_amount > 0" class="text-xs font-medium">
            {{ ctrans("To pay now: :pay_now. Balance of :balance when the goods arrive.", { pay_now: locale.currencyFormat(currencyCode, pre_orders.pay_now_amount), balance: locale.currencyFormat(currencyCode, pre_orders.deferred_amount) }) }}
        </div>

        <ul class="list-disc space-y-0.5 pl-5 text-xs">
            <li v-for="term in pre_orders.terms" :key="term">{{ term }}</li>
        </ul>

        <template v-if="!pre_orders.is_accepted">
            <label v-if="pre_orders.has_in_stock_lines" class="flex items-start gap-2 cursor-pointer">
                <input v-model="holdTogether" type="checkbox" class="mt-0.5 rounded border-gray-400" />
                <span>
                    {{ ctrans("Hold my order and send everything together") }}
                    <span class="block text-xs text-amber-800">
                        {{ ctrans("Otherwise in-stock items are sent now, and pre-order items are sent separately when they arrive with their own delivery charge.") }}
                    </span>
                </span>
            </label>

            <label class="flex items-start gap-2 cursor-pointer font-medium">
                <input v-model="acceptTerms" type="checkbox" class="mt-0.5 rounded border-gray-400" />
                <span>{{ ctrans("I accept the estimated dispatch time and the pre-order terms above") }}</span>
            </label>

            <p v-if="errorMessage" class="text-red-600 text-xs">{{ errorMessage }}</p>

            <Button
                :label="isInCheckout ? ctrans('Accept and continue to payment') : ctrans('Accept pre-order terms')"
                :disabled="!acceptTerms"
                :loading="isSubmitting"
                @click="submit" />
        </template>

        <div v-else class="text-xs font-medium">
            {{ !pre_orders.has_in_stock_lines
                ? ctrans("Accepted.")
                : pre_orders.hold_together
                    ? ctrans("Accepted. Everything will be sent together when the pre-order items arrive.")
                    : ctrans("Accepted. In-stock items are sent now; pre-order items are sent separately when they arrive, with their own delivery charge.") }}
        </div>
    </div>
</template>
