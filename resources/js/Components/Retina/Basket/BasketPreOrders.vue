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
    dispatch_label: string
    payment_label: string
    basket_label: string
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
    texts: {
        title: string
        dispatch: string
        hold_together: string
        only_pre_order: string
        accept: string
        terms_title: string
    }
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
            {{ pre_orders.texts.title }}
        </div>

        <ul class="space-y-1">
            <li v-for="line in pre_orders.lines" :key="line.transaction_id" class="flex flex-wrap items-center gap-x-2">
                <span class="font-medium">{{ line.code }}</span>
                <span class="text-amber-800">{{ line.name }}</span>
                <span>·</span>
                <span>{{ line.basket_label }}</span>
            </li>
        </ul>

        <div class="font-medium">
            {{ pre_orders.has_in_stock_lines && pre_orders.is_accepted && pre_orders.hold_together ? pre_orders.texts.only_pre_order : pre_orders.texts.dispatch }}
        </div>

        <div v-if="pre_orders.pallet_estimate_label" class="text-xs font-medium">
            {{ pre_orders.pallet_estimate_label }}. {{ ctrans("The final pallet cost is confirmed when the goods arrive.") }}
        </div>

        <div v-if="pre_orders.deferred_amount > 0" class="text-xs font-medium">
            {{ ctrans("To pay now: :pay_now. Balance of :balance when the goods arrive.", { pay_now: locale.currencyFormat(currencyCode, pre_orders.pay_now_amount), balance: locale.currencyFormat(currencyCode, pre_orders.deferred_amount) }) }}
        </div>

        <div class="text-xs">
            <div class="font-medium">{{ pre_orders.texts.terms_title }}</div>
            <ul class="mt-0.5 list-disc space-y-0.5 pl-5">
                <li v-for="term in pre_orders.terms" :key="term">{{ term }}</li>
            </ul>
        </div>

        <template v-if="!pre_orders.is_accepted">
            <label v-if="pre_orders.has_in_stock_lines" class="flex items-start gap-2 cursor-pointer">
                <input v-model="holdTogether" type="checkbox" class="mt-0.5 rounded border-gray-400" />
                <span>{{ pre_orders.texts.hold_together }}</span>
            </label>

            <label class="flex items-start gap-2 cursor-pointer font-medium">
                <input v-model="acceptTerms" type="checkbox" class="mt-0.5 rounded border-gray-400" />
                <span>{{ pre_orders.texts.accept }}</span>
            </label>

            <p v-if="errorMessage" class="text-red-600 text-xs">{{ errorMessage }}</p>

            <Button
                :label="isInCheckout ? ctrans('Accept and continue to payment') : ctrans('Accept pre-order terms')"
                :disabled="!acceptTerms"
                :loading="isSubmitting"
                @click="submit" />
        </template>

        <div v-else class="text-xs font-medium">
            {{ ctrans("Accepted.") }}
        </div>
    </div>
</template>
