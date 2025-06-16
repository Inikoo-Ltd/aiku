<script setup lang="ts">

import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { AddressManagement } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faIdCardAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faIdCardAlt)

defineProps<{
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
        customer_channel: {
            status: boolean
            platform: {
                name: string
                image: string
            }
        }
    }
    balance?: string
    address_management?: AddressManagement
}>()

const locale = inject('locale', {})

const isModalShippingAddress = ref(false)

</script>

<template>

    <div class="py-4 grid grid-cols-7 px-4 gap-x-6">
        <div class="col-span-2">
            <!-- Field: Platform -->
            <div v-if="summary?.customer_channel?.status" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div v-tooltip="trans('Platform')" class="flex-none">
                    <FontAwesomeIcon
                        icon="fal fa-parachute-box"
                        class="text-gray-400" fixed-width />
                </div>
                <div class="flex items-center gap-x-2">
                    <img v-tooltip="summary?.customer_channel?.platform?.name" :src="summary?.customer_channel?.platform?.image" alt="" class="h-6">
                </div>
            </div>

            <!-- Field: Reference Number -->
            <!-- <pre>{{ summary.customer_channel }}</pre> -->
            <Link v-if="summary?.customer_client.ulid" as="a" v-tooltip="trans('Reference')"
                :href="route('retina.dropshipping.customer_sales_channels.client.show', [summary.customer_channel?.slug, summary?.customer_client.ulid])"
                class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
                <div class="flex-none">
                    <FontAwesomeIcon icon='fal fa-user' class='text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <dd class="text-sm text-gray-500">#{{ summary?.customer_client.reference }}</dd>
            </Link>

            <!-- Field: Contact name -->
            <div v-if="summary?.customer_client.contact_name" v-tooltip="trans('Contact name')"
                class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div class="flex-none">
                    <FontAwesomeIcon icon='fal fa-id-card-alt' class='text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <dd class="text-sm text-gray-500">{{ summary?.customer_client.contact_name }}</dd>
            </div>

            <!-- Field: Company name -->
            <div v-if="summary?.customer_client.company_name" v-tooltip="trans('Company name')"
                class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div class="flex-none">
                    <FontAwesomeIcon icon='fal fa-building' class='text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <dd class="text-sm text-gray-500">{{ summary?.customer_client.company_name }}</dd>
            </div>

            <!-- Field: Email -->
            <div v-if="summary?.customer_client.email" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div v-tooltip="trans('Email')" class="flex-none">
                    <FontAwesomeIcon icon='fal fa-envelope' class='text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <a :href="`mailto:${summary?.customer_client.email}`" v-tooltip="'Click to send email'"
                    class="text-sm text-gray-500 hover:text-gray-700 truncate">{{ summary?.customer_client.email }}</a>
            </div>

            <!-- Field: Phone -->
            <div v-if="summary?.customer_client.phone" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div v-tooltip="trans('Phone')" class="flex-none">
                    <FontAwesomeIcon icon='fal fa-phone' class='text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <a :href="`tel:${summary?.customer_client.phone}`" v-tooltip="'Click to make a phone call'"
                    class="text-sm text-gray-500 hover:text-gray-700">{{ summary?.customer_client.phone }}</a>
            </div>

            <!-- Field: Shipping Address -->
            <div v-if="summary?.customer?.addresses?.delivery?.formatted_address"
                class="mt-2 pl-1 flex items w-full flex-none gap-x-2" v-tooltip="trans('Shipping address')">
                <div class="flex-none">
                    <FontAwesomeIcon icon='fal fa-shipping-fast' class='text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <dd class="w-full text-gray-500 text-xs relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50"
                    >
                    <div v-html="summary?.customer?.addresses?.delivery?.formatted_address"></div>
                    <div v-if="address_management" @click="isModalShippingAddress = true" class="underline cursor-pointer hover:text-gray-700">
                        {{ trans("Edit") }}
                        <FontAwesomeIcon icon="fal fa-pencil" class="" fixed-width aria-hidden="true" />
                    </div>
                </dd>
            </div>

        </div>

        <div class="col-span-2">
        </div>

        <div class="col-span-3">
            <div v-if="balance !== undefined" class="border-b border-gray-200 pb-0.5 flex justify-between pl-1.5 pr-4 mb-1.5 text-amber-600">
                <div class="">{{ trans("Current balance") }}:</div>
                <div class="">
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
        <Modal v-if="address_management" :isOpen="isModalShippingAddress" @onClose="() => (isModalShippingAddress = false)" width="w-full max-w-4xl">
            <AddressEditModal
                :addresses="address_management.addresses"
                :address="summary?.customer?.addresses?.delivery"
                :updateRoute="address_management.address_update_route"
                :address_modal_title="address_management.address_modal_title"
                @onDone="() => (isModalShippingAddress = false)"
            />
        </Modal>
    </div>
</template>
