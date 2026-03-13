<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import { computed, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Select from "@/Components/Forms/Fields/Select.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrash, faProjectDiagram } from "@fal"
import { notify } from "@kyvg/vue3-notification"

library.add(faTrash, faProjectDiagram)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: any
	employees: { value: number; label: string }[]
	jobPositions: { value: number; label: string }[]
	jobPositionEmployees: Record<number, string[]>
	ruleTypeOptions: { value: string; label: string }[]
	targetTypeOptions: { value: string; label: string }[]
	roleOptions: { value: string; label: string }[]
}>()

const showCreateModal = ref(false)
const isEditMode = ref(false)
const editingLeaveConcurrencyRuleId = ref<number | null>(null)
const showTargetModal = ref(false)
const editingLeaveConcurrencyRuleIdForTargets = ref<number | null>(null)

const form = useForm<{
	name: string
	rule_type: string
	limit: number | null
	max_overlap_days: number | null
	is_active: boolean
}>({
	name: "",
	rule_type: "quota",
	limit: 1,
	max_overlap_days: 0,
	is_active: true,
})

const targetForm = useForm<{
	target_type: string
	target_id: number | null
	role: string | null
}>({
	target_type: "Employee",
	target_id: null,
	role: null,
})

const resetForm = () => {
	form.reset()
	form.clearErrors()
	isEditMode.value = false
	editingLeaveConcurrencyRuleId.value = null
}

const resetTargetForm = () => {
	targetForm.reset()
	targetForm.clearErrors()
	showTargetModal.value = false
	editingLeaveConcurrencyRuleIdForTargets.value = null
}

const resetTargetFields = () => {
	targetForm.reset()
	targetForm.clearErrors()
}

const openModal = () => {
	resetForm()
	showCreateModal.value = true
}

const openEdit = (row: any) => {
	form.reset()
	form.clearErrors()
	isEditMode.value = true
	editingLeaveConcurrencyRuleId.value = row.id ?? null

	form.name = row.name ?? ""
	form.rule_type = row.rule_type ?? "quota"
	form.limit = row.limit ?? 1
	form.max_overlap_days = row.max_overlap_days ?? 0
	form.is_active = Boolean(row.is_active)

	showCreateModal.value = true
}

const openTargetModal = (row: any) => {
	resetTargetFields()
	editingLeaveConcurrencyRuleIdForTargets.value = row.id ?? null
	showTargetModal.value = true
}

watch(
	() => targetForm.target_type,
	() => {
		targetForm.target_id = null
		targetForm.clearErrors("target_id")
	}
)

const closeModal = () => {
	showCreateModal.value = false
	resetForm()
}

const submit = () => {
	if (isEditMode.value && editingLeaveConcurrencyRuleId.value) {
		form.patch(
			route("grp.org.hr.leave_concurrency_rules.update", {
				...route().params,
				leaveConcurrencyRule: editingLeaveConcurrencyRuleId.value,
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
		form.post(route("grp.org.hr.leave_concurrency_rules.store", route().params), {
			preserveScroll: true,
			onSuccess: () => {
				resetForm()
				showCreateModal.value = false
			},
		})
	}
}

const submitTarget = () => {
	if (editingLeaveConcurrencyRuleIdForTargets.value) {
		targetForm.post(
			route("grp.org.hr.leave_concurrency_rules.targets.store", {
				...route().params,
				leaveConcurrencyRule: editingLeaveConcurrencyRuleIdForTargets.value,
			}),
			{
				preserveScroll: true,
				onSuccess: () => {
					resetTargetFields()
				},
				onError: (errors) => {
					const firstError = Object.values(errors)[0]
					const message = Array.isArray(firstError)
						? firstError[0]
						: firstError ?? trans("Target not found.")

					notify({
						title: trans("Failed"),
						text: String(message),
						type: "error",
					})
				},
			}
		)
	}
}

const deleteTarget = (leaveConcurrencyRuleId: number, targetId: number) => {
	targetForm.delete(
		route("grp.org.hr.leave_concurrency_rules.targets.delete", {
			...route().params,
			leaveConcurrencyRule: leaveConcurrencyRuleId,
			leaveConcurrencyTarget: targetId,
		}),
		{
			preserveScroll: true,
		}
	)
}

const modalTitle = computed(() =>
	isEditMode.value ? trans("Edit leave concurrency rule") : trans("Create leave concurrency rule")
)
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #button-leave-concurrency-rule="{ action }">
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
					v-tooltip="trans('Edit leave concurrency rule')"
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
						name: 'grp.org.hr.leave_concurrency_rules.delete',
						parameters: {
							...route().params,
							leaveConcurrencyRule: item.id,
						},
					}"
					:isFullLoading="false"
					:title="trans('Are you sure you want to delete this leave concurrency rule?')"
					:noLabel="trans('Delete')"
					noIcon="fal fa-trash">
					<template #default="{ changeModel }">
						<Button
							type="negative"
							label="Delete"
							:icon="faTrash"
							size="xs"
							v-tooltip="trans('Delete leave concurrency rule')"
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
					{{ trans("Name") }}
				</label>
				<input
					v-model="form.name"
					type="text"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
				<div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
					{{ form.errors.name }}
				</div>
			</div>

			<div>
				<label class="block text-sm font-medium text-gray-700">
					{{ trans("Rule Type") }}
				</label>
				<select
					v-model="form.rule_type"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
					<option
						v-for="option in props.ruleTypeOptions"
						:key="option.value"
						:value="option.value">
						{{ option.label }}
					</option>
				</select>
				<div v-if="form.errors.rule_type" class="mt-1 text-sm text-red-600">
					{{ form.errors.rule_type }}
				</div>
			</div>

			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4" v-if="form.rule_type === 'quota'">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Limit") }}
					</label>
					<input
						v-model.number="form.limit"
						type="number"
						min="1"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="form.errors.limit" class="mt-1 text-sm text-red-600">
						{{ form.errors.limit }}
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Max Overlap Days") }}
					</label>
					<input
						v-model.number="form.max_overlap_days"
						type="number"
						min="0"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="form.errors.max_overlap_days" class="mt-1 text-sm text-red-600">
						{{ form.errors.max_overlap_days }}
					</div>
				</div>
			</div>

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
					<Select
						v-if="targetForm.target_type === 'Employee'"
						:form="targetForm"
						fieldName="target_id"
						:options="props.employees"
						:fieldData="{
							placeholder: trans('Select employee'),
							searchable: true
						}" />
					<Select
						v-else-if="targetForm.target_type === 'JobPosition'"
						:form="targetForm"
						fieldName="target_id"
						:options="props.jobPositions"
						:fieldData="{
							placeholder: trans('Select job position'),
							searchable: true
						}" />
				</div>
			</div>

			<div
				v-if="
					data.data.find((r: any) => r.id === editingLeaveConcurrencyRuleIdForTargets)
						?.rule_type === 'dependency'
				">
				<label class="block text-sm font-medium text-gray-700">
					{{ trans("Role") }}
				</label>
				<select
					v-model="targetForm.role"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
					<option value="">
						{{ trans("Select role") }}
					</option>
					<option
						v-for="option in props.roleOptions"
						:key="option.value"
						:value="option.value">
						{{ option.label }}
					</option>
				</select>
				<div v-if="targetForm.errors.role" class="mt-1 text-sm text-red-600">
					{{ targetForm.errors.role }}
				</div>
			</div>

			<div class="mt-6 flex justify-end gap-2">
				<Button type="tertiary" @click="resetTargetForm">
					{{ trans("Finish") }}
				</Button>
				<Button type="save" :loading="targetForm.processing" @click="submitTarget">
					{{ trans("Add Target") }}
				</Button>
			</div>
		</form>

		<div v-if="editingLeaveConcurrencyRuleIdForTargets" class="mt-6 border-t pt-4">
			<h3 class="text-md font-semibold text-gray-700 mb-3">
				{{ trans("Existing Targets") }}
			</h3>
			<div class="space-y-2">
				<div
					v-for="target in data.data.find(
						(r: any) => r.id === editingLeaveConcurrencyRuleIdForTargets
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
						<span v-else-if="target.target_type === 'JobPosition'" class="ml-2 text-gray-500">
							{{
								props.jobPositions.find((j) => j.value === target.target_id)?.label ??
								target.target_id
							}}
							<span
								v-if="props.jobPositionEmployees[target.target_id]"
								class="text-gray-400">
								({{ props.jobPositionEmployees[target.target_id].join(", ") }})
							</span>
						</span>
						<span v-else class="ml-2 text-gray-500">ID: {{ target.target_id }}</span>
						<span
							v-if="target.role"
							class="ml-2 px-2 py-1 bg-indigo-100 text-indigo-800 rounded-full text-xs">
							{{ target.role }}
						</span>
					</div>
					<Button
						type="negative"
						size="xs"
						icon="fal fa-trash"
						v-tooltip="trans('Remove target')"
						@click="deleteTarget(editingLeaveConcurrencyRuleIdForTargets, target.id)" />
				</div>
			</div>
		</div>
	</Modal>
</template>
