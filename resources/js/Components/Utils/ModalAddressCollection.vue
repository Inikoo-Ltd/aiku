<script setup lang="ts">
import PureAddress from "@/Components/Pure/PureAddress.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { onMounted, ref, watch } from "vue"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import { Address, AddressManagement } from "@/types/PureComponent/Address"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch } from "@fal"
import { faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { useTruncate } from "@/Composables/useTruncate"
library.add(faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle)

import { Switch, SwitchGroup, SwitchLabel } from "@headlessui/vue"

const props = defineProps<{
	updateRoute: routeType
	addresses: AddressManagement
	keyPayloadEdit?: string
	is_collection?: boolean
}>()

// const emits = defineEmits<{
//     (e: 'setModal', value: boolean): void
// }>()

const homeAddress =
	props.addresses?.value
		? props.addresses?.value
		: props.addresses?.address_customer?.value
const enabled = ref(props.is_collection || false)

// Method: Create new address
const isSubmitAddressLoading = ref(false)
const onSubmitNewAddress = async (address: Address) => {
	// console.log(props.addresses.value)
	const filterDataAdddress = { ...address }
	delete filterDataAdddress.formatted_address
	delete filterDataAdddress.country
	delete filterDataAdddress.id // Remove id cuz create new one

	router[props.addresses.routes_list.store_route.method || "post"](
		route(
			props.addresses.routes_list.store_route.name,
			props.addresses.routes_list.store_route.parameters
		),
		{
			delivery_address: filterDataAdddress,
		},
		{
			preserveScroll: true,
			onStart: () => (isSubmitAddressLoading.value = true),
			onFinish: () => {
				;(isSubmitAddressLoading.value = false), (isCreateNewAddress.value = false)
				// isModalAddress.value = false
				// emits('setModal', false)
			},
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text: trans("Successfully create new address."),
					type: "success",
				})
			},
			onError: () =>
				notify({
					title: trans("Failed"),
					text: trans("Failed to submit the address, try again"),
					type: "error",
				}),
		}
	)
}

// Method: Edit address history
const isEditAddress = ref(false)
const selectedAddress = ref<Address | { country_id: null }>({
	country_id: null,
})
const onEditAddress = (address: Address) => {
	console.log(address, "sdres")

	isEditAddress.value = true
	selectedAddress.value = { ...address }
}

onMounted(() => {
	if (homeAddress) {
		onEditAddress(homeAddress)
	}
})

const onSubmitEditAddress = (address: Address) => {
	// console.log(props.addresses.value)
	const filterDataAdddress = { ...address }
	delete filterDataAdddress.formatted_address
	delete filterDataAdddress.country
	delete filterDataAdddress.country_code

	router.patch(
		route(props.addresses.routes_address.update.name, props.addresses.routes_address.update.parameters),
		{
			[props.keyPayloadEdit || "address"]: filterDataAdddress,
		},
		{
			preserveScroll: true,
			onStart: () => (isSubmitAddressLoading.value = true),
			onFinish: () => {
				isSubmitAddressLoading.value = false
				isCreateNewAddress.value = false
				// isModalAddress.value = false
			},
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text: trans("Successfully update the address."),
					type: "success",
				})
			},
			onError: () =>
				notify({
					title: trans("Failed"),
					text: trans("Failed to update the address, try again."),
					type: "error",
				}),
		}
	)
}

// Method: Select address history
const isCreateNewAddress = ref(false)
const isSelectAddressLoading = ref<number | boolean | null | undefined>(false)
const onSelectAddress = (selectedAddress: Address) => {
	router.patch(
		route(props.updateRoute.name, props.updateRoute.parameters),
		{
			delivery_address_id: selectedAddress.id,
		},
		{
			onStart: () => (isSelectAddressLoading.value = selectedAddress.id),
			onFinish: () => (isSelectAddressLoading.value = false),
		}
	)
	// props.addresses.value = selectedAddress
}

const isLoading = ref<string | boolean>(false)
// Method: Pinned address

// Method: Delete address
const onDeleteAddress = (addressID: number) => {
	// console.log('vvcxvcxvcx', props.addressesList.delete_route.method, route(props.addressesList.delete_route.name, props.addressesList.delete_route.parameters))
	router.delete(
		route(props.addresses.routes_list.delete_route.name, {
			...props.addresses.routes_list.delete_route.parameters,
			address: addressID,
		}),
		{
			preserveScroll: true,
			onStart: () => (isLoading.value = "onDelete" + addressID),
			onFinish: () => {
				isLoading.value = false
			},
			onError: () =>
				notify({
					title: trans("Failed"),
					text: trans("Failed to delete the address, try again"),
					type: "error",
				}),
		}
	)
}

watch(enabled, async (newValue) => {
	const addressID = props.addresses?.address_customer?.value.id
	const address = props.addresses?.address_customer?.value
	if (!newValue) {
		const filterDataAdddress = { ...address }
		delete filterDataAdddress.formatted_address
		delete filterDataAdddress.country
		delete filterDataAdddress.id // Remove id cuz create new one

		router[props.addresses.routes_address.store.method || "post"](
			route(
				props.addresses.routes_address.store.name,
				props.addresses.routes_address.store.parameters
			),
			{
				delivery_address: filterDataAdddress,
			},
			{
				preserveScroll: true,
				onStart: () => (isSubmitAddressLoading.value = true),
				onFinish: () => {
					;(isSubmitAddressLoading.value = false), (isCreateNewAddress.value = false)
					// isModalAddress.value = false
					// emits('setModal', false)
				},
				onSuccess: () => {
					notify({
						title: trans("Success"),
						text: trans("Successfully create new address."),
						type: "success",
					})
				},
				onError: () =>
					notify({
						title: trans("Failed"),
						text: trans("Failed to submit the address, try again"),
						type: "error",
					}),
			}
		)
	} else {
		try {
			await router.delete(
				route(props.addresses.routes_address.delete.name, {
					...props.addresses.routes_address.delete.parameters,
				}),
				{
					preserveScroll: true, // Ensures the UI remains stable during the operation
					onStart: () => (isLoading.value = "onDelete" + addressID),
					onFinish: () => {
						isLoading.value = false
					},
				}
			)
			notify({
				title: trans("Success"),
				text: trans("Collection disabled successfully."),
				type: "success",
			})
		} catch (error) {
			console.error("Error disabling collection:", error) // Debugging output
			notify({
				title: trans("Failed"),
				text: trans("Failed to disable collection."),
				type: "error",
			})
		}
	}
})
</script>

<template>
	<div class="h-[600px] px-2 py-1 overflow-auto">
		<!-- <pre>current selected {{ addresses.current_selected_address_id }}</pre>
    <pre>pinned address {{ addresses.pinned_address_id }}</pre>
    <pre>home {{ addresses.home_address_id }}</pre> -->
		<div class="flex justify-between border-b border-gray-300">
			<div class="text-2xl font-bold text-center mb-2 flex gap-x-2">
				{{ trans("Delivery Address ") }}
			</div>
		</div>

		<div class="relative transition-all"></div>
		<div class="flex items-center justify-between space-x-4 p-4 rounded-lg shadow-sm">
			<SwitchGroup as="div" class="flex items-center">
				<Switch
					v-model="enabled"
					:class="[enabled ? 'bg-indigo-600' : 'bg-gray-200']"
					class="relative inline-flex h-6 w-11 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
					<span
						aria-hidden="true"
						:class="[enabled ? 'translate-x-5' : 'translate-x-0']"
						class="pointer-events-none inline-block h-5 w-5 transform bg-white rounded-full shadow transition duration-200 ease-in-out" />
				</Switch>
				<SwitchLabel as="span" class="ml-3 text-sm font-medium text-gray-900">
					{{ trans("Collection") }}
				</SwitchLabel>
			</SwitchGroup>
		</div>

		<div
			v-if="!enabled"
			:key="'edit' + selectedAddress?.id"
			class="col-span-2 relative py-4 h-fit grid grid-cols-2 gap-x-4">
			<div
				class="overflow-hidden relative text-xs rounded-lg h-fit transition-all"
				:class="[
					selectedAddress?.id
						? 'border border-gray-300 ring-2 ring-offset-4 ring-indigo-500'
						: 'ring-1 ring-gray-300',
				]">
				<div v-html="selectedAddress?.formatted_address" class="px-3 py-2"></div>
			</div>

			<!-- Form: Edit address -->
			<div class="relative bg-gray-100 p-4 rounded-md">
				<PureAddress v-model="selectedAddress" :options="addresses.options" />
				<div class="mt-6 flex justify-center">
					<Button
						@click="() => onSubmitEditAddress(selectedAddress)"
						label="Edit address"
						:loading="isSubmitAddressLoading"
						full />
				</div>
			</div>
		</div>
		<div v-else></div>
	</div>
</template>

<style scoped></style>
