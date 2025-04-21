<script setup lang="ts">
import { ref } from "vue"
import Modal from "@/Components/Utils/Modal.vue"
import { routeType } from "@/types/route"
import Select from "primevue/select"
import { router } from '@inertiajs/vue3'

// Mengontrol status modal
const model = defineModel()

const props = defineProps<{
	type?: string
	updateBalanceRoute?: routeType
}>()

// Data form
const amount = ref<number | null>(null)
const privateNote = ref<string>("")
const loading = ref(false)

// Data select untuk masing-masing kondisi
const increaseBalance = ref(null)
const decreaseBalance = ref(null)

const increase = ref([
	{ name: "Pay for the shipping of a return", type: "pay_return" },
	{ name: "Compensate Customer", type: "compensation" },
	{ name: "Transfer from other customer account", type: "transfer_out" },
	{ name: "Other Reason", type: "remove_funds_other" },
])

const decrease = ref([
	{ name: "Customer want money back", type: "money_back" },
	{ name: "Transfer to other customer account", type: "transfer_in" },
	{ name: "Other Reason", type: "remove_funds_other" },
])

// Function to reset form values
const resetForm = () => {
	amount.value = null
	privateNote.value = ""
	increaseBalance.value = null
	decreaseBalance.value = null
}

const closeModal = () => {
	model.value = false
	resetForm() 
}

const onSubmit = () => {
	const chosenType =
		props.type === "increase"
			? increaseBalance.value?.type
			: decreaseBalance.value?.type

	const payload = {
		amount: amount.value,
		notes: privateNote.value,
		type: chosenType,
	}

	router.patch(
		route(props.updateBalanceRoute.name, props.updateBalanceRoute.parameters),
		payload,
		{
			onStart: () => {
				loading.value = true
			},
			onFinish: () => {
				loading.value = false
			},
			preserveScroll: true,
			onSuccess: () => {
				closeModal()
			},
			onError: (errors) => {
				console.error("Error updating balance:", errors)
			},
		}
	)
}
</script>

<template>
	<Modal :isOpen="model" @onClose="closeModal" :closeButton="true" width="w-[800px]">
		<div class="p-6">
			<template v-if="props.type === 'increase'">
				<h2 class="text-3xl font-bold text-gray-800 mb-4">Increase Balance</h2>
				<p class="text-lg text-gray-600 mb-6">Enter the details to increase balance:</p>
				<div class="space-y-6">
					<Select
						v-model="increaseBalance"
						:options="increase"
						optionLabel="name"
						placeholder="Select your reason"
						class="w-full" />

					<div>
						<label for="amount" class="block text-gray-700 font-medium mb-2">
							Amount to deposit
						</label>
						<input
							type="number"
							id="amount"
							name="amount"
							placeholder="Enter amount"
							v-model.number="amount"
							class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
					</div>

					<div>
						<label for="privateNote" class="block text-gray-700 font-medium mb-2">
							Private Note
						</label>
						<textarea
							id="privateNote"
							name="privateNote"
							rows="4"
							placeholder="Add any private notes here..."
							v-model="privateNote"
							class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
					</div>
				</div>

				<div class="mt-8 flex justify-end space-x-4">
					<button
						@click="closeModal"
						class="px-6 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
						Cancel
					</button>
					<button
						type="button"
						@click="onSubmit"
						class="px-6 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition">
						Save
					</button>
				</div>
			</template>

			<template v-else-if="props.type === 'decrease'">
				<h2 class="text-3xl font-bold text-gray-800 mb-4">Decrease Balance</h2>
				<p class="text-lg text-gray-600 mb-6">Enter the details to decrease balance:</p>
				<div class="space-y-6">
					<Select
						v-model="decreaseBalance"
						:options="decrease"
						optionLabel="name"
						placeholder="Select your reason"
						class="w-full" />

					<div>
						<label for="amount" class="block text-gray-700 font-medium mb-2">
							Amount to withdraw
						</label>
						<input
							type="number"
							id="amount"
							name="amount"
							placeholder="Enter amount"
							v-model.number="amount"
							class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
					</div>

					<div>
						<label for="privateNote" class="block text-gray-700 font-medium mb-2">
							Private Note
						</label>
						<textarea
							id="privateNote"
							name="privateNote"
							rows="4"
							placeholder="Add any private notes here..."
							v-model="privateNote"
							class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
					</div>
				</div>

				<div class="mt-8 flex justify-end space-x-4">
					<button
						@click="closeModal"
						class="px-6 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
						Cancel
					</button>
					<button
						type="button"
						@click="onSubmit"
						class="px-6 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition">
						Save
					</button>
				</div>
			</template>

			<template v-else>
				<p class="text-gray-700">No valid type provided.</p>
			</template>
		</div>
	</Modal>
</template>
