<script setup lang="ts">
import { ref } from "vue"
import { Head, router, useForm } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import {
	faCheckCircle,
	faTimesCircle,
	faClock,
	faPencilAlt,
	faDownload,
	faFileExcel,
	faFileCsv,
} from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faCheckCircle, faTimesCircle, faClock, faPencilAlt, faDownload, faFileExcel, faFileCsv)

const props = defineProps<{
	title: string
	pageHead: {
		title: string
		icon: string[]
	}
	leaves: {
		data: any[]
		links: any
		meta: any
	}
	type_options: Record<string, string>
	status_options: Record<string, string>
}>()

const locale = useLocaleStore()
const isRejectModalOpen = ref(false)
const isEditModalOpen = ref(false)
const isExportModalOpen = ref(false)
const selectedLeave = ref<any>(null)
const isSubmitting = ref(false)
const isExporting = ref(false)

const rejectForm = useForm({
	rejection_reason: "",
})

const editForm = useForm({
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
		notify({
			title: trans("Error"),
			text: trans("Cannot find organisation ID"),
			type: "error",
		})
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

const approveLeave = (leave: any) => {
	const orgId = route().params.organisation
	console.log("Approving leave:", leave.id, "organisation:", orgId)
	console.log(
		"Route:",
		route("grp.org.hr.leaves.approve", { organisation: orgId, leave: leave.id })
	)

	if (!orgId) {
		console.error("No organisation ID found!")
		alert("Error: Cannot find organisation ID")
		return
	}

	router.post(
		route("grp.org.hr.leaves.approve", {
			organisation: orgId,
			leave: leave.id,
		}),
		{},
		{
			preserveScroll: true,
			onBefore: () => {
				console.log("Sending approve request...")
			},
			onSuccess: () => {
				console.log("Approve successful!")
			},
			onError: (errors) => {
				console.error("Approve errors:", errors)
				alert("Error: " + JSON.stringify(errors))
			},
		}
	)
}

const openRejectModal = (leave: any) => {
	selectedLeave.value = leave
	isRejectModalOpen.value = true
}

const submitReject = () => {
	if (!selectedLeave.value) return

	const orgId = route().params.organisation
	console.log("Rejecting leave:", selectedLeave.value.id, "organisation:", orgId)

	if (!orgId) {
		console.error("No organisation ID found!")
		alert("Error: Cannot find organisation ID")
		return
	}

	isSubmitting.value = true
	rejectForm.post(
		route("grp.org.hr.leaves.reject", {
			organisation: orgId,
			leave: selectedLeave.value.id,
		}),
		{
			preserveScroll: true,
			onSuccess: () => {
				console.log("Reject successful!")
				isRejectModalOpen.value = false
				rejectForm.reset()
				selectedLeave.value = null
			},
			onError: (errors) => {
				console.error("Reject errors:", errors)
				alert("Error: " + JSON.stringify(errors))
			},
			onFinish: () => {
				isSubmitting.value = false
			},
		}
	)
}

const closeRejectModal = () => {
	isRejectModalOpen.value = false
	rejectForm.reset()
	selectedLeave.value = null
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

	const orgId = route().params.organisation
	isSubmitting.value = true
	editForm.post(
		route("grp.org.hr.leaves.update", {
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
</script>

<template>
	<Head :title="title" />
	<PageHeading :data="pageHead">
		<template #other>
			<Button
				@click="openExportModal"
				:label="trans('Export')"
				type="secondary"
				icon="fal fa-download" />
		</template>
	</PageHeading>

	<div class="px-4 py-4">
		<Table :resource="leaves">
			<template #cell(employee_name)="{ item: leave }">
				<span class="font-medium text-gray-900">{{ leave.employee_name }}</span>
			</template>

			<template #cell(type_label)="{ item: leave }">
				<span
					class="px-2 py-1 rounded-full text-xs font-medium"
					:class="typeColors[leave.type]">
					{{ leave.type_label }}
				</span>
			</template>

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

			<template #cell(status_label)="{ item: leave }">
				<span
					class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border"
					:class="statusColors[leave.status]">
					<FontAwesomeIcon :icon="statusIcons[leave.status]" fixed-width />
					{{ leave.status_label }}
				</span>
			</template>

			<template #cell(reason)="{ item: leave }">
				<span class="text-gray-600 text-sm truncate max-w-xs block">{{
					leave.reason
				}}</span>
			</template>

			<template #cell(attachments)="{ item: leave }">
				<div class="flex flex-wrap gap-2">
					<a
						v-for="attachment in leave.attachments ?? []"
						:key="attachment.id"
						:href="attachment.url"
						target="_blank"
						rel="noopener"
						class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded">
						{{ attachment.name }}
					</a>
					<span
						v-if="!leave.attachments || leave.attachments.length === 0"
						class="text-gray-400 text-xs">
						{{ trans("None") }}
					</span>
				</div>
			</template>

			<template #cell(actions)="{ item: leave }">
				<div v-if="leave.status === 'pending'" class="flex gap-2">
					<Button
						v-if="
							leave.type === 'medical' &&
							(!leave.attachments || leave.attachments.length === 0)
						"
						@click="openEditModal(leave)"
						:label="trans('Edit')"
						size="xs"
						type="secondary"
						icon="fal fa-pencil-alt" />
					<Button
						@click="approveLeave(leave)"
						:label="trans('Approve')"
						size="xs"
						type="primary"
						icon="fal fa-check" />
					<Button
						@click="openRejectModal(leave)"
						:label="trans('Reject')"
						size="xs"
						type="delete"
						icon="fal fa-times" />
				</div>
				<span v-else class="text-gray-400 text-xs">{{ trans("Processed") }}</span>
			</template>
		</Table>
	</div>

	<Modal :isOpen="isRejectModalOpen" @onClose="closeRejectModal" width="w-full max-w-md">
		<div class="p-6">
			<h3 class="text-lg font-semibold text-gray-900 mb-4">
				{{ trans("Reject Leave Request") }}
			</h3>
			<p class="text-sm text-gray-600 mb-4">
				{{ trans("Are you sure you want to reject this leave request from") }}
				<strong>{{ selectedLeave?.employee_name }}</strong
				>?
			</p>

			<form @submit.prevent="submitReject" class="space-y-4">
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1">{{
						trans("Reason for rejection")
					}}</label>
					<textarea
						v-model="rejectForm.rejection_reason"
						rows="3"
						class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500"
						:placeholder="trans('Please provide a reason for rejection')" />
					<p v-if="rejectForm.errors.rejection_reason" class="text-sm text-red-500 mt-1">
						{{ rejectForm.errors.rejection_reason }}
					</p>
				</div>

				<div class="flex justify-end gap-3 pt-4">
					<Button @click="closeRejectModal" :label="trans('Cancel')" type="tertiary" />
					<Button
						type="primary"
						nativeType="submit"
						:label="trans('Reject')"
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
				{{ trans("Update medical certificate for") }}
				<strong>{{ selectedLeave?.employee_name }}</strong
				>:
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
					<Button
						type="primary"
						nativeType="submit"
						:label="trans('Save')"
						:loading="isSubmitting" />
				</div>
			</form>
		</div>
	</Modal>

	<Modal :isOpen="isExportModalOpen" @onClose="closeExportModal" width="w-full max-w-lg">
		<div class="p-6">
			<h3 class="text-lg font-semibold text-gray-900 mb-4">
				{{ trans("Export Leave Reports") }}
			</h3>
			<p class="text-sm text-gray-600 mb-4">
				{{ trans("Select filters and export format for your leave report.") }}
			</p>

			<form @submit.prevent="submitExport" class="space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("From Date")
						}}</label>
						<input
							v-model="exportForm.from"
							type="date"
							class="w-full border border-gray-300 rounded-lg px-3 py-2" />
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("To Date")
						}}</label>
						<input
							v-model="exportForm.to"
							type="date"
							class="w-full border border-gray-300 rounded-lg px-3 py-2" />
					</div>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Leave Type")
						}}</label>
						<select
							v-model="exportForm.type"
							class="w-full border border-gray-300 rounded-lg px-3 py-2">
							<option value="">{{ trans("All Types") }}</option>
							<option
								v-for="(label, value) in type_options"
								:key="value"
								:value="value">
								{{ label }}
							</option>
						</select>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Status")
						}}</label>
						<select
							v-model="exportForm.status"
							class="w-full border border-gray-300 rounded-lg px-3 py-2">
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
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Department")
						}}</label>
						<input
							v-model="exportForm.department"
							type="text"
							class="w-full border border-gray-300 rounded-lg px-3 py-2"
							:placeholder="trans('Filter by department')" />
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Team")
						}}</label>
						<input
							v-model="exportForm.team"
							type="text"
							class="w-full border border-gray-300 rounded-lg px-3 py-2"
							:placeholder="trans('Filter by team')" />
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1">{{
						trans("Employee")
					}}</label>
					<input
						v-model.number="exportForm.employee_id"
						type="number"
						class="w-full border border-gray-300 rounded-lg px-3 py-2"
						:placeholder="trans('Filter by employee ID')" />
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700 mb-2">{{
						trans("Export Format")
					}}</label>
					<div class="flex gap-4">
						<label class="flex items-center gap-2 cursor-pointer">
							<input
								v-model="exportForm.format"
								type="radio"
								value="xlsx"
								class="text-blue-600 focus:ring-blue-500" />
							<FontAwesomeIcon icon="fal fa-file-excel" class="text-green-600" />
							<span class="text-sm">{{ trans("Excel (XLSX)") }}</span>
						</label>
						<label class="flex items-center gap-2 cursor-pointer">
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

				<div class="flex justify-end gap-3 pt-4">
					<Button @click="closeExportModal" :label="trans('Cancel')" type="tertiary" />
					<Button
						type="primary"
						nativeType="submit"
						:label="trans('Export')"
						:loading="isExporting"
						icon="fal fa-download" />
				</div>
			</form>
		</div>
	</Modal>
</template>
