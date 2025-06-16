<script setup lang="ts">
import { ref } from "vue"
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue"
import { Link, router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faExclamationTriangle } from "@fal"
import { faAsterisk } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Icon } from "@/types/Utils/Icon"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "../Pure/PureInput.vue"
import { notify } from "@kyvg/vue3-notification"

library.add(faTimes, faExclamationTriangle, faAsterisk)

const props = defineProps<{
	description?: string
	icon?: Icon
	yesLabel?: string
	noLabel?: string
	routeDelete?: routeType
	isFullLoading?: boolean
	isWithMessage?: boolean
	keyMessage?: string
	whyLabel?: string
	title?: string
	message?: {
		placeholder?: string
	}
	data?: any
	invoice?: any
}>()

const emits = defineEmits<{
	(e: "onNo"): void
	(e: "onYes"): void
}>()

const isOpenModal = ref(false)
const isLoadingdelete = ref(false)
const showConfirmationInput = ref(false)
const messageDelete = ref("")
const confirmationRead = ref("")

const resetModal = () => {
	showConfirmationInput.value = false
	messageDelete.value = ""
	confirmationRead.value = ""
	isLoadingdelete.value = false
}

const openModal = () => {
	resetModal()
	isOpenModal.value = true
}

const onConfirm = () => {
	if (!confirmationRead.value) {
		return
	}
	showConfirmationInput.value = true
}

const closeModal = () => {
  resetModal();
  isOpenModal.value = false;
};

const onClickDelete = () => {
	if (!props.routeDelete?.name) return

	const selectedMethod = props.routeDelete?.method || "delete"
	// Build the payload regardless of the method
	const payload = {
	/* 	["delete_confirmation"]: messageDelete.value, */
		["deleted_note"]: confirmationRead.value,
	}
	if (selectedMethod === "delete") {
		router.delete(route(props.routeDelete.name, props.routeDelete.parameters), {
			data: payload,
			onStart: () => {
				isLoadingdelete.value = true
			},
			onSuccess: () => {
				
				// Close modal and reset state after deletion
				resetModal()
				isOpenModal.value = false
			},
			onFinish: () => {
				if (!props.isFullLoading) {
					isLoadingdelete.value = false
				}
			},
			onError: (error) => {
				notify({
					title: trans("Something went wrong"),
					text: error.recurring_bill_state,
					type: "error"
				})
			},
		})
	} else {
		router[selectedMethod](
			route(props.routeDelete.name, props.routeDelete.parameters),
			payload,
			{
				onStart: () => {
					isLoadingdelete.value = true
				},
				onSuccess: () => {
					resetModal()
					isOpenModal.value = false
				},
				onFinish: () => {
					if (!props.isFullLoading) {
						isLoadingdelete.value = false
					}
				},
			}
		)
	}
}
</script>

<template>
	<div>
		<!-- Pass the openModal function via the default slot -->
		<slot
			name="default"
			:isOpenModal="isOpenModal"
			:changeModel="openModal"
			:isLoadingdelete="isLoadingdelete" />

		<TransitionRoot as="template" :show="isOpenModal">
			<Dialog class="relative z-20" @click="closeModal">
				<TransitionChild
					as="template"
					enter="ease-out duration-150"
					enter-from="opacity-0"
					enter-to="opacity-100"
					leave="ease-in duration-100"
					leave-from="opacity-100"
					leave-to="opacity-0">
					<div class="fixed inset-0 bg-gray-500/75 transition-opacity" />
				</TransitionChild>
				<div class="fixed inset-0 z-10 w-screen overflow-y-auto">
					<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
						<TransitionChild
							as="template"
							enter="ease-out duration-150"
							enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
							enter-to="opacity-100 translate-y-0 sm:scale-100"
							leave="ease-in duration-100"
							leave-from="opacity-100 translate-y-0 sm:scale-100"
							leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
							<DialogPanel class="relative transform overflow-hidden rounded-lg bg-white px-6 pt-6 pb-6 text-left shadow-2xl transition-all sm:my-10 sm:w-full sm:max-w-lg">
								<!-- Close Button -->
								<div class="absolute top-0 right-0 hidden p-4 sm:block">
									<button
										type="button"
										class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
										@click="closeModal">
										<span class="sr-only">Close</span>
										<FontAwesomeIcon
											:icon="icon || 'fal fa-times'"
											fixed-width
											aria-hidden="true" />
									</button>
								</div>

								<div class="sm:flex sm:items-start">
									<div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
										<!-- Conditional rendering: initial info or confirmation input view -->
										<template v-if="!showConfirmationInput">
											<!-- Initial Information Step -->
											<DialogTitle as="h3" class="text-base font-semibold mb-2">
												{{ trans("Delete") }} {{ data?.reference || invoice?.reference }}
											</DialogTitle>
											<div class="rounded-md bg-yellow-50 p-4 mb-4 flex items-start space-x-3">
												<div class="pt-0.5 text-yellow-400">
													<FontAwesomeIcon icon="fal fa-exclamation-triangle" />
												</div>
												<p class="text-sm text-yellow-800 font-medium">
													Unexpected bad things will happen if you don’t read this!
												</p>
											</div>

											<!-- Description with bullet points -->
											<div class="text-sm text-gray-700 mb-6">
												<ul class="list-disc list-inside space-y-2">
													<li>
														This will permanently delete the <strong>{{ data?.reference || invoice?.reference }}</strong>
													</li>
													<li>
														All these items (pallets and customer SKUs) <strong>stored in the warehouse</strong> will be
														<strong>permanently deleted</strong>.
													</li>
												</ul>
											</div>
											
											<!-- New Text Area for deletion reason -->
											<div class="mb-4">
												<textarea
													v-model="confirmationRead"
													placeholder="Please provide the reason why you want to delete this item..."
													class="w-full p-2 border border-gray-300 rounded"
													rows="3"></textarea>
											</div>

											<div>
												<!-- Button disabled until the user types in the text area -->
												<Button
													full
													type="negative"
													:label="trans('I Have read and understand these effects')"
													:disabled="!confirmationRead"
													@click="onConfirm" />
											</div>
										</template>

										<template v-else>
											<!-- Confirmation Input Step -->
											<DialogTitle as="h3" class="text-base font-semibold">
												{{ trans("Confirm Delete") }}
											</DialogTitle>
											<p class="text-sm text-gray-700 mb-4">
												Please type delivery reference <strong>{{ data?.reference || invoice?.reference }}</strong> to confirm deletion.
											</p>
											<!-- Input field for the expected delivery reference -->
											<PureInput
												v-model="messageDelete"
												:placeholder="(props.message && props.message.placeholder) || trans('Type confirmation here...')"
												class="mb-4" />
											<div class="flex justify-end space-x-3">
												<Button
													type="secondary"
													:label="trans('Cancel')"
													@click="closeModal" />
												<Button
													 type="delete"
													:label="trans('Delete')"
													:disabled="messageDelete !== (data?.reference || invoice?.reference)"
													@click="onClickDelete" />
											</div>
										</template>
									</div>
								</div>
							</DialogPanel>
						</TransitionChild>
					</div>
				</div>
			</Dialog>
		</TransitionRoot>
	</div>
</template>
