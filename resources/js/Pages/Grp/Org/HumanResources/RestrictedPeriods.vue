<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrash, faBan } from "@fal"

library.add(faTrash, faBan)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: any
	employees: { value: number; label: string }[]
	leaveTypes: { value: number; label: string }[]
	strictnessOptions: { value: string; label: string }[]
	targetTypeOptions: { value: string; label: string }[]
}>()

const showCreateModal = ref(false)
const isEditMode = ref(false)
const editingRestrictedPeriodId = ref<number | null>(null)
const showTargetModal = ref(false)
const editingRestrictedPeriodIdForTargets = ref<number | null>(null)

const form = useForm<{
	label: string
	start_date: string
	end_date: string
	strictness: string
	is_active: boolean
	allow_superuser_override: boolean
}>({
	label: "",
	start_date: "",
	end_date: "",
	strictness: "block",
	is_active: true,
	allow_superuser_override: true,
})

const targetForm = useForm<{
	target_type: string
	target_id: number
}>({
	target_type: "",
	target_id: 0,
})

const resetForm = () => {
	form.reset()
	form.clearErrors()
	isEditMode.value = false
	editingRestrictedPeriodId.value = null
}

const resetTargetForm = () => {
	targetForm.reset()
	targetForm.clearErrors()
	showTargetModal.value = false
	editingRestrictedPeriodIdForTargets.value = null
}

const openModal = () => {
	resetForm()
	showCreateModal.value = true
}

const openEdit = (row: any) => {
	form.reset()
	form.clearErrors()
	isEditMode.value = true
	editingRestrictedPeriodId.value = row.id ?? null

	form.label = row.label ?? ""
	form.start_date = row.start_date ?? ""
	form.end_date = row.end_date ?? ""
	form.strictness = row.strictness ?? "block"
	form.is_active = Boolean(row.is_active)
	form.allow_superuser_override = Boolean(row.allow_superuser_override)

	showCreateModal.value = true
}

const openTargetModal = (row: any) => {
	resetTargetForm()
	editingRestrictedPeriodIdForTargets.value = row.id ?? null
	showTargetModal.value = true
}

const closeModal = () => {
	showCreateModal.value = false
	resetForm()
}

const submit = () => {
	if (isEditMode.value && editingRestrictedPeriodId.value) {
		form.patch(
			route("grp.org.hr.restricted_periods.update", {
				...route().params,
				restrictedPeriod: editingRestrictedPeriodId.value,
			}),
			{
				preserveScroll: true,
				onSuccess: () => {
					showCreateModal.value = false
					resetForm()
				},
			}
		)
	} else {
		form.post(route("grp.org.hr.restricted_periods.store", route().params), {
			preserveScroll: true,
			onSuccess: () => {
				resetForm()
				showCreateModal.value = false
			},
		})
	}
}

const submitTarget = () => {
	if (editingRestrictedPeriodIdForTargets.value) {
		targetForm.post(
			route("grp.org.hr.restricted_periods.targets.store", {
				...route().params,
				restrictedPeriod: editingRestrictedPeriodIdForTargets.value,
			}),
			{
				preserveScroll: true,
				onSuccess: () => {
					resetTargetForm()
				},
			}
		)
	}
}

const deleteTarget = (restrictedPeriodId: number, targetId: number) => {
	targetForm.delete(
		route("grp.org.hr.restricted_periods.targets.delete", {
			...route().params,
			restrictedPeriod: restrictedPeriodId,
			restrictedPeriodTarget: targetId,
		}),
		{
			preserveScroll: true,
		}
	)
}

const modalTitle = computed(() =>
	isEditMode.value ? trans("Edit restricted period") : trans("Create restricted period")
)
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #button-restricted-period="{ action }">
			<Button
				:icon="action.icon"
				:label="action.label"
				:style="action.style"
				@click="openModal" />
		</template>
	</PageHeading>

	<Table :resource="data" class="mt-5">
		<template #cell(action)="{ item }">
			<div class="flex justify-end gap-2">
				<Button
					type="secondary"
					label="Edit"
					icon="fal fa-pencil"
					size="xs"
					v-tooltip="trans('Edit restricted period')"
					@click="openEdit(item)" />
				<Button
					type="secondary"
					label="Targets"
					icon="fal fa-users"
					size="xs"
					v-tooltip="trans('Manage targets')"
					@click="openTargetModal(item)" />
				<ModalConfirmationDelete
					:routeDelete="{
						name: 'grp.org.hr.restricted_periods.delete',
						parameters: {
							...route().params,
							restrictedPeriod: item.id,
						},
					}"
					:isFullLoading="false"
					:title="trans('Are you sure you want to delete this restricted period?')"
					:noLabel="trans('Delete')"
					noIcon="fal fa-trash">
					<template #default="{ changeModel }">
						<Button
							type="negative"
							label="Delete"
							:icon="faTrash"
							size="xs"
							v-tooltip="trans('Delete restricted period')"
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
			<div>
				<label class="block text-sm font-medium text-gray-700">
					{{ trans("Label") }}
				</label>
				<input
					v-model="form.label"
					type="text"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
				<div v-if="form.errors.label" class="mt-1 text-sm text-red-600">
					{{ form.errors.label }}
				</div>
			</div>

			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Start Date") }}
					</label>
					<input
						v-model="form.start_date"
						type="date"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">
						{{ form.errors.start_date }}
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("End Date") }}
					</label>
					<input
						v-model="form.end_date"
						type="date"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">
						{{ form.errors.end_date }}
					</div>
				</div>
			</div>

			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Strictness") }}
					</label>
					<select
						v-model="form.strictness"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
						<option
							v-for="option in props.strictnessOptions"
							:key="option.value"
							:value="option.value">
							{{ option.label }}
						</option>
					</select>
					<div v-if="form.errors.strictness" class="mt-1 text-sm text-red-600">
						{{ form.errors.strictness }}
					</div>
				</div>
			</div>

			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

				<div class="flex items-center mt-6">
					<input
						id="allow_superuser_override"
						v-model="form.allow_superuser_override"
						type="checkbox"
						class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
					<label for="allow_superuser_override" class="ml-2 block text-sm text-gray-700">
						{{ trans("Allow Superuser Override") }}
					</label>
				</div>
			</div>

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

	<Modal :isOpen="showTargetModal" @onClose="resetTargetForm" width="w-full max-w-2xl">
		<h2 class="text-lg font-semibold text-gray-800 mb-4">
			{{ trans("Manage Targets") }}
		</h2>

		<form class="space-y-4" @submit.prevent="submitTarget">
			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Target Type") }}
					</label>
					<select
						v-model="targetForm.target_type"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
						<option value="" disabled>
							{{ trans("Select target type") }}
						</option>
						<option
							v-for="option in props.targetTypeOptions"
							:key="option.value"
							:value="option.value">
							{{ option.label }}
						</option>
					</select>
					<div v-if="targetForm.errors.target_type" class="mt-1 text-sm text-red-600">
						{{ targetForm.errors.target_type }}
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Target") }}
					</label>
					<select
						v-if="targetForm.target_type === 'Employee'"
						v-model.number="targetForm.target_id"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
						<option value="" disabled>
							{{ trans("Select employee") }}
						</option>
						<option
							v-for="option in props.employees"
							:key="option.value"
							:value="option.value">
							{{ option.label }}
						</option>
					</select>
					<select
						v-else-if="targetForm.target_type === 'LeaveType'"
						v-model.number="targetForm.target_id"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
						<option value="">
							{{ trans("Select leave type") }}
						</option>
						<option
							v-for="option in props.leaveTypes"
							:key="option.value"
							:value="option.value">
							{{ option.label }}
						</option>
					</select>
					<input
						v-else
						v-model.number="targetForm.target_id"
						type="number"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="targetForm.errors.target_id" class="mt-1 text-sm text-red-600">
						{{ targetForm.errors.target_id }}
					</div>
				</div>
			</div>

			<div class="mt-6 flex justify-end gap-2">
				<Button type="tertiary" @click="resetTargetForm">
					{{ trans("Cancel") }}
				</Button>
				<Button type="save" :loading="targetForm.processing" @click="submitTarget">
					{{ trans("Add Target") }}
				</Button>
			</div>
		</form>

		<div v-if="editingRestrictedPeriodIdForTargets" class="mt-6 border-t pt-4">
			<h3 class="text-md font-semibold text-gray-700 mb-3">
				{{ trans("Existing Targets") }}
			</h3>
			<div class="space-y-2">
				<div
					v-for="target in data.data.find(
						(p: any) => p.id === editingRestrictedPeriodIdForTargets
					)?.targets || []"
					:key="target.id"
					class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
					<div>
						<span class="font-medium text-gray-700">{{ target.target_type }}</span>
						<span v-if="target.target_type === 'Employee'" class="ml-2 text-gray-500">
							{{
								props.employees.find((e) => e.value === target.target_id)?.label ??
								target.target_id
							}}
						</span>
						<span
							v-else-if="target.target_type === 'LeaveType'"
							class="ml-2 text-gray-500">
							{{
								props.leaveTypes.find((l) => l.value === target.target_id)?.label ??
								target.target_id
							}}
						</span>
						<span v-else class="ml-2 text-gray-500">ID: {{ target.target_id }}</span>
					</div>
					<Button
						type="negative"
						size="xs"
						icon="fal fa-trash"
						v-tooltip="trans('Remove target')"
						@click="deleteTarget(editingRestrictedPeriodIdForTargets, target.id)" />
				</div>
			</div>
		</div>
	</Modal>
</template>
