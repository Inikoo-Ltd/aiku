<script setup lang="ts">
    
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboard, faDollarSign, faSortNumericDown, faWeight, faMapPin } from "@fal"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import NeedToPayV2Retina from "@/Components/Utils/NeedToPayV2Retina.vue"
import Toggle from "@/Components/Pure/Toggle.vue"
import { get, set } from "lodash"
import { notify } from "@kyvg/vue3-notification"
import { routeType } from "@/types/route"
import { router } from "@inertiajs/vue3"

const props = defineProps<{
    summary: {
        order_summary: {
            net_amount: string
            gross_amount: string
            tax_amount: string
            goods_amount: string
            services_amount: string
            charges_amount: string
        }
        order_properties: {
            weight: number
            customer_order_number: number
            customer_order_ordinal: string
            customer_order_ordinal_tooltip: string
        }
        products: {

        }
        delivery_notes: {

        }
        payments: {

        }
        invoices: {

        }
        customer: {

        }
    }
    order: {
        id: number
        is_collection: boolean
    }
    balance?: string
    address_management: AddressManagement
    is_unable_dispatch?: boolean
    contact_address?: Address | null
    isInBasket?: boolean
    updateRoute: routeType
}>()

const locale = inject('locale', {})
const layout = inject('layout', retinaLayoutStructure)

const isModalShippingAddress = ref(false)


// Method: convert "15.26" to 15.26
const convertToFloat2 = (val: any) => {
    const num = parseFloat(val)
    if (isNaN(num)) return 0.00
    return parseFloat(num.toFixed(2))
}

// Collection feature methods
const isLoadingCollection = ref(false)
const updateCollection = (value: boolean) => {
    const payload = {
        collection_address_id: value ? props.address_management?.addresses?.current_selected_address_id : null
    }

    if (props.updateRoute?.name) {
        router.patch(
            route(props.updateRoute?.name, props.updateRoute.parameters),
            payload,
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    isLoadingCollection.value = true
                },
                onFinish: () => {
                    isLoadingCollection.value = false
                },
                onError: (error) => {
                    console.error(error)
                    notify({
                        title: trans("Something went wrong."),
                        text: trans("Failed to update to collection"),
                        type: "error",
                    })
                },
            }
        )
    }
}
</script>

<template>
    <div class="py-4 grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-6 px-4">
        <div class="col-span-2 grid grid-cols-2 gap-y-4">
            <div class="col-span-2 border-b border-gray-300">
                <!-- Field: weight -->
                <dl class="mt-1 flex items-center w-full flex-none gap-x-1.5">
                    <dt v-tooltip="trans('Weight')" class="flex-none pl-1">
                        <FontAwesomeIcon :icon="faWeight" fixed-width aria-hidden="true" class="text-gray-500" />
                    </dt>
                    <dd class="text-gray-500 sep" v-tooltip="trans('Estimated weight of all products')">
                        {{ summary?.order_properties?.weight || 0 }}
                    </dd>
                </dl>

                
                <!-- Field: Collection (toggle) -->
                <dl class="mt-1 mb-2 flex items-center w-full flex-none gap-x-1.5">
                    <dt v-tooltip="trans('Collection')" class="flex-none pl-1">
                        <FontAwesomeIcon :icon="faMapPin" class="text-gray-500" fixed-width aria-hidden="true"/>
                    </dt>
                    <dd class="text-gray-500 flex items-center gap-x-2" xv-tooltip="trans('Estimated weight of all products')">
                        <Toggle
                            :modelValue="get(props.order, ['new_is_collection'], get(props.order, ['is_collection'], false))"
                            @update:model-value="(e) => (set(props.order, ['new_is_collection'], e), updateCollection(e))"
                            :loading="isLoadingCollection"
                            :disabled="!props.isInBasket"
                        />
                        <span class="text-sm"
                            :class="get(props.order, ['new_is_collection'], get(props.order, ['is_collection'], false)) ? 'text-green-600' : 'text-gray-500'"
                        >{{ trans("Collection") }}</span>
                    </dd>
                </dl>

            </div>

            <!-- Section: Billing Address -->
            <div v-if="!get(props.order, ['is_collection'], false)" class="">
                <div class="font-semibold">
                    <FontAwesomeIcon :icon="faDollarSign" class="" fixed-width aria-hidden="true" />
                    {{ trans("Billing Address") }}
                </div>
                <div v-if="summary?.customer?.addresses?.billing?.formatted_address" class="pl-6 pr-3" v-html="summary?.customer?.addresses?.billing?.formatted_address">
            
                </div>
                <div v-else class="text-gray-400 italic pl-6 pr-3">
                    {{ trans("No billing address") }}
                </div>
            </div>
            
            <!-- Section: Delivery Address -->
            <div v-if="!get(props.order, ['is_collection'], false)" class="">
                <div class="font-semibold">
                    <FontAwesomeIcon :icon="faClipboard" class="" fixed-width aria-hidden="true" />
                    {{ trans("Delivery Address") }}
                </div>
                <div v-if="summary?.customer?.addresses?.delivery?.formatted_address" class="pl-6 pr-3" v-html="summary?.customer?.addresses?.delivery?.formatted_address">
                </div>
                <div v-else class="text-gray-400 italic pl-6 pr-3">
                    {{ trans("No delivery address") }}
                </div>
                <div v-if="address_management?.address_update_route" @click="isModalShippingAddress = true"
                    class="pl-6 pr-3 w-fit underline cursor-pointer hover:text-gray-700">
                    {{ trans("Edit") }}
                    <FontAwesomeIcon icon="fal fa-pencil" class="" fixed-width aria-hidden="true"/>
                </div>
            
                <div v-if="is_unable_dispatch" class="pl-6 pr-4 text-red-500 mt-2 text-xs">
                    <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="mr-1" fixed-width aria-hidden="true" />{{ trans("We cannot deliver to :_country, please update the address or contact support.", { _country: summary?.customer?.addresses?.delivery?.country?.name }) }}
                </div>
            </div>

            <!-- Section: Offer meters -->
            <div v-if="Object.keys(layout?.offer_meters || {})?.length" class="border-t border-gray-300 pt-4 col-span-2 px-1">
                <div v-for="offer in layout?.offer_meters" class="grid grid-cols-2 mb-3">
                    <div :class="convertToFloat2(offer.metadata?.current) >= convertToFloat2(offer.metadata?.target) ? 'text-green-700' : ''"
                        class="flex items-center whitespace-nowrap"
                    >
                        <div v-if="convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target)" class="text-base">
                            {{ offer.label}}
                        </div>
                        <div v-else class="text-base text-green-600">
                            {{ offer.label_got ?? offer.label}}
                        </div>

                        <InformationIcon v-if="offer.information" :information="offer.information" class="ml-1" />
                        <FontAwesomeIcon v-if="!(convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target))" icon="fas fa-check-circle" class="ml-1" fixed-width aria-hidden="true" />
                    </div>
                    
                    <!-- Section: meter -->
                    <div v-tooltip="convertToFloat2(offer.metadata?.target) && convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target)
                        ? trans(`:xcurrentx of :xtargetx products gross amount`, { xcurrentx: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(offer.metadata?.current)), xtargetx: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(offer.metadata?.target)) })
                        : trans('Bonus secured')" class="w-full flex items-center">
                        <div class="w-full rounded-full h-2 bg-gray-200 relative overflow-hidden">
                            <div class="absolute  left-0   top-0 h-full w-3/4 transition-all duration-1000 ease-in-out"
                                :class="convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target) ? 'shimmer bg-green-400' : 'bg-green-500'"
                                :style="{
                                    width: convertToFloat2(offer.metadata?.target) ? convertToFloat2(offer.metadata?.current)/convertToFloat2(offer.metadata?.target) * 100 + '%' : '100%'
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: amount of balance, charges, shipping, tax -->
        <div class="col-span-2 md:col-span-1">
            <div v-if="!order" class="border-b border-gray-200 pb-0.5 flex justify-between pl-1.5 pr-4 mb-1.5">
                <div class="">{{ trans("Current balance") }}:</div>
                <div>
                    {{ locale.currencyFormat(layout?.iris?.currency?.code, balance ?? 0) }}
                </div>
            </div>

            <div v-else-if="order?.state === 'cancelled'" class="mb-2.5">
                <div class="text-yellow-600 border-yellow-500 bg-yellow-200 border rounded-md px-3 py-2">
                    <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="" fixed-width aria-hidden="true" />
                    {{ trans("Order cancelled, any payments made have been returned to your balance") }}
                </div>
            </div>

            <template v-else-if="summary?.products?.payment?.pay_status != 'no_need' && !isInBasket">
                <div class="w-full mb-2.5">
                    <!-- Section: pay with balance (if order Submit without paid) -->
                    <div class="w-full rounded-md shadow pxb-2 isolate border overflow-hidden"
                        :class="[
                            Number(summary.products.payment.pay_amount) <= 0 ? 'border-green-300' : 'border-red-500',
                        ]"
                    >
                        <NeedToPayV2Retina
                            :totalAmount="summary.products.payment.total_amount"
                            :paidAmount="summary.products.payment.paid_amount"
                            :payAmount="summary.products.payment.pay_amount"
                            :balance="balance || 0"
                            :payments="summary?.payments"
                            :currencyCode="layout.iris?.currency?.code"
                            :toBePaidBy="order?.to_be_paid_by"
                            :order="order"
                        >
                            <template #default>
                
                
                
                            </template>
                        </NeedToPayV2Retina>
                    </div>
                </div>
            </template>

            
            <div class="border border-gray-200 p-2 rounded">
                <OrderSummary
                    :order_summary="summary.order_summary"
                    :currency_code="layout?.iris?.currency?.code"
                />
            </div>
        </div>

        <!-- Section: Edit Delivery address -->
        <Modal v-if="address_management"
            :isOpen="isModalShippingAddress"
            @onClose="() => (isModalShippingAddress = false)"
            width="w-full max-w-lg"
            closeButton
        >
            <AddressEditModal
                :addresses="address_management.addresses"
                :address="summary?.customer?.addresses?.delivery"
                :updateRoute="address_management.address_update_route"
                @submitted="() => (isModalShippingAddress = false)"
                closeButton
                :copyAddress="contact_address"
            >
                <template #copy_address="{ address, isEqual }">
                    <div v-if="isEqual" class="text-gray-500 text-sm">
                        {{ trans("Same as the contact address") }}
                        <FontAwesomeIcon v-if="isEqual" v-tooltip="trans('Same as contact address')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                    </div>

                    <div v-else class="underline text-sm text-gray-500 hover:text-blue-700 cursor-pointer">
                        {{ trans("Copy from contact address") }}
                    </div>
                </template>
            </AddressEditModal>
        </Modal>
    </div>
</template>
