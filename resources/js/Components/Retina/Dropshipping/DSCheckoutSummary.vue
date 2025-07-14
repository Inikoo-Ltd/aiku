<script setup lang="ts">

import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { AddressManagement } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faIdCardAlt, faWeight } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import NeedToPay from "@/Components/Utils/NeedToPay.vue"
import { useTruncate } from "@/Composables/useTruncate"
library.add(faIdCardAlt, faWeight)

defineProps<{
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
    }
    balance?: string
    address_management?: AddressManagement
}>()

const locale = inject('locale', {})

const isModalShippingAddress = ref(false)

</script>

<template>

    <div class="py-4 grid grid-cols-2 md:grid-cols-7 px-4 gap-x-4 divide-y divide-gray-200 md:divide-y-0 md:divide-x">
        <div class="col-span-2 mb-4 md:mb-0">
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

            <Link v-if="summary?.customer_client.ulid" as="a" v-tooltip="trans('Client')"
                :href="route('retina.dropshipping.customer_sales_channels.client.show', [summary.customer_channel?.slug, summary?.customer_client.ulid])"
                class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
                <div class="flex-none">
                    <FontAwesomeIcon icon='fal fa-user' class='text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <dd class="text-sm text-gray-500">#{{ summary?.customer_client.reference ?? summary?.customer_client?.name }}</dd>
            </Link>

            <!-- Field: Contact name -->
            <div v-if="summary?.customer_client.contact_name" v-tooltip="trans('Contact name')"
                class="pl-1 flex items-center w-fit flex-none gap-x-2">
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

        <div class="col-span-2 mb-2 md:mb-0 pl-1.5 md:pl-3">
            <dl v-if="false" class="relative flex items-start w-full flex-none gap-x-1">
                <dt class="flex-none pt-0.5">
                    <FontAwesomeIcon icon='fal fa-dollar-sign' fixed-width aria-hidden='true' class="text-gray-500" />
                </dt>

                <!-- <NeedToPay
                     @click="onPayClick"
                    :totalAmount="box_stats.products.payment.total_amount"
                    :paidAmount="box_stats.products.payment.paid_amount"
                    :payAmount="box_stats.products.payment.pay_amount"
                    :class="[box_stats.products.payment.pay_amount ? 'hover:bg-gray-100 cursor-pointer' : '']"
                    :currencyCode="currency.code" /> -->
            </dl>

            <div class="mt-1 flex items-center w-full flex-none gap-x-1.5">
                <dt v-tooltip="trans('Weight')" class="flex-none">
                    <FontAwesomeIcon icon='fal fa-weight' fixed-width aria-hidden='true' class="text-gray-400" />
                </dt>
                
                <dd class="xtext-gray-500" v-tooltip="trans('Estimated weight of all products (in kilograms)')">
                    {{ summary.order_properties?.weight }}
                </dd>

            </div>

            <!-- Section: Shipment -->
            <div v-if="summary.order_properties?.shipments?.length" class="flex itemcen gap-x-1 py-0.5">
				<FontAwesomeIcon v-tooltip="trans('Shipments')" icon='fal fa-shipping-fast' class='text-gray-400 mt-1' fixed-width aria-hidden='true' />
				<div class="group w-full">
					<div class="leading-4 text-base flex justify-between w-full py-1">
						<div>{{ trans("Shipments") }} ({{ summary.order_properties?.shipments?.length ?? 0 }})</div>
					</div>
					
					<ul v-if="summary.order_properties?.shipments" class="list-disc pl-4">
						<li v-for="(sments, shipmentIdx) in summary.order_properties?.shipments" :key="shipmentIdx" class="xhover:bg-gray-100 hover:underline text-sm tabular-nums">
							<div class="flex justify-between">
								<a v-if="sments.combined_label_url" v-tooltip="trans('Click to open file')" target="_blank" :href="sments.combined_label_url" class="">
									{{ sments.name }}
									<FontAwesomeIcon icon="fal fa-external-link" class="" fixed-width aria-hidden="true" />
								</a>
								
								<div v-else-if="sments.label && sments.label_type === 'pdf'" v-tooltip="trans('Click to download file')" @click="base64ToPdf(sments.label)" class="group cursor-pointer">
									<span class="truncate">
										{{ sments.name }}
									</span>
									<span v-if="sments.tracking" class="text-gray-400">
										({{ useTruncate(sments.tracking, 14) }})
									</span>
									<FontAwesomeIcon icon="fal fa-external-link" class="text-gray-400 group-hover:text-gray-700" fixed-width aria-hidden="true" />
								</div>
								
								<div v-else>
									<span class="truncate">
										{{ sments.name }}
									</span>
									<span v-if="sments.tracking" class="text-gray-400">
										({{ useTruncate(sments.tracking, 14) }})
									</span>
								</div>
							</div>
							
							<!-- <Button
								v-if="sments.is_printable"
								@click="() => onPrintShipment(sments)"
								size="xs"
								icon="fal fa-print"
								label="Print label"
								type="tertiary"
								:loading="isLoadingPrint"
							/> -->
						</li>
					</ul>

				</div>
			</div>


            <!-- <div v-if="delivery_note" class="mt-1 flex items-center w-full flex-none justify-between">
                <Link
                    :href="route(routes.delivery_note.deliveryNoteRoute.name, routes.delivery_note.deliveryNoteRoute.parameters)"
                    class="flex items-center gap-3 gap-x-1.5 primaryLink cursor-pointer">
                <dt class="flex-none">
                    <FontAwesomeIcon icon='fal fa-truck' fixed-width aria-hidden='true' class="text-gray-500" />
                </dt>
                <dd class="text-gray-500 " v-tooltip="trans('Delivery Note')">
                    {{ delivery_note?.reference }}
                </dd>
                </Link>
                <a :href="route(routes.delivery_note.deliveryNotePdfRoute.name, routes.delivery_note.deliveryNotePdfRoute.parameters)"
                    as="a" target="_blank" class="flex items-center">
                    <button class="flex items-center">
                        <div class="flex-none">
                            <FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true"
                                class="text-gray-500 hover:text-indigo-500 transition-colors duration-200" />
                        </div>
                    </button>
                </a>
            </div> -->
        </div>

        <div class="col-span-2 md:col-span-3 pt-3 md:pt-0 md:pl-3">
            <div v-if="balance" class="border-b border-gray-200 pb-0.5 flex justify-between pl-1.5 pr-4 mb-1.5 xtext-amber-600">
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
