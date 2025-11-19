<script setup lang="ts">
    
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboard, faDollarSign, faSortNumericDown, faWeight } from "@fal"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"

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
    }
    balance?: string
    address_management?: AddressManagement
    is_unable_dispatch?: boolean
    contact_address?: Address | null
    currency_code?: string
}>()

const locale = inject('locale', {})

const isModalShippingAddress = ref(false)

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
                        {{ summary?.order_properties.weight || 0 }}
                    </dd>
                </dl>

                <!-- Field: number of order -->
                <!-- <dl class="mt-1 flex items-center w-full flex-none gap-x-1.5">
                    <dt zv-tooltip="trans('Weight')" class="flex-none pl-1">
                        <FontAwesomeIcon :icon="faSortNumericDown" fixed-width aria-hidden="true" class="text-gray-500" />
                    </dt>
                    <dd class="text-gray-500 sep" v-tooltip="summary?.order_properties?.customer_order_ordinal_tooltip ?? trans('Customer order number')">
                        {{ summary?.order_properties?.customer_order_ordinal || 0 }}
                    </dd>
                </dl> -->
            </div>


            <!-- Section: Billing Address -->
            <div class="">
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
            <div class="">
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
                    <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="mr-1" fixed-width aria-hidden="true" />{{ trans("We cannot deliver to :country, please update the address or contact support.", { country: summary?.customer?.addresses?.delivery?.country?.name }) }}
                </div>
            </div>
        </div>

        <!-- Section: amount of balance, charges, shipping, tax -->
        <div class="col-span-2 md:col-span-1">
            <div class="border-b border-gray-200 pb-0.5 flex justify-between pl-1.5 pr-4 mb-1.5">
                <div class="">{{ trans("Current balance") }}:</div>
                <div>
                    {{ locale.currencyFormat(summary.order_summary?.currency?.data?.code, balance ?? 0) }}
                </div>
            </div>
            
            <div class="border border-gray-200 p-2 rounded">
                <OrderSummary
                    :order_summary="summary.order_summary"
                    :currency_code="currency_code"
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
