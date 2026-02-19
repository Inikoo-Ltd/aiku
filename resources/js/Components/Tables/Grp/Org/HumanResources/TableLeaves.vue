<script setup lang="ts">
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Tag from "@/Components/Tag.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { faPlus, faEdit } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faPlus, faEdit)

const props = defineProps<{
	data: {}
	tab?: string
	organisation?: string | null
	balance?: {
		annual_days: number
		annual_used: number
		annual_remaining: number
		medical_days: number
		medical_used: number
		medical_remaining: number
		unpaid_days: number
		unpaid_used: number
		unpaid_remaining: number
	}
}>()

const locale = useLocaleStore()
const isCreateModalOpen = ref(false)
const isEditModalOpen = ref(false)
const selectedLeave = ref<any>(null)
const isSubmitting = ref(false)

const typeOptions = [
	{ value: "annual", label: trans("Annual Leave") },
	{ value: "medical", label: trans("Medical Leave") },
	{ value: "unpaid", label: trans("Unpaid Leave") },
]

const leaveForm = useForm({
	type: "annual",
	start_date: "",
	end_date: "",
	reason: "",
	attachments: [] as File[],
})

const editForm = useForm({
	attachments: [] as File[],
})

const getStatusTheme = (status: string): number => {
	switch (status) {
		case "approved":
			return 3
		case "pending":
			return 1
		case "rejected":
			return 7
		default:
			return 99
	}
}

const formatDate = (date: string) => {
	return useFormatTime(date, { localeCode: locale.language.code })
}

const submitLeave = () => {
	isSubmitting.value = true
	leaveForm
		.transform((data) => ({
			...data,
			organisation: props.organisation ?? undefined,
		}))
		.post(route("grp.clocking_employees.leaves.store"), {
			preserveScroll: true,
			forceFormData: true,
			onSuccess: () => {
				isCreateModalOpen.value = false
				leaveForm.reset()
			},
			onError: () => {
				isSubmitting.value = false
			},
			onFinish: () => {
				isSubmitting.value = false
			},
		})
}

const closeCreateModal = () => {
	isCreateModalOpen.value = false
	leaveForm.reset()
}

const openEditModal = (leave: any) => {
	selectedLeave.value = leave
	editForm.attachments = []
	isEditModalOpen.value = true
}

const closeEditModal = () => {
	isEditModalOpen.value = false
	editForm.reset()
	selectedLeave.value = null
}

const submitEdit = () => {
	if (!selectedLeave.value) return

	isSubmitting.value = true
	editForm.post(
		route("grp.clocking_employees.leaves.update", {
			leave: selectedLeave.value.id,
		}),
		{
			preserveScroll: true,
			forceFormData: true,
			onSuccess: () => {
				isEditModalOpen.value = false
				editForm.reset()
				selectedLeave.value = null
			},
			onError: (errors) => {
				console.error(errors)
				isSubmitting.value = false
			},
			onFinish: () => {
				isSubmitting.value = false
			},
		}
	)
}
</script>

<template>
	<div>
		<div class="flex justify-end">
			<Button
				@click="isCreateModalOpen = true"
				:label="trans('Request Leave')"
				icon="fal fa-plus"
				type="create" />
		</div>

		<div class="mt-4">
			<Table :resource="data" :name="tab">
				<template #cell(start_date)="{ item: leave }">
					<span class="whitespace-nowrap">
						{{ formatDate(leave.start_date) }}
					</span>
				</template>

				<template #cell(end_date)="{ item: leave }">
					<span class="whitespace-nowrap">
						{{ formatDate(leave.end_date) }}
					</span>
				</template>

				<template #cell(type_label)="{ item: leave }">
					<span class="whitespace-nowrap">
						{{ leave.type_label }}
					</span>
				</template>

				<template #cell(status_label)="{ item: leave }">
					<Tag :theme="getStatusTheme(leave.status)" size="xs" :label="leave.status">
						<template #label>
							<span class="capitalize">
								{{ leave.status }}
							</span>
						</template>
					</Tag>
				</template>

				<template #cell(reason)="{ item: leave }">
					<div class="max-w-xs">
						<span class="text-gray-600 text-sm truncate block">{{ leave.reason }}</span>
						<span
							v-if="leave.status === 'rejected' && leave.rejection_reason"
							class="text-red-600 text-xs truncate block mt-1">
							{{ leave.rejection_reason }}
						</span>
					</div>
				</template>

				<template #cell(actions)="{ item: leave }">
					<div v-if="leave.status === 'pending' && leave.type === 'medical'">
						<Button
							@click="openEditModal(leave)"
							:label="trans('Edit')"
							size="xs"
							type="transparent"
							:icon="faEdit" />
					</div>
				</template>
			</Table>
		</div>

		<Modal :isOpen="isCreateModalOpen" @onClose="closeCreateModal" width="w-full max-w-lg">
			<h2 class="text-lg font-semibold text-gray-800 mb-4">
				{{ trans("Request Leave") }}
			</h2>

			<form @submit.prevent="submitLeave" class="space-y-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Leave Type")
					}}</label>
					<select
						v-model="leaveForm.type"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
						<option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
							{{ opt.label }}
						</option>
					</select>
					<p v-if="leaveForm.errors.type" class="mt-1 text-sm text-red-600">
						{{ leaveForm.errors.type }}
					</p>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700">{{
							trans("Start Date")
						}}</label>
						<input
							v-model="leaveForm.start_date"
							type="date"
							class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
						<p v-if="leaveForm.errors.start_date" class="mt-1 text-sm text-red-600">
							{{ leaveForm.errors.start_date }}
						</p>
					</div>

					<div>
						<label class="block text-sm font-medium text-gray-700">{{
							trans("End Date")
						}}</label>
						<input
							v-model="leaveForm.end_date"
							type="date"
							class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
						<p v-if="leaveForm.errors.end_date" class="mt-1 text-sm text-red-600">
							{{ leaveForm.errors.end_date }}
						</p>
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Reason")
					}}</label>
					<textarea
						v-model="leaveForm.reason"
						rows="3"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
						:placeholder="trans('Please provide a reason for your leave request')" />
					<p v-if="leaveForm.errors.reason" class="mt-1 text-sm text-red-600">
						{{ leaveForm.errors.reason }}
					</p>
				</div>

				<div v-if="leaveForm.type === 'medical'">
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Medical Certificate")
					}}</label>
					<input
						type="file"
						@change="
							(e: Event) => {
								const files = (e.target as HTMLInputElement).files
								if (files) leaveForm.attachments = Array.from(files)
							}
						"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
						accept=".pdf,.jpg,.jpeg,.png" />
					<p class="mt-1 text-xs text-gray-500">
						{{ trans("Upload medical certificate (PDF, JPG, PNG)") }}
					</p>
				</div>

				<div class="mt-6 flex justify-end gap-2">
					<Button @click="closeCreateModal" :label="trans('Cancel')" type="tertiary" />
					<Button
						type="save"
						nativeType="submit"
						:label="trans('Submit Request')"
						:loading="isSubmitting" />
				</div>
			</form>
		</Modal>

		<Modal :isOpen="isEditModalOpen" @onClose="closeEditModal" width="w-full max-w-md">
			<h2 class="text-lg font-semibold text-gray-800 mb-4">
				{{ trans("Edit Medical Certificate") }}
			</h2>
			<p class="text-sm text-gray-600 mb-4">
				{{ trans("Update medical certificate for your leave request") }}
			</p>

			<form @submit.prevent="submitEdit" class="space-y-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Medical Certificate")
					}}</label>
					<input
						type="file"
						@change="
							(e: Event) => {
								const files = (e.target as HTMLInputElement).files
								if (files) editForm.attachments = Array.from(files)
							}
						"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
						accept=".pdf,.jpg,.jpeg,.png" />
					<p class="mt-1 text-xs text-gray-500">
						{{ trans("Upload Medical Certificate (PDF, JPG, PNG)") }}
					</p>
					<p v-if="editForm.errors.attachments" class="mt-1 text-sm text-red-600">
						{{ editForm.errors.attachments }}
					</p>
				</div>

				<div class="mt-6 flex justify-end gap-2">
					<Button @click="closeEditModal" :label="trans('Cancel')" type="tertiary" />
					<Button
						@click="submitEdit"
						type="save"
						:label="trans('Save')"
						:loading="isSubmitting" />
				</div>
			</form>
		</Modal>
	</div>
</template>
