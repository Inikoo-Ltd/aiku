<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import DatePicker from "@vuepic/vue-datepicker"
import "@vuepic/vue-datepicker/dist/main.css"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ExportModalActions from "@/Components/HumanResources/ExportModalActions.vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import RejectLeaveModal from "@/Components/HumanResources/RejectLeaveModal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Tag from "@/Components/Tag.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faEdit, faDownload, faFileExcel, faFileCsv, faPaperclip, faTrash } from "@fal"

library.add(faCheck, faTimes, faEdit, faDownload, faFileExcel, faFileCsv, faPaperclip, faTrash)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	leaves: {
		data: any[]
		links: any
		meta: any
	}
	type_options: Record<string, string | { label: string; category?: string }>
	status_options: Record<string, string>
	can_edit_admin: boolean
	holidays: { date: string; label: string }[]
}>()

const parsedTypeOptions = computed(() => {
	return Object.entries(props.type_options ?? {}).map(([value, data]) => ({
		value,
		label: typeof data === "string" ? data : data.label || value,
	}))
})

const holidayMap = computed<Record<string, string>>(() => {
	const map: Record<string, string> = {}
	for (const h of props.holidays ?? []) {
		map[h.date] = h.label
	}
	return map
})

const isDisabledDate = (date: Date): boolean => {
	const day = date.getDay()
	if (day === 0 || day === 6) return true
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, "0")
	const d = String(date.getDate()).padStart(2, "0")
	return `${year}-${month}-${d}` in holidayMap.value
}

const holidayMarkers = computed(() =>
	(props.holidays ?? []).map((h) => ({
		date: new Date(h.date + "T00:00:00"),
		type: "dot" as const,
		tooltip: h.label ? [{ text: h.label, color: "#ef4444" }] : undefined,
		color: "#ef4444",
	}))
)

const locale = useLocaleStore()
const isEditModalOpen = ref(false)
const isExportModalOpen = ref(false)
const isRejectModalOpen = ref(false)
const selectedLeave = ref<any>(null)
const isSubmitting = ref(false)
const isExporting = ref(false)

const editForm = useForm({
	type: "",
	start_date: "",
	end_date: "",
	is_half_day: false,
	session: "Full",
	reason: "",
	attachments: [] as File[],
})

const exportForm = useForm({
	from: "",
	to: "",
	type: "",
	status: "",
	department: "",
	team: "",
	employee_id: null as number | null,
	format: "xlsx",
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

const toNumber = (value: unknown): number => {
	if (typeof value === "number") {
		return Number.isFinite(value) ? value : 0
	}

	if (typeof value === "string") {
		const parsed = Number(value)
		return Number.isFinite(parsed) ? parsed : 0
	}

	return 0
}

const approvalTotalSteps = (leave: any): number => {
	return Math.max(0, toNumber(leave.approval_total_steps))
}

const approvalCompletedSteps = (leave: any): number => {
	const total = approvalTotalSteps(leave)
	if (total === 0) {
		return 0
	}

	if (leave.status === "approved") {
		return total
	}

	return Math.min(Math.max(0, toNumber(leave.approval_completed_steps)), total)
}

const approvalProgressPercent = (leave: any): number => {
	const total = approvalTotalSteps(leave)
	if (total === 0) {
		return 0
	}

	return Math.round((approvalCompletedSteps(leave) / total) * 100)
}

const approvalStatusLabel = (leave: any): string => {
	if (leave.status === "approved") {
		return trans("Completed")
	}

	if (leave.status === "rejected") {
		return trans("Rejected")
	}

	return trans("In Progress")
}

const approvalProgressText = (leave: any): string => {
	const total = approvalTotalSteps(leave)
	if (total === 0) {
		return trans("No steps")
	}

	if (leave.status === "approved") {
		return trans(":steps steps completed", { steps: String(total) })
	}

	const currentStep = Math.min(
		Math.max(1, toNumber(leave.approval_current_step) || approvalCompletedSteps(leave) + 1),
		total
	)

	return trans("Level :current of :total", {
		current: String(currentStep),
		total: String(total),
	})
}

const approvalBarClass = (leave: any): string => {
	if (leave.status === "approved") {
		return "bg-green-500"
	}

	if (leave.status === "rejected") {
		return "bg-red-500"
	}

	return "bg-amber-500"
}

const openExportModal = () => {
	exportForm.reset()
	isExportModalOpen.value = true
}

const closeExportModal = () => {
	isExportModalOpen.value = false
	exportForm.reset()
}

const submitExport = () => {
	const orgId = route().params.organisation
	if (!orgId) {
		alert("Error: Cannot find organisation ID")
		return
	}

	isExporting.value = true

	const exportParams: Record<string, any> = {
		organisation: orgId,
		format: exportForm.format,
	}

	if (exportForm.from) exportParams.from = exportForm.from
	if (exportForm.to) exportParams.to = exportForm.to
	if (exportForm.type) exportParams.type = exportForm.type
	if (exportForm.status) exportParams.status = exportForm.status
	if (exportForm.department) exportParams.department = exportForm.department
	if (exportForm.team) exportParams.team = exportForm.team
	if (exportForm.employee_id) exportParams.employee_id = exportForm.employee_id

	isExportModalOpen.value = false
	window.location.href = route("grp.org.hr.leaves.export", exportParams)

	setTimeout(() => {
		isExporting.value = false
	}, 1500)
}

const openEditModal = (leave: any) => {
	selectedLeave.value = leave
	editForm.type = leave.type ?? ""
	editForm.start_date = leave.start_date ?? ""
	editForm.end_date = leave.end_date ?? ""
	editForm.is_half_day = leave.is_half_day ?? false
	editForm.session = leave.session ?? "Full"
	editForm.reason = leave.reason ?? ""
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

	const orgId = route().params.organisation
	isSubmitting.value = true
	editForm.patch(
		route("grp.org.hr.leaves.admin.update", {
			organisation: orgId,
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
				console.error("Edit errors:", errors)
			},
			onFinish: () => {
				isSubmitting.value = false
			},
		}
	)
}

const formatDate = (date: string) => {
	return useFormatTime(date, { localeCode: locale?.language?.code })
}

const openRejectModal = (leave: any) => {
	selectedLeave.value = leave
	isRejectModalOpen.value = true
}

const closeRejectModal = () => {
	isRejectModalOpen.value = false
	selectedLeave.value = null
}
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #other>
			<Button
				type="secondary"
				:icon="faDownload"
				:label="trans('Export')"
				@click="openExportModal" />
		</template>
	</PageHeading>

	<div class="mt-4">
		<Table :resource="leaves">
			<template #cell(employee_name)="{ item: leave }">
				<span class="whitespace-nowrap">
					{{ leave.employee_name }}
				</span>
			</template>

			<template #cell(type_label)="{ item: leave }">
				<span class="whitespace-nowrap">
					{{ leave.type_label }}
				</span>
			</template>

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

			<template #cell(status)="{ item: leave }">
				<Tag :theme="getStatusTheme(leave.status)" size="xs" :label="leave.status">
					<template #label>
						<span class="capitalize">
							{{ leave.status }}
						</span>
					</template>
				</Tag>
			</template>

			<template #cell(approval_progress)="{ item: leave }">
				<div class="min-w-40">
					<div class="flex items-center justify-between gap-2 text-xs">
						<span class="font-medium text-gray-700">
							{{ approvalStatusLabel(leave) }}
						</span>
						<span class="text-gray-500">
							{{ approvalProgressText(leave) }}
						</span>
					</div>
					<div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200">
						<div
							class="h-full rounded-full transition-all duration-300 ease-out"
							:class="approvalBarClass(leave)"
							:style="{ width: `${approvalProgressPercent(leave)}%` }" />
					</div>
				</div>
			</template>

			<template #cell(reason)="{ item: leave }">
				<div class="max-w-md">
					<div v-if="leave.reason" class="text-sm text-gray-600 break-words">
						{{ leave.reason }}
					</div>
					<div
						v-if="leave.status === 'rejected' && leave.rejection_reason"
						class="mt-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
						<div class="break-words">
							{{ leave.rejection_reason }}
						</div>
					</div>
					<span
						v-if="
							!leave.reason &&
							!(leave.status === 'rejected' && leave.rejection_reason)
						">
						—
					</span>
				</div>
			</template>

			<template #cell(attachments)="{ item: leave }">
				<div class="flex flex-wrap gap-1">
					<a
						v-for="attachment in leave.attachments ?? []"
						:key="attachment.id"
						:href="attachment.url"
						target="_blank"
						rel="noopener"
						class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-blue-700 bg-blue-50 rounded">
						<FontAwesomeIcon :icon="faPaperclip" class="mr-1" fixed-width />
						{{ attachment.name }}
					</a>
					<span
						v-if="!leave.attachments || leave.attachments.length === 0"
						class="text-gray-400 text-xs">
						—
					</span>
				</div>
			</template>

			<template #cell(actions)="{ item: leave }">
				<div class="flex items-center gap-2">
					<Button
						v-if="leave.status === 'pending' && props.can_edit_admin"
						type="transparent"
						size="xs"
						:icon="faEdit"
						:label="trans('Edit')"
						@click="() => openEditModal(leave)" />
                    <span v-if="leave.status !== 'pending'" class="text-gray-400 text-xs">
						{{ trans("Processed") }}
					</span>
					<ModalConfirmation
						v-if="leave.status === 'pending' && leave.can_approve_current_user"
						:routeYes="{
							name: 'grp.org.hr.leaves.approve',
							parameters: { ...route().params, leave: leave.id },
							method: 'post',
						}">
						<template #default="{ changeModel, isLoadingdelete }">
							<Button
								type="positive"
								size="xs"
								:icon="faCheck"
								:label="trans('Approve')"
								:loading="isLoadingdelete"
								@click="changeModel" />
						</template>
						<template #btn-yes="{ clickYes, isLoadingdelete }">
							<Button
								:loading="isLoadingdelete"
								@click="clickYes"
								:label="trans('Yes, approve')"
								type="positive" />
						</template>
					</ModalConfirmation>
					<Button
						v-if="leave.status === 'pending' && leave.can_approve_current_user"
						type="warning"
						size="xs"
						:icon="faTimes"
						:label="trans('Reject')"
						@click="() => openRejectModal(leave)" />
					<ModalConfirmationDelete
						:routeDelete="{
							name: 'grp.org.hr.leaves.delete',
							parameters: { ...route().params, leave: leave.id },
						}">
						<template #default="{ changeModel, isLoadingdelete }">
							<Button
								type="negative"
								size="xs"
								:icon="faTrash"
								:label="trans('Delete')"
								:loading="isLoadingdelete"
								@click="changeModel" />
						</template>
					</ModalConfirmationDelete>

				</div>
			</template>
		</Table>
	</div>

	<Modal :isOpen="isEditModalOpen" @onClose="closeEditModal" width="w-full max-w-lg">
		<h2 class="text-lg font-semibold text-gray-800 mb-1">
			{{ trans("Edit Leave Request") }}
		</h2>
		<p class="text-sm text-gray-500 mb-4">
			{{ selectedLeave?.employee_name }}
		</p>

		<form @submit.prevent="submitEdit" class="space-y-4">
			<div>
				<label class="block text-sm font-medium text-gray-700">{{
					trans("Leave Type")
				}}</label>
				<select
					v-model="editForm.type"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
					<option
						v-for="option in parsedTypeOptions"
						:key="option.value"
						:value="option.value">
						{{ option.label }}
					</option>
				</select>
				<p v-if="editForm.errors.type" class="mt-1 text-sm text-red-600">
					{{ editForm.errors.type }}
				</p>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Start Date")
					}}</label>
					<DatePicker
						:modelValue="editForm.start_date ? new Date(editForm.start_date) : null"
						@update:modelValue="(date: Date) => (editForm.start_date = date ? date.toISOString().split('T')[0] : '')"
						:disabledDates="isDisabledDate"
						:markers="holidayMarkers"
						:enableTimePicker="false"
						:clearable="true"
						:autoApply="true"
						:placeholder="trans('Select start date')"
						class="mt-1 block w-full"
						inputClassName="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<p v-if="editForm.errors.start_date" class="mt-1 text-sm text-red-600">
						{{ editForm.errors.start_date }}
					</p>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("End Date")
					}}</label>
					<DatePicker
						:modelValue="editForm.end_date ? new Date(editForm.end_date) : null"
						@update:modelValue="(date: Date) => (editForm.end_date = date ? date.toISOString().split('T')[0] : '')"
						:disabledDates="isDisabledDate"
						:markers="holidayMarkers"
						:enableTimePicker="false"
						:clearable="true"
						:autoApply="true"
						:placeholder="trans('Select end date')"
						class="mt-1 block w-full"
						inputClassName="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<p v-if="editForm.errors.end_date" class="mt-1 text-sm text-red-600">
						{{ editForm.errors.end_date }}
					</p>
				</div>
			</div>

			<div>
				<label class="block text-sm font-medium text-gray-700">{{
					trans("Reason")
				}}</label>
				<textarea
					v-model="editForm.reason"
					rows="3"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm resize-none"
					:placeholder="trans('Enter reason...')" />
				<p v-if="editForm.errors.reason" class="mt-1 text-sm text-red-600">
					{{ editForm.errors.reason }}
				</p>
			</div>

			<div>
				<label class="block text-sm font-medium text-gray-700">{{
					trans("Attachments")
				}}</label>
				<div
					v-if="selectedLeave?.attachments?.length"
					class="mt-1 mb-2 flex flex-wrap gap-1">
					<a
						v-for="att in selectedLeave.attachments"
						:key="att.id"
						:href="att.url"
						target="_blank"
						rel="noopener"
						class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-blue-700 bg-blue-50 rounded">
						<FontAwesomeIcon :icon="faPaperclip" class="mr-1" fixed-width />
						{{ att.name }}
					</a>
				</div>
				<input
					type="file"
					multiple
					@change="
						(e: Event) => {
							const files = (e.target as HTMLInputElement).files
							if (files) editForm.attachments = Array.from(files)
						}
					"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
					accept=".pdf,.jpg,.jpeg,.png" />
				<p class="mt-1 text-xs text-gray-500">
					{{ trans("Uploading new files will replace existing attachments (PDF, JPG, PNG, max 5 files)") }}
				</p>
				<p v-if="editForm.errors.attachments" class="mt-1 text-sm text-red-600">
					{{ editForm.errors.attachments }}
				</p>
			</div>

			<div class="mt-6 flex justify-end gap-2">
				<Button @click="closeEditModal" :label="trans('Cancel')" type="tertiary" />
				<Button
					type="save"
					nativeType="submit"
					:label="trans('Save')"
					:loading="isSubmitting" />
			</div>
		</form>
	</Modal>

	<Modal :isOpen="isExportModalOpen" @onClose="closeExportModal" width="w-full max-w-lg">
		<h2 class="text-lg font-semibold text-gray-800 mb-4">
			{{ trans("Export Leave Reports") }}
		</h2>
		<p class="text-sm text-gray-600 mb-4">
			{{ trans("Select filters and export format for your leave report.") }}
		</p>

		<form @submit.prevent="submitExport" class="space-y-4">
			<div class="grid grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("From Date")
					}}</label>
					<input
						v-model="exportForm.from"
						type="date"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("To Date")
					}}</label>
					<input
						v-model="exportForm.to"
						type="date"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
				</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Leave Type")
					}}</label>
					<select
						v-model="exportForm.type"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
						<option value="">{{ trans("All Types") }}</option>
						<option
							v-for="option in parsedTypeOptions"
							:key="option.value"
							:value="option.value">
							{{ option.label }}
						</option>
					</select>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Status")
					}}</label>
					<select
						v-model="exportForm.status"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
						<option value="">{{ trans("All Statuses") }}</option>
						<option
							v-for="(label, value) in status_options"
							:key="value"
							:value="value">
							{{ label }}
						</option>
					</select>
				</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Department")
					}}</label>
					<select
						v-model="exportForm.department"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
						<option value="">{{ trans("All Departments") }}</option>
						<option
							v-for="(label, value) in parsedDepartmentOptions"
							:key="value"
							:value="value">
							{{ label }}
						</option>
					</select>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Team")
					}}</label>
					<select
						v-model="exportForm.team"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
						<option value="">{{ trans("All Teams") }}</option>
						<option
							v-for="(label, value) in parsedTeamOptions"
							:key="value"
							:value="value">
							{{ label }}
						</option>
					</select>
				</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Employee")
					}}</label>
					<select
						v-model="exportForm.employee_id"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
						<option value="">{{ trans("All Employees") }}</option>
						<option
							v-for="(label, value) in parsedEmployeeOptions"
							:key="value"
							:value="value">
							{{ label }}
						</option>
					</select>
				</div>
			</div>

			<div>
				<label class="block text-sm font-medium text-gray-700">{{
					trans("Export Format")
				}}</label>
				<div class="mt-2 flex gap-4">
					<label class="flex cursor-pointer items-center gap-2">
						<input
							v-model="exportForm.format"
							type="radio"
							value="xlsx"
							class="text-blue-600 focus:ring-blue-500" />
						<FontAwesomeIcon icon="fal fa-file-excel" class="text-green-600" />
						<span class="text-sm">{{ trans("Excel (XLSX)") }}</span>
					</label>
					<label class="flex cursor-pointer items-center gap-2">
						<input
							v-model="exportForm.format"
							type="radio"
							value="csv"
							class="text-blue-600 focus:ring-blue-500" />
						<FontAwesomeIcon icon="fal fa-file-csv" class="text-blue-600" />
						<span class="text-sm">{{ trans("CSV") }}</span>
					</label>
				</div>
			</div>
		</form>
		<ExportModalActions
			class-name="mt-6 flex justify-end gap-2"
			:loading="isExporting"
			export-icon="fal fa-download"
			@cancel="closeExportModal"
			@export="submitExport" />
	</Modal>

	<RejectLeaveModal
		:isOpen="isRejectModalOpen"
		:leave="selectedLeave"
		:route="{
			name: 'grp.org.hr.leaves.reject',
			parameters: { ...route().params, leave: selectedLeave?.id },
		}"
		@close="closeRejectModal" />
</template>
