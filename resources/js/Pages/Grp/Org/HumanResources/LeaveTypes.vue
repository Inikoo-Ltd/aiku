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
import { faTrash, faChartNetwork, faCalendarMinus, faLayerGroup } from "@fal"

library.add(faTrash, faChartNetwork, faCalendarMinus, faLayerGroup)
const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: any
	categoryOptions: { value: string; label: string }[]
}>()

const showCreateModal = ref(false)
const isEditMode = ref(false)
const editingLeaveTypeId = ref<number | null>(null)

const form = useForm<{
	code: string
	name: string
	color: string | null
	description: string | null
	category: string
	requires_approval: boolean
	max_days_per_year: number | null
	value: number
	is_active: boolean
}>({
	code: "",
	name: "",
	color: null,
	description: null,
	category: "",
	requires_approval: true,
	max_days_per_year: null,
	value: 1,
	is_active: true,
})

const resetForm = () => {
	form.reset()
	form.clearErrors()
	isEditMode.value = false
	editingLeaveTypeId.value = null
}

const openModal = () => {
	resetForm()
	showCreateModal.value = true
}

const openEdit = (row: any) => {
	form.reset()
	form.clearErrors()
	isEditMode.value = true
	editingLeaveTypeId.value = row.id ?? null

	form.code = row.code ?? ""
	form.name = row.name ?? ""
	form.color = row.color ?? null
	form.description = row.description ?? null
	form.category = row.category ?? ""
	form.requires_approval = Boolean(row.requires_approval)
	form.max_days_per_year = row.max_days_per_year ?? null
	form.value =
		typeof row.value === "number"
			? row.value
			: typeof row.settings?.value === "number"
				? row.settings.value
				: 1
	form.is_active = Boolean(row.is_active)

	showCreateModal.value = true
}

const closeModal = () => {
	showCreateModal.value = false
	resetForm()
}

const submit = () => {
	if (isEditMode.value && editingLeaveTypeId.value) {
		form.patch(
			route("grp.org.hr.leaves.types.update", {
				...route().params,
				leaveType: editingLeaveTypeId.value,
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
		form.post(route("grp.org.hr.leaves.types.store", route().params), {
			preserveScroll: true,
			onSuccess: () => {
				resetForm()
				showCreateModal.value = false
			},
		})
	}
}

const modalTitle = computed(() =>
	isEditMode.value ? trans("Edit leave type") : trans("Create leave type")
)
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #button-leave-type="{ action }">
			<Button
				:icon="action.icon"
				:label="action.label"
				:style="action.style"
				@click="openModal" />
		</template>
	</PageHeading>

	<Table :resource="data" class="mt-5">
		<template #cell(color)="{ item }">
			<div
				v-if="item.color"
				class="w-6 h-6 rounded-full border border-gray-200 shadow-sm"
				:style="{ backgroundColor: item.color }"></div>
			<span v-else class="text-gray-400 text-sm italic">{{ trans("No color") }}</span>
		</template>

		<template #cell(action)="{ item }">
			<div class="flex justify-end gap-2">
				<Button
					type="secondary"
					label="Edit"
					icon="fal fa-pencil"
					size="xs"
					v-tooltip="trans('Edit leave type')"
					@click="openEdit(item)" />
				<ModalConfirmationDelete
					:routeDelete="{
						name: 'grp.org.hr.leaves.types.delete',
						parameters: {
							...route().params,
							leaveType: item.id,
						},
					}"
					:isFullLoading="false"
					:title="trans('Are you sure you want to delete this leave type?')"
					:noLabel="trans('Delete')"
					noIcon="fal fa-trash">
					<template #default="{ changeModel }">
						<Button
							type="negative"
							label="Delete"
							:icon="faTrash"
							size="xs"
							v-tooltip="trans('Delete leave type')"
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
			<!-- Code + Name -->
			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Code") }}
					</label>
					<input
						v-model="form.code"
						type="text"
						:disabled="isEditMode"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100" />
					<div v-if="form.errors.code" class="mt-1 text-sm text-red-600">
						{{ form.errors.code }}
					</div>
				</div>

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
						{{ trans("Color") }}
					</label>
					<input
						v-model="form.color"
						type="color"
						class="mt-1 block w-full h-9 rounded-md border border-gray-300 p-1 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="form.errors.color" class="mt-1 text-sm text-red-600">
						{{ form.errors.color }}
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

			<!-- Category + Max Days -->
			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Category") }}
					</label>
					<select
						v-model="form.category"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
						<option value="">
							{{ trans("Select category") }}
						</option>
						<option
							v-for="option in props.categoryOptions"
							:key="option.value"
							:value="option.value">
							{{ option.label }}
						</option>
					</select>
					<div v-if="form.errors.category" class="mt-1 text-sm text-red-600">
						{{ form.errors.category }}
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Maximum Days") }}
					</label>
					<input
						v-model.number="form.max_days_per_year"
						type="number"
						step="0.5"
						min="0"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="form.errors.max_days_per_year" class="mt-1 text-sm text-red-600">
						{{ form.errors.max_days_per_year }}
					</div>
				</div>
			</div>

			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Value") }}
					</label>
					<input
						v-model.number="form.value"
						type="number"
						step="0.01"
						min="0.01"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="form.errors.value" class="mt-1 text-sm text-red-600">
						{{ form.errors.value }}
					</div>
				</div>
			</div>

			<!-- Requires Approval + Active -->
			<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div class="flex items-center mt-6">
					<input
						id="requires_approval"
						v-model="form.requires_approval"
						type="checkbox"
						class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
					<label for="requires_approval" class="ml-2 block text-sm text-gray-700">
						{{ trans("Requires Approval") }}
					</label>
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
