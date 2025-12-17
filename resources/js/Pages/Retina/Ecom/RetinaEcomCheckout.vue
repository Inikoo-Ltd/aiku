<script setup lang="ts">
import EcomCheckoutSummary from "@/Components/Retina/Ecom/EcomCheckoutSummary.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, onMounted, onUnmounted, ref } from "vue"
import type { Component } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import CheckoutPaymentBankTransfer from "@/Components/Retina/Ecom/CheckoutPaymentBankTransfer.vue"
import CheckoutPaymentCard from "@/Components/Retina/Ecom/CheckoutPaymentCard.vue"
import { faArrowLeft, faCreditCardFront, faUniversity } from "@fal"
import { faExclamationTriangle, faStar, faBoxHeart, faShieldAlt } from "@fas"
import { Head } from "@inertiajs/vue3"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { routeType } from "@/types/route"
import { PageHeadingTypes } from "@/types/PageHeading"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import CheckoutPaymentCashOnDelivery from "@/Components/Retina/Ecom/CheckoutPaymentCashOnDelivery.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faCreditCardFront, faUniversity, faExclamationTriangle)

const props = defineProps<{
    pageHead: PageHeadingTypes
    order: {},
    paymentMethods: []
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
    }
    balance: string
    total_amount: string
    routes: {
        back_to_basket: routeType
    }
    to_pay_data: {
        by_balance: number
        by_other: number
        total: number
    },
    currency_code: string
}>()

const currentTab = ref({
    index: 0,
    key: props.paymentMethods?.[0]?.key
})

const layout = inject("layout", retinaLayoutStructure)

const component = computed(() => {
    const components: Component = {
        credit_card: CheckoutPaymentCard,
        bank_transfer: CheckoutPaymentBankTransfer,
        cash_on_delivery: CheckoutPaymentCashOnDelivery

    }

    return components[currentTab.value.key]
})


onMounted(() => {
    layout.root_active = "retina.ecom.basket."
})
onUnmounted(() => {
    layout.root_active = ""
})

const locale = inject("locale", aikuLocaleStructure)

</script>

<template>

    <Head title="Checkout" />
    <PageHeading
        :data="pageHead"
    />

    <div v-if="order?.has_insurance || order?.is_premium_dispatch || order?.has_extra_packing" class="absolute top-0 left-1/2 -translate-x-1/2 bg-yellow-500 rounded-b px-4 py-0.5 text-sm space-x-1">
        <FontAwesomeIcon v-if="order?.is_premium_dispatch" v-tooltip="trans('Premium dispatch')" :icon="faStar" class="text-white animate-pulse" fixed-width aria-hidden="true" />
        <FontAwesomeIcon v-if="order?.has_extra_packing" v-tooltip="trans('Extra packing')" :icon="faBoxHeart" class="text-white animate-pulse" fixed-width aria-hidden="true" />
        <FontAwesomeIcon v-if="order?.has_insurance" v-tooltip="trans('Insurance')" :icon="faShieldAlt" class="text-white animate-pulse" fixed-width aria-hidden="true" />
    </div>

    <div v-if="!summary" class="text-center text-gray-500 text-2xl pt-6">
        {{ trans("Your basket is empty") }}
    </div>

    <div v-else class="w-full px-4 xmt-8">

        <EcomCheckoutSummary
            :summary
            :balance
            :order="order"
            isInBasket
        />

        <!-- If 'Total' is 0 or less -->
        <div v-if="to_pay_data.total <= 0">
            <EmptyState
                :data="{
                    title: trans('No item to checkout')
                }"
            />
        </div>

        <!-- If balance can't cover -->
        <div v-else-if="to_pay_data.by_other > 0" class="mt-10 md:mx-10 ">
            <div v-if="to_pay_data.by_balance > 0" class="mx-auto text-center text-lg border border-gray-300 py-4 rounded">

                <div>
                    <span class="font-bold bg-yellow-300 px-1 py-0.5">{{ locale?.currencyFormat(currency_code, to_pay_data.by_balance) }}  {{ trans("of") }} {{ locale?.currencyFormat(currency_code, to_pay_data.total) }}</span>
                    {{ trans("will be paid with balance") }}
                </div>

                <div class="text-gray-500 text-sm mt-1">
                    {{ trans("Please paid the rest with your preferred method below:") }}
                </div>
            </div>

            <div class="mt-5 border border-gray-300">
                <div v-if="props.paymentMethods?.length > 1" class="max-w-lg">
                    <div class="grid grid-cols-1 sm:hidden">
                        <!-- Use an "onChange" listener to redirect the user to the selected tab URL. -->
                        <select aria-label="Select a tab"
                                class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-2 pl-3 pr-8 text-base  outline outline-1 -outline-offset-1 outline-gray-300 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600">
                            <option v-for="(tab, tabIdx) in paymentMethods" :key="tabIdx" :selected="currentTab === tabIdx">
                                <FontAwesomeIcon :icon="tab.icon" class="" fixed-width aria-hidden="true" />
                                {{ tab.label }}
                            </option>
                        </select>
                    </div>

                    <div class="hidden sm:block">
                        <nav class="isolate flex divide-x divide-gray-200 rounded-lg shadow" aria-label="Tabs">
                            <div
                                v-for="(tab, tabIdx) in props.paymentMethods"
                                @click="currentTab.index = tabIdx, currentTab.key = tab.key"
                                :key="tabIdx"
                                :class="[currentTab.index === tabIdx ? '' : 'text-gray-500 hover:text-gray-700', tabIdx === 0 ? 'rounded-l-lg' : '', tabIdx === props.paymentMethods?.length - 1 ? 'rounded-r-lg' : '']"
                                class="cursor-pointer group relative min-w-0 flex-1 overflow-hidden bg-white px-4 py-4 text-center text-sm font-medium hover:bg-gray-100 focus:z-10"
                            >
                                <FontAwesomeIcon v-if="tab.icon" :icon="tab.icon" class="mr-1" fixed-width aria-hidden="true" />
                                <span>{{ tab.label }}</span>
                                <span aria-hidden="true" :class="[currentTab.index === tabIdx ? 'bg-indigo-500' : 'bg-transparent', 'absolute inset-x-0 bottom-0 h-0.5']" />
                            </div>
                        </nav>
                    </div>
                </div>

                <!-- <KeepAlive> -->
                    <component
                        :is="component"
                        :data="paymentMethods[currentTab.index]"
                        :needToPay="to_pay_data.by_other"
                        :currency_code
                        :order="order"
                    />
                <!-- </KeepAlive> -->
            </div>
        </div>

        <!-- If balance can cover totally -->
        <div v-else-if="(to_pay_data.by_balance > 0) && (to_pay_data.by_balance >= to_pay_data.total)" class="ml-10 mr-4 py-5 flex items-center flex-col gap-y-2 border border-gray-300 rounded">
            <div class="w-64">
                <ButtonWithLink
                    iconRight="fas fa-arrow-right"
                    :label="trans('Place order')"
                    :routeTarget="routes?.pay_with_balance"
                    full
                >
                </ButtonWithLink>
            </div>

            <div class="text-xs text-gray-500 xmt-2 italic text-center gap-x-1 w-80 justify-center">
                <FontAwesomeIcon icon="fal fa-info-circle" xclass="mt-[4px]" fixed-width aria-hidden="true" />
                <div class="leading-5 text-center inline">
                    {{ trans("This is your final confirmation. You can pay totally with your current balance.") }}
                </div>
            </div>
        </div>


        <div class="gap-x-4 mt-4 md:px-10">
            <ButtonWithLink
                :icon="faArrowLeft"
                type="tertiary"
                :label="trans('Back to basket')"
                :routeTarget="routes.back_to_basket"
            />
        </div>


    </div>
</template>