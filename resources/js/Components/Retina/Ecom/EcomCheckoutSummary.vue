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
}>()

const locale = inject('locale', {})

const isModalShippingAddress = ref(false)

</script>

<template>
    <div class="py-4 grid grid-cols-3 px-4">
        <div>
            <!-- Section: Current balance -->
            <!-- <dl class="ml-5 mb-6 relative isolate bg-indigo-50 border border-indigo-200 rounded shadow px-4 py-5 sm:px-5 sm:py-3 overflow-hidden grid items-center max-w-72">
                <div class="-z-10 absolute  top-1/2 -translate-y-1/2 transform-gpu blur-2xl" aria-hidden="true">
                    <div class="aspect-[577/310] w-[36.0625rem] bg-gradient-to-r from-[#ff80b5] to-[#9089fc] opacity-30" style="clip-path: polygon(74.8% 41.9%, 97.2% 73.2%, 100% 34.9%, 92.5% 0.4%, 87.5% 0%, 75% 28.6%, 58.5% 54.6%, 50.1% 56.8%, 46.9% 44%, 48.3% 17.4%, 24.7% 53.9%, 0% 27.9%, 11.9% 74.2%, 24.9% 54.1%, 68.6% 100%, 74.8% 41.9%)" />
                </div>

                <dt class="text-base font-normal opacity-70">
                    {{ trans("Current balance") }}
                </dt>

                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                    <div class="flex items-baseline text-2xl font-semibold text-indigo-600">
                        {{ locale.currencyFormat(summary.order_summary?.currency?.data?.code, balance ?? 0) }}
                    </div>
                </dd>
            </dl> -->

            <!-- Section: Invoice Address -->
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
        </div>
        
        <div>
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
            </div>
        </div>

        <!-- Summary -->
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

        <!-- Section: Delivery address -->
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