<script setup lang="ts">

import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, onMounted, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { AddressManagement } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"
import Icon from "@/Components/Icon.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFilePdf, faIdCardAlt, faTruck, faWeight, faMapPin } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ToggleSwitch from 'primevue/toggleswitch';
import { notify } from "@kyvg/vue3-notification"

library.add(faIdCardAlt, faWeight, faMapPin)

const props = defineProps<{
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
        order_properties: {
            weight: string
            shipments: {
                name: string
                tracking?: string
                label?: string
                label_type?: string
                combined_label_url?: string
            }[]
        }
        customer_channel: {
            status: boolean
            platform: {
                name: string
                image: string
            }
        }
        invoices: any
        delivery_notes: Array<any>
        customer?: {
            addresses?: {
                delivery?: {
                    formatted_address: string
                }
            }
        }
    }
    balance?: string
    address_management: AddressManagement
    dataPalletReturn?: {
        is_collection: boolean
    }
    isOrder?: boolean
    boxStats?: {
        fulfilment_customer?: {
            address?: {
                value?: {
                    formatted_address: string
                }
            }
        }
    }
    listError?: {
        box_stats_delivery_address?: boolean
    }
}>()

const locale = inject('locale', {})

const isModalShippingAddress = ref(false)

// Collection feature reactive variables
const isDeliveryAddressManagementModal = ref(false)
const isCollection = ref(false)
const collectionBy = ref('myself')
const textValue = ref<string | null>('')

// Collection feature methods
const updateCollection = async (e: Event) => {
    const target = e.target as HTMLInputElement
    const payload = {
        collection_address_id: target.checked ? props.address_management.addresses.current_selected_address_id : null
    }
    try {
        router.patch(route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters), {
            ...payload
        })
    } catch (error) {
        console.error(error)
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to update to collection"),
            type: "error",
        })
    }
}


const updateCollectionType = () => {
    const payload: Record<string, any> = {
        collection_by: collectionBy.value,
    }

    if (collectionBy.value === 'myself') {
        payload.shipping_notes = null
        textValue.value = null // also clear in frontend
    }

    router.patch(
        route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Collection type updated successfully"),
                    type: "success",
                })
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to update collection type"),
                    type: "error",
                })
            },
        }
    )
}

const updateCollectionNotes = () => {
    router.patch(
        route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters),
        {shipping_notes: textValue.value},
        {
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Text updated successfully"),
                    type: "success",
                })
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to update text"),
                    type: "error",
                })
            },
        }
    )
}

onMounted(() => {
    isCollection.value = Boolean(props.address_management?.addresses?.collection_address_id)
});

</script>

<template>

    <div class="py-4 grid grid-cols-2 md:grid-cols-7 px-4 gap-x-4 divide-y divide-gray-200 md:divide-y-0 md:divide-x">
        <div class="col-span-2 mb-4 md:mb-0">
            <!-- Field: Platform -->
            <div v-if="summary?.customer_channel?.status" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div v-tooltip="trans('Platform')" class="flex-none">
                    <FontAwesomeIcon icon="fal fa-parachute-box" class="text-gray-400" fixed-width/>
                </div>
                <div class="flex items-center gap-x-2">
                    <img v-tooltip="summary?.customer_channel?.platform?.name"
                         :src="summary?.customer_channel?.platform?.image" alt="" class="h-6">
                </div>
            </div>

            <!-- Field: Reference Number -->

            <Link v-if="summary?.customer_client?.ulid" as="a" v-tooltip="trans('Client')"
                  :href="route('retina.dropshipping.customer_sales_channels.client.show', [summary.customer_channel?.slug, summary?.customer_client.ulid])"
                  class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
                <div class="flex-none">
                    <FontAwesomeIcon icon='fal fa-user' class='text-gray-400' fixed-width aria-hidden='true'/>
                </div>
                <dd class="text-sm text-gray-500">
                    #{{ summary?.customer_client.reference ?? summary?.customer_client?.name }}
                </dd>
            </Link>

            <!-- Field: Contact name -->
            <div v-if="summary?.customer_client?.contact_name" v-tooltip="trans('Contact name')"
                 class="pl-1 flex items-center w-fit flex-none gap-x-2">
                <div class="flex-none">
                    <FontAwesomeIcon icon='fal fa-id-card-alt' class='text-gray-400' fixed-width aria-hidden='true'/>
                </div>
                <dd class="text-sm text-gray-500">{{ summary?.customer_client.contact_name }}</dd>
            </div>

            <!-- Field: Company name -->
            <div v-if="summary?.customer_client?.company_name" v-tooltip="trans('Company name')"
                 class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div class="flex-none">
                    <FontAwesomeIcon icon='fal fa-building' class='text-gray-400' fixed-width aria-hidden='true'/>
                </div>
                <dd class="text-sm text-gray-500">{{ summary?.customer_client.company_name }}</dd>
            </div>

            <!-- Field: Email -->
            <div v-if="summary?.customer_client?.email" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div v-tooltip="trans('Email')" class="flex-none">
                    <FontAwesomeIcon icon='fal fa-envelope' class='text-gray-400' fixed-width aria-hidden='true'/>
                </div>
                <a :href="`mailto:${summary?.customer_client.email}`" v-tooltip="'Click to send email'"
                   class="text-sm text-gray-500 hover:text-gray-700 truncate">{{ summary?.customer_client.email }}</a>
            </div>

            <!-- Field: Phone -->
            <div v-if="summary?.customer_client?.phone" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <div v-tooltip="trans('Phone')" class="flex-none">
                    <FontAwesomeIcon icon='fal fa-phone' class='text-gray-400' fixed-width aria-hidden='true'/>
                </div>
                <a :href="`tel:${summary?.customer_client.phone}`" v-tooltip="'Click to make a phone call'"
                   class="text-sm text-gray-500 hover:text-gray-700">{{ summary?.customer_client.phone }}</a>
            </div>

            <!-- Collection Toggle -->
            <div class="mt-2 pl-1 flex items w-full flex-none gap-x-2" v-if="!isOrder || isCollection">
                <FontAwesomeIcon icon='fal fa-map-pin' class='text-gray-400' fixed-width aria-hidden='true'/>
                <ToggleSwitch v-if="!isOrder" @change="updateCollection"
                              v-model="isCollection"/>
                <span class="text-sm text-gray-500">Collection</span>
            </div>

            <!-- Collection Options -->
            <div class="mt-2 pl-1 flex items w-full flex-none gap-x-2">
                <div v-if="isCollection && !isOrder" class="w-full">
                    <span class="block mb-1">{{ trans("Collection by:") }}</span>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" value="myself" v-model="collectionBy" @change="updateCollectionType"
                                   class="form-radio"/>
                            <span class="ml-2">{{ trans("My Self") }}</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" value="thirdParty" v-model="collectionBy" @change="updateCollectionType"
                                   class="form-radio"/>
                            <span class="ml-2">{{ trans("Third Party") }}</span>
                        </label>
                    </div>

                    <div v-if="collectionBy === 'thirdParty'" class="mt-3">
                        <textarea v-model="textValue" @blur="updateCollectionNotes" rows="5"
                                  class="w-full border border-gray-300 rounded-md p-2"
                                  placeholder="Type additional notes..."></textarea>
                    </div>
                </div>

                <div v-else-if="!isCollection" class="w-full text-xs text-gray-500"
                     :class="listError?.box_stats_delivery_address ? 'errorShake' : ''">
                    <dd
                        class="w-full text-gray-500 text-xs relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
                        <div v-html="summary?.customer?.addresses?.delivery?.formatted_address"></div>
                        <div v-if="address_management?.address_update_route" @click="isModalShippingAddress = true"
                             class="underline cursor-pointer hover:text-gray-700">
                            {{ trans("Edit") }}
                            <FontAwesomeIcon icon="fal fa-pencil" class="" fixed-width aria-hidden="true"/>
                        </div>
                    </dd>
                </div>
            </div>
        </div>

        <div class="col-span-2 mb-2 md:mb-0 pl-1.5 md:pl-3">
            <dl v-if="false" class="relative flex items-start w-full flex-none gap-x-1">
                <dt class="flex-none pt-0.5">
                    <FontAwesomeIcon icon='fal fa-dollar-sign' fixed-width aria-hidden='true' class="text-gray-500"/>
                </dt>


            </dl>

            <dl class="mt-1 flex items-center w-full flex-none gap-x-1.5">
                <dt v-tooltip="trans('Weight')" class="flex-none">
                    <FontAwesomeIcon icon='fal fa-weight' fixed-width aria-hidden='true' class="text-gray-400"/>
                </dt>

                <dd class="xtext-gray-500" v-tooltip="trans('Estimated weight of all products')">
                    {{ summary.order_properties?.weight ?? '-' }}
                </dd>
            </dl>

            <div v-if="summary?.delivery_notes?.length" class="mt-4 border rounded-lg p-4 pt-3 bg-white shadow-sm">
                <!-- Section Title -->
                <div class="flex items-center gap-2 border-b border-gray-200 pb-2 mb-3">
                    <FontAwesomeIcon :icon="faTruck" class="text-blue-500" fixed-width/>
                    <div class="text-sm font-semibold text-gray-800">
                        {{ trans('Delivery Notes') }}
                    </div>
                </div>

                <!-- Delivery Note Items -->
                <div v-for="(note, index) in summary.delivery_notes" :key="index"
                     class="mb-3 pb-3 border-b border-dashed last:border-0 last:mb-0 last:pb-0">

                    <div class="flex items-center gap-2 text-sm text-gray-700 mb-1">
                        <span>{{ note?.reference }}</span>
                        <span class="ml-auto text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">
                            <Icon :data="note?.state"/>
                        </span>
                    </div>

                    <!-- Shipments -->
                    <div v-if="note?.shipments?.length > 0" class="mt-1 text-xs text-gray-600">
                        <p class="text-gray-700 font-medium mb-1">{{ trans('Shipments') }}:</p>
                        <ul class="pl-4 space-y-1">
                            <li v-for="(shipment, i) in note.shipments" :key="i">
                                <template v-if="shipment?.formatted_tracking_urls?.length">
                                    <div v-for="trackingData in shipment.formatted_tracking_urls">

                                        {{ shipment.name }}
                                        <a :href="trackingData.url" target="_blank" rel="noopener noreferrer"
                                           class="secondaryLink" v-tooltip="trans('Click to track shipment')">
                                            {{ trackingData.tracking }}
                                        </a>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-gray-400 ">
                                        {{ shipment.name }}: {{ shipment.tracking }}
                                    </div>
                                    <a class="secondaryLink" target="_parent" v-if="shipment.shipper_url"
                                       :href="shipment.shipper_url">{{ trans('Tracking url') }} </a>

                                </template>
                            </li>

                        </ul>
                    </div>

                    <div v-else class="mt-1 text-xs italic text-gray-400">
                        {{ trans('No shipments') }}
                    </div>
                </div>
            </div>

            <div v-if="summary?.invoices?.length > 0" class="mt-4 border rounded-lg p-4 pt-3 bg-white shadow-sm">
                <!-- Section Title -->
                <div class="flex items-center gap-2 border-b border-gray-200 pb-2 mb-3">
                    <FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true"/>
                    <div class="text-sm font-semibold text-gray-800">
                        {{ trans('Invoices') }}
                    </div>
                </div>

                <!-- Delivery Note Items -->
                <div v-for="(invoice, index) in summary.invoices" :key="index"
                     class="mb-3 pb-3 border-b border-dashed last:border-0 last:mb-0 last:pb-0">

                    <div class="flex items-center gap-2 text-sm text-gray-700 mb-1">
                        <Link :href="route(invoice?.routes?.show?.name, invoice?.routes?.show.parameters)"
                              class="flex items-center gap-3 gap-x-1.5 primaryLink cursor-pointer">
                            <div class="text-gray-500 " v-tooltip="trans('Invoice')">
                                {{ invoice?.reference }}
                            </div>
                        </Link>
                        <a :href="route(invoice?.routes?.download?.name, invoice?.routes?.download?.parameters)"
                           target="_blank" class="ml-auto text-sm p-1 bg-red-100 text-red-600 rounded cursor-pointer"
                           v-tooltip="trans('Download invoice')">
                            <FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true"/>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Section: Shipment -->
            <!--  <div v-if="summary.order_properties?.shipments?.length" class="flex itemcen gap-x-1 py-0.5">
                <FontAwesomeIcon v-tooltip="trans('Shipments')" icon='fal fa-shipping-fast' class='text-gray-400 mt-1'
                    fixed-width aria-hidden='true' />
                <div class="group w-full overflow-x-auto border border-gray-200 rounded">

                    <table class="min-w-full divide-y divide-gray-200 ">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-2.5 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans("Shipper") }}
                                </th>
                                <th scope="col"
                                    class="px-2.5 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans("Tracking Number") }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(shipment, shipmentIdx) in summary.order_properties?.shipments"
                                :key="shipmentIdx">
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                    {{ shipment.name }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                    <div v-if="shipment.tracking_urls && shipment.tracking_urls.length > 0">
                                        <div v-for="(trackingItem, trackingIdx) in shipment.tracking_urls"
                                            :key="trackingIdx" class="mb-1 last:mb-0">
                                            <a :href="trackingItem.url" target="_blank"
                                                class="cursor-pointer text-blue-600 hover:text-blue-800 hover:underline"
                                                v-tooltip="trans('Click to track shipment')">
                                                {{ trackingItem.tracking }}
                                            </a>
                                        </div>
                                    </div>
                                    <div v-else-if="shipment.tracking">
                                        {{ shipment.tracking }}
                                    </div>
                                    <div v-else>
                                        {{ trans("No tracking information") }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
 -->
        </div>

        <div class="col-span-2 md:col-span-3 pt-3 md:pt-0 md:pl-3">
            <div v-if="balance"
                 class="border-b border-gray-200 pb-0.5 flex justify-between pl-1.5 pr-4 mb-1.5 xtext-amber-600">
                <div class="">{{ trans("Current balance") }}:</div>
                <div class="">
                    {{ locale.currencyFormat(summary.order_summary?.currency?.data?.code, balance ?? 0) }}
                </div>
            </div>

            <div class="border border-gray-200 p-2 rounded">
                <OrderSummary :order_summary="summary.order_summary"
                              :currency_code="summary.order_summary?.currency?.data?.code"/>
            </div>
        </div>


        <!-- Section: Delivery address -->
        <Modal v-if="address_management" :isOpen="isModalShippingAddress"
            @onClose="() => (isModalShippingAddress = false)"
            width="w-full max-w-lg"
            closeButton
        >
            <AddressEditModal :addresses="address_management.addresses"
                :address="summary?.customer?.addresses?.delivery"
                :updateRoute="address_management.address_update_route"
                @submitted="() => (isModalShippingAddress = false)"
            />
        </Modal>
    </div>
</template>
