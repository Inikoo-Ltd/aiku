<script setup lang="ts">
    
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboard, faDollarSign, faPencil } from "@fal"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { AddressManagement } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"

const props = defineProps<{
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
    }
    balance?: string
    address_management?: AddressManagement
    is_unable_dispatch?: boolean
}>()

const locale = inject('locale', {})

const isModalShippingAddress = ref(false)

</script>

<template>
    <div class="py-4 grid grid-cols-3 px-4 ">
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

        <!-- Section: balance, charges, shipping, tax -->
        <div>
            <div class="border-b border-gray-200 pb-0.5 flex justify-between pl-1.5 pr-4 mb-1.5">
                <div class="">{{ trans("Current balance") }}:</div>
                <div>
                    {{ locale.currencyFormat(summary.order_summary?.currency?.data?.code, balance ?? 0) }}
                </div>
            </div>
            
            <div class="border border-gray-200 p-2 rounded">
                <OrderSummary
                    :order_summary="summary.order_summary"
                    :currency_code="summary.order_summary?.currency?.data?.code"
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
            />
        </Modal>
    </div>
</template>
