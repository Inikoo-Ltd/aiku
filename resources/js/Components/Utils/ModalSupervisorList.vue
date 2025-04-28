<script setup lang="ts">
import { ref } from "vue"
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faExclamationTriangle } from "@fal"
import { faAsterisk } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Icon } from "@/types/Utils/Icon"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "../Pure/PureInput.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"

// Assumed notify function from your notification/notification library
// import { notify } from "your-notification-lib"

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
	routeSupervisor?: routeType // Route used for fetching supervisors
	message?: { placeholder?: string }
	data?: any
	invoice?: any
}>()

const emits = defineEmits<{
	(e: "onNo"): void
	(e: "onYes"): void
}>()

// Modal state and deletion-related states
const isOpenModal = ref(false)
const isLoadingdelete = ref(false)
const showConfirmationInput = ref(false)
const messageDelete = ref("")
const confirmationRead = ref("")

// Supervisor list state
const supervisors = ref<any[]>([])
const isLoadingSupervisors = ref(false)

// Reset all modal state
const resetModal = () => {
	showConfirmationInput.value = false
	messageDelete.value = ""
	confirmationRead.value = ""
	isLoadingdelete.value = false
	supervisors.value = []
	isLoadingSupervisors.value = false
}

// Open the modal; if a routeSupervisor is provided, fetch the list of supervisors
const openModal = () => {
	resetModal()
	isOpenModal.value = true
	if (props.routeSupervisor && props.routeSupervisor.name) {
		fetchSupervisors()
	}
}

const closeModal = () => {
	resetModal()
	isOpenModal.value = false
}

// Fetch supervisor data using axios
const fetchSupervisors = async () => {
	isLoadingSupervisors.value = true
	try {
		// If your API expects "nama" instead of "name", adjust accordingly.
		const { data } = await axios.get(
			route(props.routeSupervisor.name, props.routeSupervisor.parameters)
		)
		// Assign list from data.data since your API returns it in the "data" property.
		supervisors.value = data.data
	} catch (error) {
		notify({
			title: trans("Something went wrong"),
			text: trans("Failed to load supervisors"),
			type: "error",
		})
	} finally {
		isLoadingSupervisors.value = false
	}
}

// Dummy onConfirm function – adjust if needed.
const onConfirm = () => {
	if (!confirmationRead.value) return
	showConfirmationInput.value = true
}
</script>

<template>
	<div>
		<slot
			name="default"
			:isOpenModal="isOpenModal"
			:changeModel="openModal"
			:isLoadingdelete="isLoadingdelete" />

		<TransitionRoot as="template" :show="isOpenModal">
			<Dialog class="relative z-20" @close="closeModal">
				<!-- Backdrop Transition rendered as a div -->
				<TransitionChild
					as="div"
					enter="ease-out duration-150"
					enter-from="opacity-0"
					enter-to="opacity-100"
					leave="ease-in duration-100"
					leave-from="opacity-100"
					leave-to="opacity-0">
					<div
						class="fixed inset-0 bg-gray-500/75 transition-opacity"
						@click="closeModal" />
				</TransitionChild>

				<div class="fixed inset-0 z-10 w-screen overflow-y-auto">
					<div
						class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
						<!-- Panel Transition rendered as a div -->
						<TransitionChild
							as="div"
							enter="ease-out duration-150"
							enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
							enter-to="opacity-100 translate-y-0 sm:scale-100"
							leave="ease-in duration-100"
							leave-from="opacity-100 translate-y-0 sm:scale-100"
							leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
							<DialogPanel
								class="relative transform overflow-hidden rounded-lg bg-white px-6 pt-6 pb-6 text-left shadow-2xl transition-all sm:my-10 sm:w-full sm:max-w-lg"
								@click.stop>
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
										<DialogTitle as="h3" class="text-base font-semibold mb-2">
											{{ trans("Available Supervisors") }}
										</DialogTitle>
										<div
											class="rounded-md bg-yellow-50 p-4 mb-4 flex items-start space-x-3">
											<div class="pt-0.5 text-yellow-400">
												<FontAwesomeIcon
													icon="fal fa-exclamation-triangle" />
											</div>
											<p class="text-sm text-yellow-800 font-medium">
												You do not have the necessary permissions to perform this action. Kindly contact your supervisor for authorization.
											</p>
										</div>
										<template v-if="isLoadingSupervisors">
											<p>Loading supervisors...</p>
										</template>
										<template v-else>
											<template v-if="supervisors.length > 0">
												<p>
													Please contact one of the following supervisors:
												</p>
												<ul class="list-disc pl-5 space-y-2">
													<li
														v-for="sup in supervisors"
														:key="sup.id || sup.email">
														{{ sup.name }}
													</li>
												</ul>
											</template>
											<template v-else>
												<p class="text-sm text-red-700 justify-center">
													No supervisors available
												</p>
											</template>
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
