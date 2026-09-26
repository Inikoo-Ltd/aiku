<script setup lang="ts">
    
import { FontAwesomeIcon, FontAwesomeLayers } from "@fortawesome/vue-fontawesome"
import { faSortNumericDown, faWeight, faMapPin, faChevronDown, faBadgePercent } from "@fal"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { ctrans } from "@/Composables/useTrans"
import { computed, inject, ref } from "vue"
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import NeedToPayV2Retina from "@/Components/Utils/NeedToPayV2Retina.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { get, set } from "lodash"
import { notify } from "@kyvg/vue3-notification"
import { routeType } from "@/types/route"
import { Link, router } from "@inertiajs/vue3"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import payments from "@/Pages/Grp/Overview/Accounting/Payments.vue"
import MissedOfferFOB from "@/Components/Iris/Offers/MissedOffers/MissedOfferFOB.vue"
import EcomUpcomingTransactions from "@/Components/Retina/Ecom/EcomUpcomingTransactions.vue"

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
    address_management?: AddressManagement
    earlier_delivery_address?: { previous_address: string, previous_address_line: string, previous_order_reference: string, current_address_line: string, confirmed: boolean, actions: { confirm_route: routeType, use_previous_route: routeType } | null } | null
    is_forbidden_delivery?: boolean
    is_forbidden_billing?: boolean
    contact_address?: Address | null
    isShowAllOffersMeter?: boolean  // Whether to show all offers meter or only the achieved offers
    updateRoute: routeType
    missed_offers: {}
    isInBasket?: boolean
    isInCheckout?: boolean
    changeAddressRoute?: routeType
    upcoming_transactions?: {
        data: {
            id: number
            product_code: string | null
            product_name: string | null
            quantity: number | string
            public_notes: string | null
            type: 'gift' | 'follow_on'
            state: string
        }[]
    }
}>()

const earlierDeliveryAddress = computed(() => props.earlier_delivery_address ?? props.address_management?.addresses?.earlier_delivery_address ?? null)

const choosingDeliveryAddress = ref<string | null>(null)
const chooseDeliveryAddress = (choice: string, action: routeType) => {
    router.patch(route(action.name, action.parameters), {}, {
        preserveScroll: true,
        onStart: () => { choosingDeliveryAddress.value = choice },
        onFinish: () => { choosingDeliveryAddress.value = null },
        onError: () => notify({ title: ctrans("Something went wrong"), text: ctrans("Please try again or contact support."), type: "error" }),
    })
}

const locale = inject('locale', {})
const isSummaryExpanded = ref(false)
const isCollection = computed(() => !!get(props.order, ['new_is_collection'], get(props.order, ['is_collection'], false)))
const billingAddressLine = computed(() => (props.summary?.customer?.addresses?.billing?.formatted_address ?? '')
    .replace(/<br\s*\/?>/gi, ', ')
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/\s+/g, ' ')
    .replace(/(\s*,\s*)+/g, ', ')
    .replace(/^,\s*|,\s*$/g, '')
    .trim())
const orderTotal = computed(() => {
    const summaryGroups = Object.values(props.summary?.order_summary ?? {}).filter(Array.isArray) as { price_total?: string | number }[][]
    return summaryGroups.at(-1)?.at(-1)?.price_total ?? 0
})
const layout = inject('layout', retinaLayoutStructure)

const isModalShippingAddress = ref(false)


// Method: convert "15.26" to 15.26
const convertToFloat2 = (val: any) => {
    const num = parseFloat(val)
    if (isNaN(num)) return 0.00
    return parseFloat(num.toFixed(2))
}

// Method: is the offer target reached
const isOfferFulfilled = (offer: any) => {
    return convertToFloat2(offer.metadata?.current) >= convertToFloat2(offer.metadata?.target)
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
                        title: ctrans("Something went wrong."),
                        text: ctrans("Failed to update to collection"),
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
        <div class="col-span-2 grid grid-cols-1 gap-y-4">
            <div class="">
                <div v-if="!isInBasket && get(props.order, ['is_collection'], false)" class="bg-gray-50 w-full text-center py-2 border border-gray-300 rounded">
                    <FontAwesomeIcon :icon="faMapPin" class="text-gray-600" fixed-width aria-hidden="true"/>
                    {{ ctrans("This order is for collection only") }}.
                </div>
                <!-- Section: Delivery Address -->
                <div v-if="isInBasket || !isCollection" class="flow-root">
                <div v-if="props.isInBasket" class="float-right ml-3 mb-2 flex flex-col divide-y divide-gray-200 rounded-md border border-gray-200 text-sm">
                    <button v-if="address_management?.address_update_route" type="button" @click="isModalShippingAddress = true"
                        class="px-3 py-2 text-left leading-tight text-gray-800 hover:text-gray-900 hover:bg-gray-50">
                        {{ ctrans("Edit") }}
                    </button>
                </div>
                    <div class="font-semibold">
                        {{ isCollection ? ctrans("Collection") : ctrans("Delivery Address") }}
                    </div>
                    <div v-if="isCollection" class="pr-3 text-gray-600">
                        {{ ctrans("You will collect this order from :shop", { shop: layout?.iris?.shop?.name ?? '' }) }}.
                    </div>
                    <template v-else>
                    <div v-if="summary?.customer?.addresses?.delivery?.formatted_address" class="pr-3 leading-snug text-gray-600" v-html="summary?.customer?.addresses?.delivery?.formatted_address">
                    </div>
                    <div v-else class="text-gray-400 italic pr-3">
                        {{ ctrans("No delivery address") }}
                    </div>
                    </template>
                    <div class="clear-both mt-3 rounded-md border border-gray-200 px-3 py-2 text-xs text-gray-600 leading-snug">
                        <span class="font-medium">{{ ctrans("Billing address") }}:</span>
                        {{ billingAddressLine || ctrans("No billing address") }}
                        <Link :href="route('retina.sysadmin.settings.edit')" class="ml-1 whitespace-nowrap underline hover:text-gray-800">
                            {{ ctrans("Edit") }}
                        </Link>
                    </div>
                    <div v-if="is_forbidden_billing" class="text-red-500 mt-2 text-xs">
                        <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="mr-1" fixed-width aria-hidden="true" />{{ ctrans("Your current billing address (:_country) is marked as forbidden, please update the address or contact support.", { _country: summary?.customer?.addresses?.billing?.country?.name }) }}
                    </div>
                    <template v-if="!isCollection">
                
                    <div v-if="earlierDeliveryAddress && !earlierDeliveryAddress.confirmed" class="mr-3 mt-2 text-xs text-yellow-800 bg-yellow-50 border border-yellow-300 rounded px-2.5 py-2">
                        <div>
                            <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="mr-1" fixed-width aria-hidden="true" />{{ ctrans("Your last order went to:") }}
                        </div>
                        <div class="mt-1 pl-5" v-html="earlierDeliveryAddress.previous_address"></div>
                        <div v-if="earlierDeliveryAddress.actions" class="mt-2 pl-5 flex flex-col gap-y-1.5">
                            <div class="block w-full text-center cursor-pointer rounded-md border border-yellow-400 bg-yellow-100 px-3 py-1.5 font-medium hover:bg-yellow-200"
                                :class="choosingDeliveryAddress ? 'pointer-events-none opacity-50' : ''"
                                @click="chooseDeliveryAddress('confirm', earlierDeliveryAddress.actions.confirm_route)">
                                {{ ctrans("Yes, deliver to :_address", { _address: earlierDeliveryAddress.current_address_line }) }}
                            </div>
                            <div class="block w-full text-center cursor-pointer rounded-md border border-yellow-400 bg-yellow-100 px-3 py-1.5 font-medium hover:bg-yellow-200"
                                :class="choosingDeliveryAddress ? 'pointer-events-none opacity-50' : ''"
                                @click="chooseDeliveryAddress('previous', earlierDeliveryAddress.actions.use_previous_route)">
                                {{ ctrans("Deliver to :_address instead", { _address: earlierDeliveryAddress.previous_address_line }) }}
                            </div>
                            <Link v-if="changeAddressRoute?.name" :href="route(changeAddressRoute.name, changeAddressRoute.parameters)"
                                class="block w-full text-center cursor-pointer rounded-md border border-yellow-400 bg-yellow-100 px-3 py-1.5 font-medium hover:bg-yellow-200">
                                {{ ctrans("Use another address (in your basket)") }}
                            </Link>
                        </div>
                    </div>

                    <div v-if="is_forbidden_delivery" class="pr-4 text-red-500 mt-2 text-xs">
                        <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="mr-1" fixed-width aria-hidden="true" />{{ ctrans("We cannot deliver to :_country, please update the address or contact support.", { _country: summary?.customer?.addresses?.delivery?.country?.name }) }}
                    </div>
                    </template>
                </div>
            </div>


            <!-- Section: Offer meters (free gift, etc)-->
            <div v-if="Object.keys(layout?.offer_meters || {})?.length" class="border-t border-gray-300 pt-6 col-span-full px-1 flex flex-col gap-y-3">
                <template v-for="(offer, offerIndex) in layout?.offer_meters" :key="offerIndex">
                    <div v-if="isShowAllOffersMeter || isOfferFulfilled(offer)" class="grid grid-cols-2 gap-x-4">
                        <!-- Title: is gift -->
                        <div v-if="offer.is_gift" :class="convertToFloat2(offer.metadata?.current) >= convertToFloat2(offer.metadata?.target) ? 'text-green-700' : ''"
                            class="flex items-center whitespace-nowrap text-ellipsis truncate w-full"
                        >
                            <FontAwesomeIcon icon='fal fa-gift' class='opacity-60 mr-1 shrink-0' fixed-width aria-hidden='true' />
                            <span class="font-bold">{{ ctrans('Gift') }}</span>:

                            <InformationIcon v-if="offer.information" :information="offer.information" class="ml-1" />
                            <!-- <FontAwesomeIcon v-if="!(convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target))" icon="fas fa-check-circle" class="ml-1" fixed-width aria-hidden="true" /> -->

                            <span class="ml-2 xopacity-70">
                                {{ offer.label }}
                            </span>
                        </div>

                        <div v-else :class="convertToFloat2(offer.metadata?.current) >= convertToFloat2(offer.metadata?.target) ? 'text-green-700' : ''"
                            class="flex items-center whitespace-nowrap text-ellipsis truncate w-full"
                        >
                            <FontAwesomeIcon :icon="faBadgePercent" class="opacity-60 mr-1 shrink-0" fixed-width aria-hidden="true" />
                            <div v-if="convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target)" v-tooltip="offer.label" class="text-ellipsis truncate">
                                {{ offer.label}}
                            </div>
                            <div v-else v-tooltip="offer.label_got ?? offer.label" class="text-green-600 text-ellipsis truncate">
                                {{ offer.label_got ?? offer.label}}
                            </div>

                            <InformationIcon v-if="offer.information" :information="offer.information" class="ml-1" />
                            <FontAwesomeIcon v-if="!(convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target))" icon="fas fa-check-circle" class="ml-1" fixed-width aria-hidden="true" />
                        </div>
                        
                        <!-- Section: meter -->
                        <div v-if="isShowAllOffersMeter" v-tooltip="convertToFloat2(offer.metadata?.target) && convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target)
                            ? ctrans(`:xcurrentx / :xtargetx  (Spend at least :xtargetx to get the offer)`, { xcurrentx: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(offer.metadata?.current)), xtargetx: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(offer.metadata?.target)) })
                            : ctrans('Offer activated')" class="w-full flex items-center">
                            <div class="w-full rounded-full h-2 bg-gray-200 relative overflow-hidden">
                                <div class="absolute  left-0   top-0 h-full w-3/4 transition-all duration-1000 ease-in-out"
                                    :class="convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target) ? 'shimmer bg-green-400' : 'bg-green-500'"
                                    :style="{
                                        width: convertToFloat2(offer.metadata?.target) ? convertToFloat2(offer.metadata?.current)/convertToFloat2(offer.metadata?.target) * 100 + '%' : '100%'
                                    }"
                                />
                            </div>
                        </div>

                        <!-- Section: fulfilled check (when not in basket) -->
                        <div v-else class="w-full flex items-center justify-end">
                            <span v-tooltip="ctrans('Offer activated')">
                                <FontAwesomeIcon icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Section: Missed Offers -->
            <Transition v-if="layout.app.environment === 'local'" name="slide-to-right">
                <div v-if="Object.values(missed_offers || {}).length" class="xborder border-red-200 xbg-red-50 rounded-md p-3">
                    <div class="text-xs text-red-500 font-bold mb-0">
                        <!-- <span class="bg-red-500 text-white">(local only)</span> -->
                        {{ ctrans('You missed ( :numberMissedOffer ) offers', { numberMissedOffer: Object.values(missed_offers || {}).length }) }}
                    </div>
                    <div class="flex flex-col gap-y-2">
                        <TransitionGroup name="list" tag="ul" class="!m-0 space-y-2">
                            <li v-for="(missed_offer, misOfferKey) in missed_offers" :key="misOfferKey" class="list-none">
                                <MissedOfferFOB v-if="misOfferKey === 'fob'" :data="missed_offer" />
                                <div v-else class="bg-[#2a919e] text-white px-2 py-2 rounded-md text-sm flex items-center gap-x-2">
                                    <InformationIcon :information="missed_offer.label" class="text-2xl" />
                                    <div>{{ missed_offer.label }}</div>
                                </div>
                            </li>
                        </TransitionGroup>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Section: amount of balance, charges, shipping, tax -->
        <div class="col-span-2 md:col-span-1">
            <div v-if="!order" class="border-b border-gray-200 pb-0.5 flex justify-between pl-1.5 pr-4 mb-1.5">
                <div class="">{{ ctrans("Current balance") }}:</div>
                <div>
                    {{ locale.currencyFormat(layout?.iris?.currency?.code, balance ?? 0) }}
                </div>
            </div>

            <div v-else-if="order?.state === 'cancelled'" class="mb-2.5">
                <div class="text-yellow-600 border-yellow-500 bg-yellow-200 border rounded-md px-3 py-2">
                    <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="" fixed-width aria-hidden="true" />
                    {{ ctrans("Order cancelled, any payments made have been returned to your balance") }}
                </div>
            </div>

            <div v-else-if="order?.state === 'handling_blocked'">
                <!-- INI-1521: hide payment status if handling_blocked -->
            </div>

            <template v-else-if="summary?.products?.payment?.pay_status != 'no_need' && !(isInBasket || isInCheckout)">
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

            <div class="hidden md:block">
                <!-- Field: weight -->
                <dl class="mt-1 flex items-center w-full flex-none gap-x-1.5">
                    <dt v-tooltip="ctrans('Weight')" class="flex-none pl-1">
                        <FontAwesomeIcon :icon="faWeight" fixed-width aria-hidden="true" class="text-gray-600" />
                    </dt>
                    <dd class="text-gray-600 sep" v-tooltip="ctrans('Estimated weight of all products')">
                        {{ summary?.order_properties?.weight || 0 }}
                    </dd>
                </dl>
            </div>

            <button
                type="button"
                class="md:hidden w-full flex items-center justify-between border border-gray-200 rounded px-3 py-2.5 text-sm font-medium focus:outline-none"
                :class="isSummaryExpanded ? 'rounded-b-none border-b-0' : ''"
                :aria-expanded="isSummaryExpanded"
                @click="isSummaryExpanded = !isSummaryExpanded"
            >
                <span class="flex items-baseline gap-x-2">
                    {{ ctrans("Total") }}
                    <span class="text-xs font-normal text-gray-600 underline decoration-dotted underline-offset-2">
                        {{ isSummaryExpanded ? ctrans("Hide breakdown") : ctrans("Show breakdown") }}
                        <FontAwesomeIcon :icon="faChevronDown" class="transition-transform" :class="isSummaryExpanded ? '' : 'rotate-180'" fixed-width aria-hidden="true" />
                    </span>
                </span>
                <span class="text-base font-semibold">{{ locale.currencyFormat(layout?.iris?.currency?.code, orderTotal) }}</span>
            </button>
            <div class="border border-gray-200 p-2 rounded" :class="isSummaryExpanded ? 'rounded-t-none' : 'hidden md:block'">
                <OrderSummary
                    class="!text-gray-600"
                    :order_summary="summary.order_summary"
                    :currency_code="layout?.iris?.currency?.code"
                >
                    <template #cell_shipping_1="{ fieldSummary }">
                        <dt class="col-span-3 flex flex-col">
                            <div class="flex items-center leading-none" :class="fieldSummary.label_class">
                                <span
                                    :class="fieldSummary.data.discounted_shipping_offer_id ? 'text-green-500' : ''"
                                >
                                    {{ fieldSummary.label }}
                                </span>

                                <span v-tooltip="ctrans('Estimated weight of all products')" class="md:hidden ml-1.5 text-gray-400 flex items-center gap-x-0.5 whitespace-nowrap">
                                    <FontAwesomeIcon :icon="faWeight" fixed-width aria-hidden="true" class="text-xs" />
                                    {{ summary?.order_properties?.weight || 0 }}
                                </span>

                                <FontAwesomeLayers v-if="fieldSummary.data.discounted_shipping_offer_id" v-tooltip="ctrans('Shipping discount')" class="ml-1 me-2 text-green-500">
                                    <FontAwesomeIcon fixed-width icon="fal fa-truck"/>
                                    <FontAwesomeIcon fixed-width icon="fas fa-percent" style="left: unset; right: 6px; bottom: 2px; width: 30%;"/>
                                </FontAwesomeLayers>

                                <!-- qq{{ fieldSummary.data.discounted_shipping_offer_id }}ww -->
                                <FontAwesomeIcon v-if="fieldSummary.information_icon" icon='fal fa-question-circle'
                                    v-tooltip="fieldSummary.information_icon"
                                    class='ml-1 cursor-pointer text-gray-400 hover:text-gray-600' fixed-width
                                    aria-hidden='true'
                                />
                                
                            </div>
                            <span v-if="fieldSummary.information" v-tooltip="fieldSummary.information" class="text-xs text-gray-400 truncate">
                                {{ fieldSummary.information }}
                            </span>
                        </dt>
                    </template>
                </OrderSummary>
            </div>
        </div>
        <!-- Section: Upcoming transactions (gift, follow on) -->
        <div v-if="upcoming_transactions?.data?.length" class="col-span-2 md:col-span-3">
            <EcomUpcomingTransactions :upcomingTransactions="upcoming_transactions" />
        </div>
        <!-- Section: Edit Delivery address -->
        <Modal v-if="address_management"
            :isOpen="isModalShippingAddress"
            @onClose="() => (isModalShippingAddress = false)"
            width="w-full max-w-lg"
            closeButton
        >
            <label v-if="isInBasket" class="mb-5 mr-6 flex items-center gap-x-3 rounded-md border px-3 py-3 cursor-pointer select-none transition-colors"
                :class="isCollection ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:bg-gray-50'">
                <input
                    type="checkbox"
                    class="size-5 rounded border-gray-300 text-green-600 focus:ring-green-500"
                    :checked="isCollection"
                    :disabled="isLoadingCollection || !props.updateRoute?.name"
                    @change="(event) => { const isChecked = (event.target as HTMLInputElement).checked; set(props.order, ['new_is_collection'], isChecked); updateCollection(isChecked) }"
                />
                <span class="font-medium text-gray-800">{{ ctrans("I want to collect this order from your warehouse") }}</span>
                <LoadingIcon v-if="isLoadingCollection" class="ml-auto" />
            </label>
            <Button v-if="isCollection" :label="ctrans('Done')" full @click="() => (isModalShippingAddress = false)" />
            <AddressEditModal
                v-else
                :addresses="address_management.addresses"
                :address="summary?.customer?.addresses?.delivery"
                :updateRoute="address_management.address_update_route"
                @submitted="() => (isModalShippingAddress = false)"
                closeButton
                :copyAddress="contact_address"
            >
                <template #copy_address="{ address, isEqual }">
                    <div v-if="isEqual" class="text-gray-600 text-sm">
                        {{ ctrans("Same as the contact address") }}
                        <FontAwesomeIcon v-if="isEqual" v-tooltip="ctrans('Same as contact address')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                    </div>

                    <div v-else class="underline text-sm text-gray-600 hover:text-blue-700 cursor-pointer">
                        {{ ctrans("Copy from contact address") }}
                    </div>
                </template>
            </AddressEditModal>
        </Modal>
    </div>
</template>
