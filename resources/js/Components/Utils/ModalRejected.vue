<script setup lang="ts">
import { ref, computed, watch } from "vue"
import Modal from "@/Components/Utils/Modal.vue"
import { router } from "@inertiajs/vue3"

const model = defineModel()

const props = defineProps<{
	customer?: any
	customerID?: number
	customerName?: string
}>()

const emit = defineEmits<{
	(e: "reject", payload: { rejected_reason: string; rejected_notes?: string }): void
}>()

const closeModal = () => {
	model.value = false
}

const selectedOption = ref<string | null>(null)
const otherText = ref<string>("")
const loading = ref(false)

watch(model, (newVal) => {
  if (newVal) {
    selectedOption.value = null
    otherText.value = ""
  }
})
const isRejectDisabled = computed(() => {
	if (loading.value) return true
	if (!selectedOption.value) return true
	if (selectedOption.value === "other" && !otherText.value.trim()) return true
	return false
})

const onReject = () => {
	
	if (!selectedOption.value) return
	const reason = selectedOption.value

	if (reason === "other" && !otherText.value.trim()) return

	const payload: { status: string; rejected_reason: string; rejected_notes?: string } = {
		status: "rejected",
		rejected_reason: reason,
	}

	if (reason === "other") {
		payload.rejected_notes = otherText.value.trim()
	}

	if (props.customerName && props.customerID) {
		router.patch(
			route("grp.models.customer.reject", { customer: props.customerID }),
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
					emit("reject", {
						rejected_reason: reason,
						rejected_notes: reason === "other" ? otherText.value.trim() : undefined,
					})
					closeModal()
				},
				onError: (errors) => {
					console.error("Error rejecting customer:", errors)
				},
			}
		)
	}
}
</script>

<template>
	<Modal :isOpen="model" @onClose="closeModal" :closeButton="true" width="w-[800px]">
		<div class="flex flex-col h-full p-8 space-y-6">
			<div class="border-b pb-4">
				<h2 class="text-3xl font-bold mb-2">Reject Customer</h2>
				<p class="text-gray-500">
					<span class="font-medium">{{ customerName }}</span>
				</p>
			</div>

			<div>
				<p class="text-lg font-semibold mb-3">Select a reason for rejection:</p>
				<div class="space-y-4">
					<label class="flex items-center space-x-3">
						<input
							type="radio"
							class="form-radio h-5 w-5 text-indigo-600"
							value="spam"
							v-model="selectedOption" />
						<span class="text-gray-700 text-base">Spam</span>
					</label>
					<label class="flex items-center space-x-3">
						<input
							type="radio"
							class="form-radio h-5 w-5 text-indigo-600"
							value="duplicated"
							v-model="selectedOption" />
						<span class="text-gray-700 text-base">Duplicated</span>
					</label>
					<label class="flex items-center space-x-3">
						<input
							type="radio"
							class="form-radio h-5 w-5 text-indigo-600"
							value="other"
							v-model="selectedOption" />
						<span class="text-gray-700 text-base">Other</span>
					</label>
				</div>
			</div>

			<div v-if="selectedOption === 'other'">
				<label for="otherReason" class="block text-sm font-medium text-gray-700 mb-2">
					Please specify your reason:
				</label>
				<textarea
					type="text"
					id="otherReason"
					v-model="otherText"
					placeholder="Enter your reason here..."
					class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:border-indigo-300" />
			</div>

			<div class="flex justify-end space-x-4 pt-4 border-t">
				<button
					@click="closeModal"
					class="px-6 py-2 bg-gray-200 text-gray-800 font-medium rounded-md hover:bg-gray-300 transition">
					Cancel
				</button>
				<button
					@click="onReject"
					:disabled="isRejectDisabled"
					class="px-6 py-2 bg-red-500 text-white font-medium rounded-md hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition">
					<span v-if="loading">Rejecting...</span>
					<span v-else>Reject</span>
				</button>
			</div>
		</div>
	</Modal>
</template>
