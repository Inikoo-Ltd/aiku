<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboard, faDollarSign, faWeight, faMapPin } from "@fal"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { Address } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { get, set } from "lodash"
import { notify } from "@kyvg/vue3-notification"
import { routeType } from "@/types/route"
import { router } from "@inertiajs/vue3"
import { Rating } from "primevue"
import { faStar } from "@fas"
import { faCube, faFolder } from "@far"

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
        products: {}
        delivery_notes: {}
        invoices: {}
        customer: {}
    }
    order: {
        id: number
        is_collection: boolean
    }
    review_summary?: {
        family_review: number
        total_family_review: number
        total_product_review: number
        overall_review: number
        average_review: number
    }
    is_unable_dispatch?: boolean
    contact_address?: Address | null
    isInBasket?: boolean
    updateRoute: routeType
    missed_offers: {}
}>()

const locale = inject("locale", {})
const layout = inject("layout", retinaLayoutStructure)

const isModalShippingAddress = ref(false)

// Method: convert "15.26" to 15.26
const convertToFloat2 = (val: any) => {
    const num = parseFloat(val)
    if (isNaN(num)) return 0.0
    return parseFloat(num.toFixed(2))
}

// Collection feature methods
const isLoadingCollection = ref(false)
const updateCollection = (value: boolean) => {
    const payload = {
        collection_address_id: value
            ? props.address_management?.addresses?.current_selected_address_id
            : null,
    }

    if (props.updateRoute?.name) {
        router.patch(route(props.updateRoute?.name, props.updateRoute.parameters), payload, {
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
        })
    }
}
</script>

<template>
    <div class="py-4 grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-6 px-4">
        <div class="col-span-2 grid grid-cols-2 gap-y-4">
            <!-- Section: Billing Address -->
            <div class="">
                <div class="font-semibold">
                    <FontAwesomeIcon :icon="faDollarSign" class="" fixed-width aria-hidden="true" />
                    {{ trans("Billing Address") }}
                </div>
                <div v-if="summary?.customer?.addresses?.billing?.formatted_address" class="pl-6 pr-3"
                    v-html="summary?.customer?.addresses?.billing?.formatted_address"></div>
                <div v-else class="text-gray-400 italic pl-6 pr-3">
                    {{ trans("No billing address") }}
                </div>
            </div>

            <div class="">
                <!-- Field: Collection (toggle) -->

                <div v-if="get(props.order, ['is_collection'], false)"
                    class="bg-gray-50 w-full text-center py-2 border border-gray-300 rounded">
                    <FontAwesomeIcon :icon="faMapPin" class="text-gray-500" fixed-width aria-hidden="true" />
                    {{ trans("This order is for collection only") }}.
                </div>

                <!-- Section: Delivery Address -->
                <div v-if="!get(props.order, ['is_collection'], false)" class="">
                    <div class="font-semibold">
                        <FontAwesomeIcon :icon="faClipboard" class="" fixed-width aria-hidden="true" />
                        {{ trans("Delivery Address") }}
                    </div>
                    <div v-if="summary?.customer?.addresses?.delivery?.formatted_address" class="pl-6 pr-3"
                        v-html="summary?.customer?.addresses?.delivery?.formatted_address"></div>
                    <div v-else class="text-gray-400 italic pl-6 pr-3">
                        {{ trans("No delivery address") }}
                    </div>

                    <div v-if="is_unable_dispatch" class="pl-6 pr-4 text-red-500 mt-2 text-xs">
                        <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="mr-1" fixed-width
                            aria-hidden="true" />{{
                                trans(
                                    "We cannot deliver to :_country, please update the address or contact support.",
                                    { _country: summary?.customer?.addresses?.delivery?.country?.name }
                        )
                        }}
                    </div>
                </div>
            </div>

            <!-- review summary -->
            <div class="col-span-2 flex items-center justify-between rounded-xl border border-gray-200 bg-white p-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">
                        {{ trans("Review") }}
                    </h2>

                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <div class="flex items-center gap-1 rounded-md bg-amber-100 px-2 py-1 text-amber-700" :v-tooltip="trans('overall review')">
                            <FontAwesomeIcon :icon="faStar" class="text-[11px]" />
                            <span>{{ review_summary?.overall_review }}/1</span>
                        </div>

                        <div class="flex items-center gap-1 rounded-md bg-blue-100 px-2 py-1 text-blue-700" :v-tooltip="trans('family review')">
                            <FontAwesomeIcon :icon="faFolder" class="text-[11px]" />
                            <span>
                                {{ review_summary?.family_review }}/{{ review_summary?.total_family_review }}
                            </span>
                        </div>

                        <div class="flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-1 text-emerald-700" :v-tooltip="trans('product review')">
                            <FontAwesomeIcon :icon="faCube" class="text-[11px]" />
                            <span>
                                {{ review_summary?.product_review }}/{{ review_summary?.total_product_review }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 rounded-lg border  px-3 py-2 rating">
                    <div class="text-center">
                        <div class="text-lg font-bold leading-none ">
                            {{ review_summary?.average_review?.toFixed(1) ?? "0.0" }}
                        </div>
                        <div class="text-[10px] text-gray-500">
                            Avg Rating
                        </div>
                    </div>

                    <Rating :modelValue="review_summary?.average_review ?? 0" :readonly="true" :disabled="true"
                        :cancel="false" class="scale-75 origin-right" />
                </div>
            </div>
        </div>

        <!-- Section: amount of balance, charges, shipping, tax -->
        <div class="col-span-2 md:col-span-1">
            <div>
                <!-- Field: weight -->
                <dl class="mt-1 flex items-center w-full flex-none gap-x-1.5">
                    <dt v-tooltip="trans('Weight')" class="flex-none pl-1">
                        <FontAwesomeIcon :icon="faWeight" fixed-width aria-hidden="true" class="text-gray-500" />
                    </dt>
                    <dd class="text-gray-500 sep" v-tooltip="trans('Estimated weight of all products')">
                        {{ summary?.order_properties?.weight || 0 }}
                    </dd>
                </dl>
            </div>

            <div class="border border-gray-200 p-2 rounded">
                <OrderSummary :order_summary="summary.order_summary" :currency_code="layout?.iris?.currency?.code" />
            </div>
        </div>

        <!-- Section: Edit Delivery address -->
        <Modal v-if="address_management" :isOpen="isModalShippingAddress"
            @onClose="() => (isModalShippingAddress = false)" width="w-full max-w-lg" closeButton>
            <AddressEditModal :addresses="address_management.addresses"
                :address="summary?.customer?.addresses?.delivery" :updateRoute="address_management.address_update_route"
                @submitted="() => (isModalShippingAddress = false)" closeButton :copyAddress="contact_address">
                <template #copy_address="{ address, isEqual }">
                    <div v-if="isEqual" class="text-gray-500 text-sm">
                        {{ trans("Same as the contact address") }}
                        <FontAwesomeIcon v-if="isEqual" v-tooltip="trans('Same as contact address')" icon="fal fa-check"
                            class="text-green-500" fixed-width aria-hidden="true" />
                    </div>

                    <div v-else class="underline text-sm text-gray-500 hover:text-blue-700 cursor-pointer">
                        {{ trans("Copy from contact address") }}
                    </div>
                </template>
            </AddressEditModal>
        </Modal>
    </div>
</template>

<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
	color: #f59e0b !important;
}
</style>