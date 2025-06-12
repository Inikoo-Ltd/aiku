<script setup lang="ts">
import CheckoutSummary from "@/Components/Retina/Ecom/CheckoutSummary.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { faPaypal } from "@fortawesome/free-brands-svg-icons"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, onMounted, ref } from "vue"
import type { Component } from "vue"
import { data } from "autoprefixer";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n"

import CheckoutPaymentBankTransfer from "@/Components/Retina/Ecom/CheckoutPaymentBankTransfer.vue"
import CheckoutPaymentCard from "@/Components/Retina/Ecom/CheckoutPaymentCard.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"


import { faArrowLeft, faCreditCardFront, faUniversity } from "@fal"
import { faExclamationTriangle } from "@fas"
import { Head } from "@inertiajs/vue3"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { routeType } from "@/types/route"

library.add(faCreditCardFront, faUniversity, faExclamationTriangle)

const props = defineProps<{
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
}>()

// console.log('prporpor', props)

const currentTab = ref({
    index: 0,
    key: props.paymentMethods?.[0]?.key
})

const layout = inject('layout', retinaLayoutStructure)
console.log('layout', layout.retina.type)

const component = computed(() => {
    const components: Component = {
        bank_transfer: CheckoutPaymentBankTransfer,
        credit_card: CheckoutPaymentCard,

    };

    return components[currentTab.value.key];
})

// const isModalConfirmationOrder = ref(false)
// const onProcessOrder = () => {
//     console.log('onProcessOrder')
//     isModalConfirmationOrder.value = false
//     // router.post(route('retina.models.top_up_payment_api_point.store'), {
//     //     amount: amount.value,
//     //     // notes: privateNote.value,
//     // }, {
//     //     preserveState: true,
//     //     preserveScroll: true,
//     //     onStart: () => {
//     //         isLoading.value = true
//     //     },
//     //     onFinish: () => {
//     //         isLoading.value = false
//     //     }
//     // })
// }
</script>

<template>
    <!-- paymentMethods: <pre>{{ total_amount }}</pre> -->
    <Head title="Checkout" />

    <div v-if="!summary" class="text-center text-gray-500 text-2xl pt-6">
        {{ trans("Your basket is empty") }}
    </div>

    <div v-else class="w-full px-4 mt-8">
        <div class="px-4 text-xl">
            <span class="text-gray-500">{{ trans("Order number") }}</span> <span class="font-bold">#{{ order.reference }}</span>
        </div>
        
        <CheckoutSummary
            :summary
            :balance
        />

        <!-- Section: Payment Tabs -->
        <div v-if="balance > total_amount" class="mx-10 border border-gray-300">
            <div v-if="props.paymentMethods?.length" class="max-w-lg">
                <div class="grid grid-cols-1 sm:hidden">
                    <!-- Use an "onChange" listener to redirect the user to the selected tab URL. -->
                    <select aria-label="Select a tab" class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-2 pl-3 pr-8 text-base  outline outline-1 -outline-offset-1 outline-gray-300 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600">
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

            <KeepAlive>
                <component
                    :is="component"
                    :data="paymentMethods[currentTab.index]"
                />
            </KeepAlive>
        </div>

        <div v-else class="ml-10 mr-4 py-5 flex items-center flex-col gap-y-2 border border-gray-300 rounded">
            <div class="text-center text-gray-500">
                This is your final confirmation.
                <br> You can pay totally with your current balance.
            </div>
            <Button @click="'isModalConfirmationOrder = true'" label="Place Order" size="l" />
        </div>


        <div class="xflex xjustify-end gap-x-4 mt-4 px-10">
            <ButtonWithLink
                v-if="layout.retina.type === 'dropshipping'"
                :icon="faArrowLeft"
                type="tertiary"
                label="Back to basket"
                :routeTarget="routes.back_to_basket"
            />

            <ButtonWithLink
                v-else
                :icon="faArrowLeft"
                type="tertiary"
                label="Back to basket"
                :routeTarget="{
                    name: 'retina.ecom.basket.show'
                }"
            />
        </div>

        <!-- <Modal
            :isOpen="isModalConfirmationOrder"
            @close="() => isModalConfirmationOrder = false"
            width="w-full max-w-lg"
        >
            <div class="px-3">
                <div>
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-amber-100">
                        <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-amber-600 text-xl" fixed-width aria-hidden="true" />
                    </div>
                    <div class="mt-3 text-center sm:mt-2">
                        <div as="h3" class="text-base font-semibold">
                            Final confirmation
                        </div>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 sm:mt-6 flex gap-x-4">
                    <Button @click="isModalConfirmationOrder = false" label="cancel" type="tertiary" />
                    <Button @click="onProcessOrder" label="Yes, process order" icon="" full />
                </div>
            </div>

        </Modal> -->
    </div>
</template>