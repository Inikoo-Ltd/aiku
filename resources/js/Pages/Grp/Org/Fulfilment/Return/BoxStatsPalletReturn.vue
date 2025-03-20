<!--
  -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import JsBarcode from "jsbarcode"
import { computed, onMounted, ref, watch } from "vue"
import { capitalize } from "@/Composables/capitalize"
import ModalAddress from "@/Components/Utils/ModalAddress.vue"

import { PalletReturn, BoxStats } from "@/types/Pallet"
import { Link, router } from "@inertiajs/vue3"
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import { trans } from "laravel-vue-i18n"

import Modal from "@/Components/Utils/Modal.vue"
import { routeType } from "@/types/route"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { Switch, SwitchGroup, SwitchLabel } from "@headlessui/vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle, faPencil, faPenSquare } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ModalAddressCollection from "@/Components/Utils/ModalAddressCollection.vue"
import PalletEditCustomerReference from "@/Components/Pallet/PalletEditCustomerReference.vue"
import { notify } from "@kyvg/vue3-notification"
import Textarea from "primevue/textarea"
library.add(faQuestionCircle, faPencil, faPenSquare)

const props = defineProps<{
	dataPalletReturn: PalletReturn
	boxStats: BoxStats
	updateRoute: routeType
}>()

onMounted(() => {
	JsBarcode("#palletReturnBarcode", route().v().params.palletReturn, {
		lineColor: "rgb(41 37 36)",
		width: 2,
		height: 50,
		displayValue: false,
	})
})

// Method: Create new address
const isModalAddress = ref(false)
const isModalAddressCollection = ref(false)
const enabled = ref(props.dataPalletReturn.is_collection || false)
const isLoading = ref<string | boolean>(false)
const textValue = ref("")
const textUpdateTimeout = ref<number | null>(null)

// Computed property to intercept changes via v-model
const computedEnabled = computed({
	get() {
		return enabled.value
	},
	set(newValue: boolean) {
		const addressID = props.boxStats.fulfilment_customer.address?.address_customer?.value.id
		const address = props.boxStats.fulfilment_customer.address?.address_customer?.value

		if (!newValue) {
			// Prepare the address data for creating a new record.
			const filterDataAddress = { ...address }
			delete filterDataAddress.formatted_address
			delete filterDataAddress.country
			delete filterDataAddress.id // Remove id to create a new one

			router[
				props.boxStats.fulfilment_customer.address.routes_address.store.method || "post"
			](
				route(
					props.boxStats.fulfilment_customer.address.routes_address.store.name,
					props.boxStats.fulfilment_customer.address.routes_address.store.parameters
				),
				{
					delivery_address: filterDataAddress,
				},
				{
					preserveScroll: true,
					onFinish: () => {},
					onSuccess: () => {
						notify({
							title: trans("Success"),
							text: trans("Set the address to selected address."),
							type: "success",
						})
					},
					onError: () =>
						notify({
							title: trans("Something went wrong"),
							text: trans("Failed to submit the address, try again"),
							type: "error",
						}),
				}
			)
		} else {
			try {
				router.delete(
					route(props.boxStats.fulfilment_customer.address.routes_address.delete.name, {
						...props.boxStats.fulfilment_customer.address.routes_address.delete
							.parameters,
					}),
					{
						preserveScroll: true,
						onStart: () => (isLoading.value = "onDelete" + addressID),
						onFinish: () => {
							isLoading.value = false
						},
					}
				)
				notify({
					title: trans("Success"),
					text: trans("Set the address to follow collection."),
					type: "success",
				})
			} catch (error) {
				console.error("Error disabling collection:", error)
				notify({
					title: trans("Something went wrong"),
					text: trans("Failed to disable collection."),
					type: "error",
				})
			}
		}
		// Finally, update the ref value.
		enabled.value = newValue
	},
})

const computedText = computed({
	get() {
		return textValue.value
	},
	set(newValue: string) {
		textValue.value = newValue

		// Clear any existing debounce timer.
		if (textUpdateTimeout.value) {
			clearTimeout(textUpdateTimeout.value)
		}

		textUpdateTimeout.value = setTimeout(() => {
			router.patch(
				route(props.updateRoute.name, props.updateRoute.parameters),
				{ collection_notes: textValue.value },
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
		}, 500) as unknown as number // Type assertion for browser compatibility.
	},
})
</script>

<template>
	<div
		class="h-min grid sm:grid-cols-2 lg:grid-cols-4 border-t border-b border-gray-200 divide-x divide-gray-300">
		<!-- Box: Customer -->
		<BoxStatPallet class="py-1 sm:py-2 px-3">
			<!-- Field: Reference -->
			<Link
				as="a"
				v-if="boxStats?.fulfilment_customer?.customer?.reference"
				:href="
					route('grp.org.fulfilments.show.crm.customers.show', [
						route().params.organisation,
						boxStats.fulfilment_customer.fulfilment.slug,
						boxStats.fulfilment_customer.slug,
					])
				"
				class="flex items-center w-fit flex-none gap-x-2 cursor-pointer secondaryLink">
				<dt v-tooltip="trans('Customer Reference')" class="flex-none">
					<span class="sr-only">Reference</span>
					<FontAwesomeIcon
						icon="fal fa-id-card-alt"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd>{{ boxStats.fulfilment_customer.customer.reference }}</dd>
			</Link>

			<!-- Field: Contact name -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer?.contact_name"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Contact name')" class="flex-none">
					<span class="sr-only">Contact name</span>
					<FontAwesomeIcon
						icon="fal fa-user"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd>{{ boxStats.fulfilment_customer.customer.contact_name }}</dd>
			</div>

			<!-- Field: Company name -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer?.company_name"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Company name')" class="flex-none">
					<span class="sr-only">Company name</span>
					<FontAwesomeIcon
						icon="fal fa-building"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd>{{ boxStats.fulfilment_customer.customer.company_name }}</dd>
			</div>

			<!-- Field: Email -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer.email"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Email')" class="flex-none">
					<span class="sr-only">Email</span>
					<FontAwesomeIcon
						icon="fal fa-envelope"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a
					:href="`mailto:${boxStats.fulfilment_customer?.customer.email}`"
					class="hover:underline w-full pr-4 break-words leading-none">
					{{ boxStats.fulfilment_customer?.customer.email }}
				</a>
			</div>

			<!-- Field: Phone -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer.phone"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Phone')" class="flex-none">
					<span class="sr-only">Phone</span>
					<FontAwesomeIcon
						icon="fal fa-phone"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a>{{ boxStats.fulfilment_customer?.customer.phone }}</a>
			</div>

			<!-- Field: Delivery Address -->
			<div class="flex flex-col w-full gap-y-2 mb-1">
				<!-- Top Row: Icon and Switch -->
				<div class="flex items-center gap-x-2">
					<dt v-tooltip="trans('Pallet Return\'s address')" class="flex-none">
						<span class="sr-only">Delivery address</span>
						<FontAwesomeIcon
							icon="fal fa-map-marker-alt"
							size="xs"
							class="text-gray-400"
							fixed-width
							aria-hidden="true" />
					</dt>
					<SwitchGroup as="div" class="flex items-center">
						<Switch
							v-model="computedEnabled"
							:class="[computedEnabled ? 'bg-indigo-600' : 'bg-gray-200']"
							class="relative inline-flex h-6 w-11 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
							<span
								aria-hidden="true"
								:class="[computedEnabled ? 'translate-x-5' : 'translate-x-0']"
								class="pointer-events-none inline-block h-5 w-5 transform bg-white rounded-full shadow transition duration-200 ease-in-out" />
						</Switch>
						<SwitchLabel as="span" class="ml-3 text-sm font-medium text-gray-900">
							{{ trans("Collection") }}
						</SwitchLabel>
					</SwitchGroup>
				</div>

				<!-- Bottom Row: Address Display -->
				<div
					v-if="dataPalletReturn.is_collection !== true"
					class="w-full text-xs text-gray-500">
					<div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
						<span
							v-html="
								boxStats.fulfilment_customer?.address?.value?.formatted_address
							" />
						<div
							@click="() => (isModalAddressCollection = true)"
							class="whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
							<span>Edit</span>
						</div>
					</div>
				</div>

				<!-- Alternative Display for Collection -->
				<div v-else class="w-full">
					<Textarea  v-model="computedText" autoResize rows="5" class="w-full" cols="30" />
				</div>
			</div>
		</BoxStatPallet>

		<!-- Box Stats: 2 -->
		<BoxStatPallet
			class="py-1 sm:py-2 px-3"
			:label="capitalize(dataPalletReturn?.state)"
			icon="fal fa-truck-couch">
			<!-- Customer reference -->
			<div class="mb-1">
				<PalletEditCustomerReference :dataPalletDelivery="dataPalletReturn" :updateRoute />
				<!-- :disabled="dataPalletReturn?.state !== 'in_process' && dataPalletReturn?.state !== 'submit'"-->
			</div>

			<!-- Barcode -->
			<div
				class="mb-4 h-full w-full lg:max-w-72 mx-auto py-1 px-2 flex flex-col bg-gray-100 ring-1 ring-gray-300 rounded items-center">
				<svg id="palletReturnBarcode" class="w-full h-full"></svg>
				<div class="text-xs text-gray-500">
					{{ route().params.palletReturn }}
				</div>
			</div>

			<div v-if="boxStats?.delivery_state" class="border-t border-gray-300 pt-1.5">
				<div
					class="flex items-center flex-none gap-x-2 w-fit"
					:class="boxStats?.delivery_state.class"
					v-tooltip="trans('Delivery status')">
					<dt class="flex-none">
						<span class="sr-only">{{ boxStats.delivery_state.tooltip }}</span>
						<FontAwesomeIcon
							:icon="boxStats.delivery_state.icon"
							fixed-width
							aria-hidden="true" />
					</dt>
					<dd class="text-xs">{{ boxStats.delivery_state.tooltip }}</dd>
				</div>
			</div>

			<!-- Section: Pallets, Services, Physical Goods -->
			<!-- <div class="border-t border-gray-300 mt-2 pt-2 space-y-0.5">
                <div v-tooltip="trans('Count of pallets')" class="w-fit flex items-center gap-x-3">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-pallet' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                    </dt>
                    <dd class="text-gray-500 text-sm font-medium tabular-nums">{{ dataPalletReturn.number_pallets }} <span class="text-gray-400 font-normal">{{ dataPalletReturn.number_pallets > 1 ? trans('Pallets') : trans('Pallet') }}</span></dd>
                </div>
                <div v-tooltip="trans('Count of stored items')" class="w-fit flex items-center gap-x-3">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-cube' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                    </dt>
                    <dd class="text-gray-500 text-sm font-medium tabular-nums">{{ dataPalletReturn.number_stored_items }} <span class="text-gray-400 font-normal">{{ dataPalletReturn.number_pallets > 1 ? trans('Stored items') : trans('Stored item') }}</span></dd>
                </div>
                <div v-tooltip="trans('Count of services')" class="w-fit flex items-center gap-x-3">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-concierge-bell' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                    </dt>
                    <dd class="text-gray-500 text-sm font-medium tabular-nums">{{ dataPalletReturn.number_services }} <span class="text-gray-400 font-normal">{{ dataPalletReturn.number_pallets > 1 ? trans('Services') : trans('Service') }}</span></dd>
                </div>
                <div v-tooltip="trans('Count of physical goods')" class="w-fit flex items-center gap-x-3">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-cube' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                    </dt>
                    <dd class="text-gray-500 text-sm font-medium tabular-nums">{{ dataPalletReturn.number_physical_goods }} <span class="text-gray-400 font-normal">{{ dataPalletReturn.number_pallets > 1 ? trans('Physical goods') : trans('Physical good') }}</span></dd>
                </div>
            </div> -->
		</BoxStatPallet>

		<!-- Box: Order summary -->
		<BoxStatPallet
			v-if="boxStats?.order_summary"
			class="sm:col-span-2 border-t sm:border-t-0 border-gray-300">
			<section
				aria-labelledby="summary-heading"
				class="lg:max-w-xl rounded-lg px-4 py-4 sm:px-6 lg:mt-0">
				<!-- <h2 id="summary-heading" class="text-lg font-medium">Order summary</h2> -->
				<div class="text-gray-500 mb-2" v-if="boxStats?.recurring_bill">
					<Link
						:href="
							route(
								boxStats?.recurring_bill?.route?.name,
								boxStats?.recurring_bill?.route?.parameters
							)
						"
						method="get"
						v-tooltip="'Recurring Bill'"
						class="primaryLink">
						{{ boxStats?.recurring_bill?.reference }}
					</Link>
				</div>
				<OrderSummary
					:order_summary="boxStats.order_summary"
					:currency_code="boxStats.order_summary.currency.data.code" />
			</section>
		</BoxStatPallet>
	</div>

	<Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
		<ModalAddress :addresses="boxStats.fulfilment_customer.address" :updateRoute />
	</Modal>

	<Modal :isOpen="isModalAddressCollection" @onClose="() => (isModalAddressCollection = false)">
		<ModalAddressCollection
			:addresses="boxStats.fulfilment_customer.address"
			:updateRoute
			:is_collection="dataPalletReturn.is_collection" />
	</Modal>
</template>

<style scoped lang="scss">
:deep(.country) {
	@apply font-medium text-sm;
}
</style>
