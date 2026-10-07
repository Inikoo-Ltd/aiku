<script setup lang="ts">
import { computed, inject } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"
import { PageHeadingTypes } from "@/types/PageHeading"
import PageHeading from "@/Components/Headings/PageHeadingPublic.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import CheckoutPaymentCard from "@/Components/Retina/Ecom/CheckoutPaymentCard.vue"
import RetinaPreOrderPanel, { PreOrderShowcase } from "@/Components/Retina/PreOrder/RetinaPreOrderPanel.vue"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    order: { id: number, slug: string }
    pre_order: PreOrderShowcase
    paymentMethods: any[]
    to_pay_data: { by_balance: number, by_other: number, total: number }
    currency_code: string
    routes: { pay_with_balance: routeType, order: routeType }
}>()

const locale = inject("locale", aikuLocaleStructure)
const cardMethod = computed(() => props.paymentMethods?.[0])
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <div class="w-full px-4 space-y-6 pb-10">
        <RetinaPreOrderPanel :pre_order :orderId="order.id" :orderSlug="order.slug" />

        <div v-if="!pre_order.can_pay_balance" class="text-center text-gray-600">
            {{ ctrans("There is nothing to pay on this order.") }}
            <Link :href="route(routes.order.name, routes.order.parameters)" class="underline">{{ ctrans("Back to the order") }}</Link>
        </div>

        <template v-else>
            <div v-if="to_pay_data.by_balance > 0 && to_pay_data.by_other <= 0" class="mx-auto flex max-w-md flex-col items-center gap-3 rounded border border-gray-300 py-5">
                <div>{{ ctrans(":amount will be paid from your account balance.", { amount: locale.currencyFormat(currency_code, to_pay_data.by_balance) }) }}</div>
                <ButtonWithLink :routeTarget="routes.pay_with_balance" :label="ctrans('Pay the balance')" />
            </div>

            <div v-else class="md:mx-10">
                <div v-if="to_pay_data.by_balance > 0" class="mb-3 text-center">
                    {{ ctrans(":balance of :total will be paid from your account balance, please pay the rest by card.", { balance: locale.currencyFormat(currency_code, to_pay_data.by_balance), total: locale.currencyFormat(currency_code, to_pay_data.total) }) }}
                </div>
                <CheckoutPaymentCard
                    v-if="cardMethod"
                    :data="cardMethod"
                    :needToPay="to_pay_data.by_other"
                    :currency_code
                    :order="order" />
                <div v-else class="text-center text-gray-600">
                    {{ ctrans("Card payments are not available right now, please contact us to pay the balance.") }}
                </div>
            </div>
        </template>
    </div>
</template>
