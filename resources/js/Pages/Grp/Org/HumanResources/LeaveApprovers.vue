<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrash, faUserShield, faPencil, faPlus } from "@fal"

library.add(faTrash, faUserShield, faPencil, faPlus)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: any
	employeeOptions: {
		value: number
		label: string
		email?: string | null
		employee_id: number
		organisation_id: number
	}[]
	organisationOptions: {
		value: number
		label: string
	}[]
	organisationId: number
}>()

const showCreateModal = ref(false)

const form = useForm<{
	user_id: number | null
	organisation_id: number | null
	organisation_ids: number[]
	sequence_number: number
	description: string | null
	is_active: boolean
}>({
	user_id: null,
	organisation_id: null,
	organisation_ids: [],
	sequence_number: 1,
	description: null,
	is_active: true,
})

const resetForm = () => {
	form.reset()
	form.clearErrors()
	form.organisation_id = props.organisationId
	form.organisation_ids = [props.organisationId]
}

const openModal = () => {
	resetForm()
	showCreateModal.value = true
}

const closeModal = () => {
	showCreateModal.value = false
	resetForm()
}

const filteredEmployeeOptions = computed(() => {
	if (!form.organisation_ids.length) {
		return props.employeeOptions
	}

	return props.employeeOptions.filter((employee) =>
		form.organisation_ids.includes(employee.organisation_id)
	)
})

const isAllAcceptedSelected = computed(() => form.sequence_number === 0)

const levelOptions = [
	{ value: 0, label: trans("All Accepted"), hint: trans("Final approval immediately") },
	{ value: 1, label: trans("Level 1"), hint: trans("First approval step") },
	{ value: 2, label: trans("Level 2"), hint: trans("Second approval step") },
	{ value: 3, label: trans("Level 3"), hint: trans("Third approval step") },
	{ value: 4, label: trans("Level 4"), hint: trans("Fourth approval step") },
	{ value: 5, label: trans("Level 5"), hint: trans("Fifth approval step") },
]

const submit = () => {
	form.post(route("grp.org.hr.leave_approvers.store", route().params), {
		preserveScroll: true,
		onSuccess: () => {
			resetForm()
			showCreateModal.value = false
		},
	})
}

const modalTitle = computed(() => trans("Create Leave Approver"))
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #button-leave-approver="{ action }">
			<Button
				:icon="action.icon"
				:label="action.label"
				:style="action.style"
				@click="openModal" />
		</template>
	</PageHeading>

	<Table :resource="data" class="mt-5">
		<template #cell(organisation_names)="{ item }">
			<div class="max-w-xl">
				<p
					class="text-gray-600 text-sm leading-5 line-clamp-2"
					v-tooltip="item.organisation_names">
					{{ item.organisation_names || "-" }}
				</p>
				<p v-if="item.organisation_ids?.length" class="mt-1 text-xs text-gray-400">
					{{ trans(":count organisations", { count: item.organisation_ids.length }) }}
				</p>
			</div>
		</template>

		<template #cell(user_email)="{ item }">
			<span class="text-gray-600">{{ item.user_email || "-" }}</span>
		</template>

		<template #cell(sequence_number)="{ item }">
			<span
				class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
				:class="{
					'bg-amber-100 text-amber-800': item.sequence_number === 0,
					'bg-indigo-100 text-indigo-800': item.sequence_number === 1,
					'bg-blue-100 text-blue-800': item.sequence_number === 2,
					'bg-green-100 text-green-800': item.sequence_number === 3,
					'bg-purple-100 text-purple-800': item.sequence_number > 3,
				}">
				{{
					item.sequence_number === 0
						? trans("All Accepted")
						: trans("Level :level", { level: item.sequence_number })
				}}
			</span>
		</template>

		<template #cell(is_active)="{ item }">
			<span
				class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
				:class="
					item.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
				">
				{{ item.is_active ? trans("Active") : trans("Inactive") }}
			</span>
		</template>

		<template #cell(action)="{ item }">
			<div class="flex justify-end gap-2">
				<ModalConfirmationDelete
					:routeDelete="{
						name: 'grp.org.hr.leave_approvers.delete',
						parameters: {
							...route().params,
							leaveApprover: item.id,
							leave_approver_ids: item.leave_approver_ids,
						},
					}"
					:isFullLoading="false"
					:title="trans('Are you sure you want to delete this leave approver entry?')"
					:noLabel="trans('Delete')"
					noIcon="fal fa-trash">
					<template #default="{ changeModel }">
						<Button
							type="negative"
							label="Delete"
							:icon="faTrash"
							size="xs"
							v-tooltip="trans('Delete leave approver')"
							@click="changeModel()" />
					</template>
				</ModalConfirmationDelete>
			</div>
		</template>
	</Table>

	<Modal :isOpen="showCreateModal" @onClose="closeModal" width="w-full max-w-2xl">
		<h2 class="text-lg font-semibold text-gray-800 mb-4">
			{{ modalTitle }}
		</h2>

		<form class="space-y-4" @submit.prevent="submit">
			<!-- Organisation -->
			<div>
				<label class="block text-sm font-medium text-gray-700">
					{{ trans("Organisations") }}
				</label>
				<PureMultiselect
					v-model="form.organisation_ids"
					:options="organisationOptions"
					mode="multiple"
					placeholder="Select organisations..."
					:valueProp="'value'"
					:label="'label'"
					:required="true"
					:searchable="true"
					@update:modelValue="
						() => {
							form.errors.organisation_ids = null
							form.organisation_id = form.organisation_ids[0] ?? null
							if (
								form.user_id &&
								!filteredEmployeeOptions.some(
									(employee) => employee.value === form.user_id
								)
							) {
								form.user_id = null
							}
						}
					">
					<template #option="{ option }">
						<div class="truncate">
							{{ option.label }}
						</div>
					</template>
				</PureMultiselect>
				<div v-if="form.errors.organisation_ids" class="mt-1 text-sm text-red-600">
					{{ form.errors.organisation_ids }}
				</div>
			</div>

			<!-- User + Level -->
			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Employee") }}
					</label>
					<PureMultiselect
						v-model="form.user_id"
						:options="filteredEmployeeOptions"
						placeholder="Select employee..."
						:valueProp="'value'"
						:label="'label'"
						:required="true"
						:searchable="true"
						@update:modelValue="() => (form.errors.user_id = null)">
						<template #label="{ value }">
							<div class="w-full text-left pl-4 truncate">
								{{ value.label }}
								<span v-if="value.email" class="text-sm text-gray-400">
									({{ value.email }})
								</span>
							</div>
						</template>

						<template #option="{ option }">
							<div class="truncate">
								{{ option.label }}
								<span v-if="option.email" class="text-sm text-gray-400">
									({{ option.email }})
								</span>
							</div>
						</template>
					</PureMultiselect>
					<div v-if="form.errors.user_id" class="mt-1 text-sm text-red-600">
						{{ form.errors.user_id }}
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Level") }}
					</label>
					<select
						v-model.number="form.sequence_number"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
						<option
							v-for="level in levelOptions"
							:key="level.value"
							:value="level.value">
							{{ level.label }}
						</option>
					</select>
					<div
						v-if="isAllAcceptedSelected"
						class="mt-2 rounded-lg border border-amber-200 bg-gradient-to-r from-amber-50 to-white px-3 py-2 text-sm text-amber-900">
						<div class="font-medium">{{ trans("All Accepted role enabled") }}</div>
						<div class="mt-1 text-amber-800">
							{{
								trans(
									"This user can approve or reject the leave immediately without waiting for Level 1 to Level 5."
								)
							}}
						</div>
					</div>
					<div v-else class="mt-2 text-xs text-gray-500">
						{{
							levelOptions.find((level) => level.value === form.sequence_number)?.hint
						}}
					</div>
					<div v-if="form.errors.sequence_number" class="mt-1 text-sm text-red-600">
						{{ form.errors.sequence_number }}
					</div>
				</div>
			</div>

			<!-- Description -->
			<div>
				<label class="block text-sm font-medium text-gray-700">
					{{ trans("Description") }}
				</label>
				<textarea
					v-model="form.description"
					rows="3"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
				<div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
					{{ form.errors.description }}
				</div>
			</div>

			<!-- Active -->
			<div class="flex items-center mt-6">
				<input
					id="is_active"
					v-model="form.is_active"
					type="checkbox"
					class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
				<label for="is_active" class="ml-2 block text-sm text-gray-700">
					{{ trans("Active") }}
				</label>
			</div>

			<!-- Buttons -->
			<div class="mt-6 flex justify-end gap-2">
				<Button type="tertiary" @click="closeModal">
					{{ trans("Cancel") }}
				</Button>
				<Button type="save" :loading="form.processing" @click="submit">
					{{ trans("Save") }}
				</Button>
			</div>
		</form>
	</Modal>
</template>
