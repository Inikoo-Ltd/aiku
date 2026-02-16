<script setup lang="ts">
import { ref, computed } from "vue"
import { Link, router, useForm } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { faPlus, faPaperclip, faCheckCircle, faTimesCircle, faClock, faPencilAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faPlus, faPaperclip, faCheckCircle, faTimesCircle, faClock, faPencilAlt)

const props = defineProps<{
	data: {}
	tab?: string
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

const typeOptions = [
	{ value: "annual", label: trans("Annual Leave") },
	{ value: "medical", label: trans("Medical Leave") },
	{ value: "unpaid", label: trans("Unpaid Leave") },
]

const statusColors: Record<string, string> = {
	pending: "bg-yellow-100 text-yellow-800 border-yellow-200",
	approved: "bg-green-100 text-green-800 border-green-200",
	rejected: "bg-red-100 text-red-800 border-red-200",
}

const typeColors: Record<string, string> = {
	annual: "bg-blue-100 text-blue-800",
	medical: "bg-red-100 text-red-800",
	unpaid: "bg-gray-100 text-gray-800",
}

const statusIcons: Record<string, string> = {
	pending: "fal fa-clock",
	approved: "fal fa-check-circle",
	rejected: "fal fa-times-circle",
}

const submitLeave = () => {
	isSubmitting.value = true
	leaveForm.post(route("grp.clocking_employees.leaves.store"), {
		preserveScroll: true,
		onSuccess: () => {
			isCreateModalOpen.value = false
			leaveForm.reset()
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
			onSuccess: () => {
				isEditModalOpen.value = false
				editForm.reset()
				selectedLeave.value = null
			},
			onFinish: () => {
				isSubmitting.value = false
			},
		}
	)
}
</script>

<template>
	<div class="px-4 py-4 space-y-4">
		<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
			<div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-gray-500">{{ trans("Annual Leave") }}</p>
						<p class="text-2xl font-bold text-blue-600">
							{{ balance?.annual_remaining ?? 0 }}
							<span class="text-sm font-normal text-gray-400"
								>/ {{ balance?.annual_days ?? 0 }}</span
							>
						</p>
					</div>
					<div class="p-2 bg-blue-100 rounded-full">
						<FontAwesomeIcon
							icon="fal fa-calendar"
							class="text-blue-500 text-xl"
							fixed-width />
					</div>
				</div>
				<p class="text-xs text-gray-400 mt-2">
					{{ balance?.annual_used ?? 0 }} {{ trans("days used") }}
				</p>
			</div>

			<div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-gray-500">{{ trans("Medical Leave") }}</p>
						<p class="text-2xl font-bold text-red-600">
							{{ balance?.medical_remaining ?? 0 }}
							<span class="text-sm font-normal text-gray-400"
								>/ {{ balance?.medical_days ?? 0 }}</span
							>
						</p>
					</div>
					<div class="p-2 bg-red-100 rounded-full">
						<FontAwesomeIcon
							icon="fal fa-medkit"
							class="text-red-500 text-xl"
							fixed-width />
					</div>
				</div>
				<p class="text-xs text-gray-400 mt-2">
					{{ balance?.medical_used ?? 0 }} {{ trans("days used") }}
				</p>
			</div>

			<div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-gray-500">{{ trans("Unpaid Leave") }}</p>
						<p class="text-2xl font-bold text-gray-600">
							{{ trans("Unlimited") }}
						</p>
					</div>
					<div class="p-2 bg-gray-100 rounded-full">
						<FontAwesomeIcon
							icon="fal fa-calendar-times"
							class="text-gray-500 text-xl"
							fixed-width />
					</div>
				</div>
				<p class="text-xs text-gray-400 mt-2">
					{{ balance?.unpaid_used ?? 0 }} {{ trans("days used") }}
				</p>
			</div>
		</div>

		<div class="flex justify-end">
			<Button
				@click="isCreateModalOpen = true"
				:label="trans('Request Leave')"
				icon="fal fa-plus"
				type="create" />
		</div>

		<Table :resource="data" :name="tab">
			<template #cell(start_date)="{ item: leave }">
				<span class="text-gray-900">{{
					useFormatTime(leave.start_date, { localeCode: locale.language.code })
				}}</span>
			</template>

			<template #cell(end_date)="{ item: leave }">
				<span class="text-gray-900">{{
					useFormatTime(leave.end_date, { localeCode: locale.language.code })
				}}</span>
			</template>

			<template #cell(type_label)="{ item: leave }">
				<span
					class="px-2 py-1 rounded-full text-xs font-medium"
					:class="typeColors[leave.type]">
					{{ leave.type_label }}
				</span>
			</template>

			<template #cell(status_label)="{ item: leave }">
				<span
					class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border"
					:class="statusColors[leave.status]">
					<FontAwesomeIcon :icon="statusIcons[leave.status]" fixed-width />
					{{ leave.status_label }}
				</span>
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
						type="secondary"
						icon="fal fa-pencil-alt" />
				</div>
			</template>
		</Table>

		<Modal :isOpen="isCreateModalOpen" @onClose="closeCreateModal" width="w-full max-w-lg">
			<div class="p-6">
				<h3 class="text-lg font-semibold text-gray-900 mb-4">
					{{ trans("Request Leave") }}
				</h3>

				<form @submit.prevent="submitLeave" class="space-y-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Leave Type")
						}}</label>
						<select
							v-model="leaveForm.type"
							class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
							<option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
								{{ opt.label }}
							</option>
						</select>
						<p v-if="leaveForm.errors.type" class="text-sm text-red-500 mt-1">
							{{ leaveForm.errors.type }}
						</p>
					</div>

					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1">{{
								trans("Start Date")
							}}</label>
							<input
								v-model="leaveForm.start_date"
								type="date"
								class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
							<p v-if="leaveForm.errors.start_date" class="text-sm text-red-500 mt-1">
								{{ leaveForm.errors.start_date }}
							</p>
						</div>

						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1">{{
								trans("End Date")
							}}</label>
							<input
								v-model="leaveForm.end_date"
								type="date"
								class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
							<p v-if="leaveForm.errors.end_date" class="text-sm text-red-500 mt-1">
								{{ leaveForm.errors.end_date }}
							</p>
						</div>
					</div>

					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Reason")
						}}</label>
						<textarea
							v-model="leaveForm.reason"
							rows="3"
							class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
							:placeholder="
								trans('Please provide a reason for your leave request')
							" />
						<p v-if="leaveForm.errors.reason" class="text-sm text-red-500 mt-1">
							{{ leaveForm.errors.reason }}
						</p>
					</div>

					<div v-if="leaveForm.type === 'medical'">
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
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
							class="w-full border border-gray-300 rounded-lg px-3 py-2"
							accept=".pdf,.jpg,.jpeg,.png" />
						<p class="text-xs text-gray-500 mt-1">
							{{ trans("Upload medical certificate (PDF, JPG, PNG)") }}
						</p>
					</div>

					<div class="flex justify-end gap-3 pt-4">
						<Button
							@click="closeCreateModal"
							:label="trans('Cancel')"
							type="tertiary" />
						<Button
							type="submit"
							:label="trans('Submit Request')"
							:loading="isSubmitting" />
					</div>
				</form>
			</div>
		</Modal>

		<Modal :isOpen="isEditModalOpen" @onClose="closeEditModal" width="w-full max-w-md">
			<div class="p-6">
				<h3 class="text-lg font-semibold text-gray-900 mb-4">
					{{ trans("Edit Medical Certificate") }}
				</h3>
				<p class="text-sm text-gray-600 mb-4">
					{{ trans("Update medical certificate for your leave request") }}
				</p>

				<form @submit.prevent="submitEdit" class="space-y-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
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
							class="w-full border border-gray-300 rounded-lg px-3 py-2"
							accept=".pdf,.jpg,.jpeg,.png" />
						<p class="text-xs text-gray-500 mt-1">
							{{ trans("Upload medical certificate (PDF, JPG, PNG)") }}
						</p>
						<p v-if="editForm.errors.attachments" class="text-sm text-red-500 mt-1">
							{{ editForm.errors.attachments }}
						</p>
					</div>

					<div class="flex justify-end gap-3 pt-4">
						<Button @click="closeEditModal" :label="trans('Cancel')" type="tertiary" />
						<Button type="submit" :label="trans('Save')" :loading="isSubmitting" />
					</div>
				</form>
			</div>
		</Modal>
	</div>
</template>
