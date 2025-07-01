<script setup lang="ts">
import { inject, ref, computed } from "vue"
import axios from "axios"
import { router, useForm } from "@inertiajs/vue3" // Inertia router
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEdit, faTrash, faSave, faSignOutAlt } from "@fortawesome/free-solid-svg-icons"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faPlus } from "@far"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import ConfirmPopup from "primevue/confirmpopup"
import { useConfirm } from "primevue/useconfirm"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { faExclamationTriangle } from "@fal"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import Dialog from "primevue/dialog" // Import the Dialog component

// Add icons to the library
library.add(faEdit, faTrash, faPlus, faSave, faSignOutAlt, faExclamationTriangle)

const props = withDefaults(defineProps<{ widget: any[] }>(), { widget: () => [] })

// Create reactive copy for deletion handling
const widgetItems = ref([...props.widget])

// Inject global locale and layout stores
const locale = inject("locale", aikuLocaleStructure)
const layoutStore = inject("layout", layoutStructure)

const confirm = useConfirm()

// Track edit mode, modal visibility, and unsaved changes
const isEditing = ref(false)
const showModal = ref(false)
const hasChanges = ref(false)

// Reactive arrays for new inputs (restricted to one each)
const newUserInputs = ref<{ query: string; suggestions: string[] }[]>([])
const newExternalEmailInputs = ref<string[]>([])
const dataUserList = ref([])
const formAddUser = useForm({ user_id: [] })
const routeIndexUser = {
	name: "grp.json.outbox.users.index",
	parameters: { outbox: route().params["outbox"] },
}

const hasSubscriptions = computed(() => {
	return (
		widgetItems.value.length > 0 ||
		newUserInputs.value.length > 0 ||
		newExternalEmailInputs.value.length > 0
	)
})

// Functions to show and hide the modal
const toggleEdit = () => {
	isEditing.value = true
	showModal.value = true
}
const exitEdit = () => {
	isEditing.value = false
	showModal.value = false
}

const addUser = () => {
	// Only allow adding a user input if no input of either type exists
	if (newUserInputs.value.length === 0 && newExternalEmailInputs.value.length === 0) {
		newUserInputs.value.push({ query: "", suggestions: [] })
		hasChanges.value = true
	}
}
const addExternalEmail = () => {
	// Only allow adding an external email input if no input of either type exists
	if (newExternalEmailInputs.value.length === 0 && newUserInputs.value.length === 0) {
		newExternalEmailInputs.value.push("")
		hasChanges.value = true
	}
}

const deleteWidgetItem = (item: any, index: number) => {
	const subscriber_id = item.subscriber_id
	console.log(item, index)
	const routeToDelete = {
		name: "grp.models.outboxes.subscriber.delete",
		parameters: [route().params["outbox"], subscriber_id],
	}
	router.delete(route(routeToDelete.name, routeToDelete.parameters), {
		preserveScroll: true,
		onSuccess: () => {
			notify({
				title: trans("Success"),
				text: trans("Successful Delete"),
				type: "success",
			})
			// Remove the deleted item from the reactive copy
			widgetItems.value.splice(index, 1)
			hasChanges.value = true
		},
		onError: (error: any) => {
			console.error("Error deleting subscriber", error)
		},
	})
}

const deleteUserInput = (index: number) => {
	newUserInputs.value.splice(index, 1)
	hasChanges.value = true
}
const deleteExternalEmailInput = (index: number) => {
	newExternalEmailInputs.value.splice(index, 1)
	hasChanges.value = true
}

const saveChanges = () => {
	const payload: any = {}
	if (newExternalEmailInputs.value.length > 0) {
		payload.external_emails = newExternalEmailInputs.value
	}
	if (newUserInputs.value.length > 0) {
		payload.users_id = Array.isArray(formAddUser.user_id)
			? formAddUser.user_id
			: [formAddUser.user_id]
	}
	const routeToSubmit = {
		name: "grp.models.outboxes.subscriber.store",
		parameters: [route().params["outbox"]],
	}
    console.log("test->",route(routeToSubmit.name, routeToSubmit.parameters))
	console.log(payload, "payload to submit")
	router.post(route(routeToSubmit.name, routeToSubmit.parameters), payload, {
		preserveScroll: true,
		onSuccess: (page) => {

			widgetItems.value = page.props.showcase.outbox_subscribe.data
			
			notify({
				title: trans("Success"),
				text: trans("Successfully attach"),
				type: "success",
			})
			newUserInputs.value = []
			newExternalEmailInputs.value = []
			hasChanges.value = false
			exitEdit()
		},
		onError: (errors: any) => {
			notify({
				title: trans("Something went wrong."),
				text: trans(errors["external_emails.0"] || errors.users_id),
				type: "error",
			})
		},
		onFinish: () => {},
	})
}

const confirmDeleteWidgetItem = (event: Event, item: any, index: number) => {
	confirm.require({
		target: event.currentTarget,
		appendTo: "body",
		message: "Are you sure you want to delete this record?",
		icon: "pi pi-exclamation-triangle",
		rejectProps: {
			label: "No",
			severity: "secondary",
			outlined: true,
		},
		acceptProps: {
			label: "Yes",
			severity: "danger",
		},
		accept: () => {
			deleteWidgetItem(item, index)
		},
		reject: () => {
			// Optionally handle rejection
		},
	})
}
</script>

<template>
	<!-- Ensure the ConfirmPopup is rendered -->
	<ConfirmPopup />

	<!-- Display card in non-edit mode -->
	<dl class="mb-2 grid grid-cols-1 md:grid-cols-2 gap-3">
		<div class="rounded-lg bg-white shadow border border-gray-200">
			<!-- Card Header -->
			<div class="px-4 py-5 flex items-center justify-between">
				<dt class="text-lg font-semibold text-gray-500 capitalize">Subscriber</dt>
				<FontAwesomeIcon
					:icon="faEdit"
					class="text-blue-500 cursor-pointer"
					@click="toggleEdit" />
			</div>
			<div class="pl-4 pr-4 pb-5">
				<!-- Iterate over the reactive copy -->
				<div
					v-for="(item, index) in widgetItems"
					:key="item.id"
					class="flex items-center justify-between border-b border-gray-100 py-1">
					<span class="text-gray-600">
						{{ item.contact_name }}
						<i>
							<template v-if="item.email"> ({{ item.email }}) </template>
							<template v-else>
								(<FontAwesomeIcon
									:icon="faExclamationTriangle"
									class="text-red-500 mr-1" />
								no email set)
							</template>
						</i>
					</span>
				</div>
				<div v-if="!hasSubscriptions" class="mt-2">
					<p class="text-gray-600 italic">not subscribe set</p>
				</div>
			</div>
		</div>
	</dl>

	<!-- Edit Modal -->
	<Dialog
		v-model:visible="showModal"
		header="Edit Subscriber"
		modal
		draggable
		closable
		@hide="exitEdit">
		<div class="pl-4 pr-4 pb-5">
			<!-- List current subscribers with delete option -->
			<div
				v-for="(item, index) in widgetItems"
				:key="item.id"
				class="flex items-center justify-between border-b border-gray-100 py-1">
				<span class="text-gray-600">
					{{ item.contact_name }}
					<i>
						<template v-if="item.email"> ({{ item.email }}) </template>
						<template v-else>
							(<FontAwesomeIcon
								:icon="faExclamationTriangle"
								class="text-red-500 mr-1" />
							no email set)
						</template>
					</i>
					<span
						class="inline-block p-2"
						@click="confirmDeleteWidgetItem($event, item, index)">
						<FontAwesomeIcon :icon="faTrash" class="text-red-500 cursor-pointer" />
					</span>
				</span>
			</div>

			<!-- New user input (only one allowed at a time) -->
			<div v-if="newUserInputs.length" class="mt-2 flex items-center">
				<div
					v-for="(input, index) in newUserInputs"
					:key="'user-' + index"
					class="flex-1 border-b py-2">
					<PureMultiselectInfiniteScroll
						v-model="formAddUser.user_id"
						:fetchRoute="routeIndexUser"
						:placeholder="trans('Select User')"
						valueProp="id"
						@optionsList="(options) => (dataUserList = options)">
						<template #singlelabel="{ value }">
							<div class="w-full text-left pl-4">
								{{ value.username }}
								<span class="text-sm text-gray-400">
									<template v-if="value.email">
										{{ value.email }}
									</template>
									<template v-else>
										<FontAwesomeIcon
											:icon="faExclamationTriangle"
											class="text-red-500 mr-1" />
										no email set
									</template>
								</span>
							</div>
						</template>
						<template #option="{ option }">
							<div>
								{{ option.username }}
								<span class="text-sm text-gray-400"
									>| {{ option.contact_name }}</span
								>
							</div>
						</template>
					</PureMultiselectInfiniteScroll>
				</div>
				<!-- Delete icon beside the user input -->
				<FontAwesomeIcon
					:icon="faTrash"
					class="text-red-500 cursor-pointer ml-2"
					@click="deleteUserInput(0)" />
			</div>

			<!-- New external email input (only one allowed at a time) -->
			<div v-if="newExternalEmailInputs.length" class="mt-2 flex items-center">
				<div
					v-for="(input, index) in newExternalEmailInputs"
					:key="'external-' + index"
					class="flex-1">
					<input
						type="text"
						v-model="newExternalEmailInputs[index]"
						@input="hasChanges.value = true"
						placeholder="Enter External Email"
						class="w-full border border-gray-300 rounded p-2" />
				</div>
				<!-- Delete icon beside the external email input -->
				<FontAwesomeIcon
					:icon="faTrash"
					class="text-red-500 cursor-pointer ml-2"
					@click="deleteExternalEmailInput(0)" />
			</div>

			<!-- Action buttons for adding inputs -->
			<div class="mt-2 flex items-center space-x-4">
				<!-- Disable Add User if either input exists -->
				<Button
					label="Add User"
					type="secondary"
					size="s"
					@click="addUser"
					:disabled="newUserInputs.length > 0 || newExternalEmailInputs.length > 0"
					iconRight="far fa-plus" />
				<!-- Disable Add External Email if either input exists -->
				<Button
					label="Add External Email"
					type="secondary"
					size="s"
					@click="addExternalEmail"
					:disabled="newExternalEmailInputs.length > 0 || newUserInputs.length > 0"
					iconRight="far fa-plus" />
				<!-- Save button -->
				<Button
					label="Save"
					type="primary"
					size="s"
					@click="saveChanges"
					:disabled="!hasChanges"
					iconRight="faSave" />
			</div>
		</div>
	</Dialog>
</template>
