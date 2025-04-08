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
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import Timeline from "primevue/timeline"

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
}>()

const emits = defineEmits<{
	(e: "onNo"): void
	(e: "onYes"): void
}>()

// Modal open state
const isOpenModal = ref(false)
// Loading state for deletion
const isLoadingdelete = ref(false)
// Confirmation step flag (false = show initial info; true = show confirmation input)
const showConfirmationInput = ref(false)
// Value for user input confirmation
const messageDelete = ref("")

// When the user confirms they've read the message,
// we transition to the confirmation input view.
const onConfirm = () => {
	showConfirmationInput.value = true
}

// When the user clicks the final delete confirmation button,
// perform the deletion action.
const onClickDelete = () => {
	if (!props.routeDelete?.name) return

	const selectedMethod = props.routeDelete?.method || "delete"

	// Build the payload regardless of the method
	const payload = {
		["delete_confirmation"]: messageDelete.value,
	}


	if (selectedMethod === "delete") {
		// For delete requests, pass the payload inside the 'data' property.
		router.delete(route(props.routeDelete.name, props.routeDelete.parameters), {
			data: payload,
			onStart: () => {
				isLoadingdelete.value = true
			},
			onSuccess: () => {
				// Close modal and reset state after deletion
				isOpenModal.value = false
				showConfirmationInput.value = false
				messageDelete.value = ""
			},
			onFinish: () => {
				if (!props.isFullLoading) {
					isLoadingdelete.value = false
				}
			},
		})
	} else {
		// For other methods, pass the payload as the second parameter.
		router[selectedMethod](
			route(props.routeDelete.name, props.routeDelete.parameters),
			payload,
			{
				onStart: () => {
					isLoadingdelete.value = true
				},
				onSuccess: () => {
					isOpenModal.value = false
					showConfirmationInput.value = false
					messageDelete.value = ""
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
		<slot
			name="default"
			:isOpenModal="isOpenModal"
			:changeModel="() => (isOpenModal = !isOpenModal)"
			:isLoadingdelete="isLoadingdelete" />

		<TransitionRoot as="template" :show="isOpenModal">
			<Dialog class="relative z-20" @close="isOpenModal = false">
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
					<div
						class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
						<TransitionChild
							as="template"
							enter="ease-out duration-150"
							enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
							enter-to="opacity-100 translate-y-0 sm:scale-100"
							leave="ease-in duration-100"
							leave-from="opacity-100 translate-y-0 sm:scale-100"
							leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
							<DialogPanel
								class="relative transform overflow-hidden rounded-lg bg-white px-6 pt-6 pb-6 text-left shadow-2xl transition-all sm:my-10 sm:w-full sm:max-w-lg">
								<!-- Close Button -->
								<div class="absolute top-0 right-0 hidden p-4 sm:block">
									<button
										type="button"
										class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
										@click="isOpenModal = false">
										<span class="sr-only">Close</span>
										<FontAwesomeIcon
											:icon="icon || 'fal fa-times'"
											fixed-width
											aria-hidden="true" />
									</button>
								</div>

								<div class="sm:flex sm:items-start">
									<div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
										<!-- Conditional Rendering: initial info or confirmation input view -->
										<template v-if="!showConfirmationInput">
											<!-- Initial Information Step -->
											<DialogTitle as="h3" class="text-base font-semibold">
												{{ title || trans("Delete") }}
											</DialogTitle>
											<div
												class="rounded-md bg-yellow-50 p-4 mb-4 flex items-start space-x-3">
												<div class="pt-0.5 text-yellow-400">
													<FontAwesomeIcon
														icon="fal fa-exclamation-triangle" />
												</div>
												<p class="text-sm text-yellow-800 font-medium">
													Unexpected bad things will happen if you don’t
													read this!
												</p>
											</div>

											<!-- Description with bullet points -->
											<div class="text-sm text-gray-700 mb-6">
												<ul class="list-disc list-inside space-y-2">
													<li>
														This will permanently delete the
														<strong>{{ data.reference }}</strong>
														 All these items will be
														<strong>permanently deleted</strong>.
													</li>
													<li>
														This will not change your billing plan. If
														you want to downgrade, you can do so in your
														Billing Settings.
													</li>
												</ul>
											</div>
											<div>
												<!-- Transition to confirmation input step -->
												<Button
													full
													type="tertiary"
													:label="
														trans(
															'I Have read and understand these effects'
														)
													"
													@click="onConfirm" />
											</div>
										</template>

										<template v-else>
											<!-- Confirmation Input Step -->
											<DialogTitle as="h3" class="text-base font-semibold">
												{{ trans("Confirm Delete Repository") }}
											</DialogTitle>
											<p class="text-sm text-gray-700 mb-4">
												Please type delevery reference
												<strong>{{data.reference}}</strong> to confirm deletion.
											</p>
											<!-- Input field for confirmation (you can also use a regular input if desired) -->
											<PureTextarea
												v-model="messageDelete"
												:placeholder="
													(props.message && props.message.placeholder) ||
													trans('Type repository name here')
												"
												class="mb-4" />
											<div class="flex justify-end space-x-3">
												<Button
													type="secondary"
													:label="trans('Cancel')"
													@click="showConfirmationInput = false" />
												<Button
													type="tertiary"
													:label="trans('Delete Repository')"
													:disabled="
														messageDelete.trim() !== data.reference
													"
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
