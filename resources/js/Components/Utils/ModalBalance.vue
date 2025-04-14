<script setup lang="ts">
import { ref } from "vue"
import Modal from "@/Components/Utils/Modal.vue"

const model = ref(true) // Or use your defineModel() if available

const props = defineProps<{
	type?: string
}>()

const emit = defineEmits<{
	(e: "reject", payload: { reason: string; amount?: number; note?: string }): void
}>()

// Form data
const reason = ref<string>("")
const amount = ref<number | null>(null)
const privateNote = ref<string>("")

const closeModal = () => {
	model.value = false
}

const saveForm = () => {
	// You can add validation here as needed
	emit("reject", {
		reason: reason.value,
		amount: amount.value || 0,
		note: privateNote.value,
	})
	closeModal()
}
</script>

<template>
	<Modal :isOpen="model" @onClose="closeModal" :closeButton="true" width="w-[800px]">
		<div >
			<template v-if="props.type === 'increase'">
				<h2 class="text-3xl font-bold text-gray-800 mb-2">Increase Balance</h2>
				<p class="text-lg text-gray-600 mb-6">Enter the details to increase balance:</p>

				<!-- Two-column layout: Left for input form, Right for additional form fields -->
				<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
					<!-- Left Column: Input Form for Reason -->
					<div class="space-y-4">
                        <div>
							<label for="reason" class="block text-gray-700 font-medium mb-2">
								Pay for the shipping of a return
							</label>
							<input
								type="text"
								id="reason"
								name="reason"
								placeholder="Enter reason"
								v-model="reason"
								class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
						</div>
                        <div>
							<label for="reason" class="block text-gray-700 font-medium mb-2">
								Compensate Customer 
							</label>
							<input
								type="text"
								id="reason"
								name="reason"
								placeholder="Enter reason"
								v-model="reason"
								class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
						</div>
                        <div>
							<label for="reason" class="block text-gray-700 font-medium mb-2">
								Transfer from other customer account
							</label>
							<input
								type="text"
								id="reason"
								name="reason"
								placeholder="Enter reason"
								v-model="reason"
								class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
						</div>
						<div>
							<label for="reason" class="block text-gray-700 font-medium mb-2">
								Other Reason
							</label>
							<input
								type="text"
								id="reason"
								name="reason"
								placeholder="Enter reason"
								v-model="reason"
								class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
						</div>
						<!-- You can add more left-side fields here if needed -->
					</div>

					<!-- Right Column: Additional Fields -->
					<div class="space-y-4">
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
								class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
						</div>
						<div>
							<label for="privateNote" class="block text-gray-700 font-medium mb-2">
								Private Note
							</label>
							<textarea
								id="privateNote"
								name="privateNote"
								rows="3"
								placeholder="Add any private notes here..."
								v-model="privateNote"
								class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
						</div>
					</div>
				</div>

				<!-- Action Buttons -->
				<div class="mt-6 flex justify-end space-x-3">
					<button
						@click="closeModal"
						class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
						Cancel
					</button>
					<button
						type="button"
						@click="saveForm"
						class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition">
						Save
					</button>
				</div>
			</template>

			<template v-else-if="props.type === 'decrease'">
				<h2 class="text-3xl font-bold text-gray-800 mb-2">Decrease Balance</h2>
				<!-- Your 'decrease' form goes here -->
			</template>

			<template v-else>
				<!-- Default content if needed -->
				<p class="text-gray-700">No valid type provided.</p>
			</template>
		</div>
	</Modal>
</template>
