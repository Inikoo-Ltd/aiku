<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3"
import { computed, onMounted, reactive, ref, watch } from "vue"
import { debounce } from "lodash-es"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Pagination from "@/Components/Table/Pagination.vue"
import HrRequestCard from "@/Components/HumanResources/HrRequestCard.vue"
import HrRequestFilters from "@/Components/HumanResources/HrRequestFilters.vue"
import HrConfirmDialog from "@/Components/HumanResources/HrConfirmDialog.vue"
import EmployeeAvatar from "@/Components/HumanResources/EmployeeAvatar.vue"
import RejectLeaveModal from "@/Components/HumanResources/RejectLeaveModal.vue"
import LeaveCoverSelect, { type LeaveCoverOption } from "@/Components/HumanResources/LeaveCoverSelect.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Select from "primevue/select"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import FileUpload from "primevue/fileupload"
import RadioButton from "primevue/radiobutton"
import { useConfirm } from "primevue/useconfirm"
import Textarea from "primevue/textarea"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { isOnLeaveDuring } from "@/Composables/useLeaveCovers"
import { dateToIso, fieldFocusClass, inputFocusClass, isoToDate } from "@/Composables/useHrFormControls"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faEdit, faDownload, faFileExcel, faFileCsv, faPaperclip, faTrash, faPlus, faChartNetwork, faUserFriends, faCalendarAlt, faFileAlt } from "@fal"

library.add(faCheck, faTimes, faEdit, faDownload, faFileExcel, faFileCsv, faPaperclip, faTrash, faPlus, faChartNetwork, faUserFriends, faCalendarAlt, faFileAlt)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	leaves: any
	filters?: {
		search: string
		status: string | null
		type: string | null
		from: string | null
		to: string | null
	}
	can_delete?: boolean
	type_options: Record<string, string | { label: string; category?: string }>
	status_options: Record<string, string>
	can_edit_admin: boolean
	can_record: boolean
	holidays: { date: string; label: string }[]
	employee_options: Record<string, string>
	employee_job_positions: Record<string, { id: number; name: string; department: string | null }[]>
	employee_ids_with_group_access: number[]
	employee_leave_periods: Record<string, [string, string][]>
}>()

const parsedEmployeeOptions = computed(() => props.employee_options ?? {})

const employeeSelectOptions = computed(() =>
	Object.entries(parsedEmployeeOptions.value).map(([value, label]) => ({ value, label }))
)

const employeeIdsWithGroupAccess = computed(() => new Set((props.employee_ids_with_group_access ?? []).map(String)))

const coverOptionsFor = (absentEmployeeId: string | number, startDate: string, endDate: string): LeaveCoverOption[] => {
	const absentHasGroupAccess = employeeIdsWithGroupAccess.value.has(String(absentEmployeeId))
	const absentPositions = props.employee_job_positions?.[absentEmployeeId] ?? []
	const absentPositionIds = new Set(absentPositions.map((position) => position.id))
	const absentDepartments = new Set(absentPositions.map((position) => position.department).filter(Boolean))

	return employeeSelectOptions.value
		.filter((option) => option.value !== String(absentEmployeeId))
		.map((option) => {
			const positions = props.employee_job_positions?.[option.value] ?? []
			const sharedRoles = positions.filter((position) => absentPositionIds.has(position.id)).map((position) => position.name)
			const sharedDepartments = [...new Set(positions.map((position) => position.department).filter((department) => department && absentDepartments.has(department)))]

			const lacksGroupAccess = absentHasGroupAccess && !employeeIdsWithGroupAccess.value.has(option.value)
			const disabled = isOnLeaveDuring(props.employee_leave_periods?.[option.value], startDate, endDate)
			const tooltip = disabled
				? ctrans("On leave during this period")
				: lacksGroupAccess
				? ctrans("No group access: cannot reach the group-wide sections the absent employee works in")
				: sharedRoles.length
				? ctrans("Same organisation and same role: :roles", { roles: sharedRoles.join(", ") })
				: sharedDepartments.length
				? ctrans("Same organisation and same department: :departments", { departments: sharedDepartments.join(", ") })
				: null

			return { ...option, sharedRoles, sharedDepartments, lacksGroupAccess, disabled, tooltip }
		})
		.sort((a, b) => Number(a.disabled) - Number(b.disabled) || Number(a.lacksGroupAccess) - Number(b.lacksGroupAccess) || b.sharedRoles.length - a.sharedRoles.length || b.sharedDepartments.length - a.sharedDepartments.length)
}

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

const holidayDates = computed(() => (props.holidays ?? []).map((holiday) => isoToDate(holiday.date)).filter((date): date is Date => !!date))

const holidayLabel = (date: { year: number; month: number; day: number }): string | undefined =>
	holidayMap.value[`${date.year}-${String(date.month + 1).padStart(2, "0")}-${String(date.day).padStart(2, "0")}`]

const locale = useLocaleStore()
const isEditModalOpen = ref(false)
const isRecordModalOpen = ref(false)
const isExportModalOpen = ref(false)
const isRejectModalOpen = ref(false)
const isApproveModalOpen = ref(false)
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
	cover_employee_id: "" as string | number,
})

const recordForm = useForm({
	employee_id: "" as string | number,
	type: "",
	start_date: "",
	end_date: "",
	reason: "",
	cover_employee_id: "" as string | number,
})

watch(
	() => recordForm.employee_id,
	() => (recordForm.cover_employee_id = "")
)

const isoDateModel = (form: Record<string, any>, key: string) =>
	computed<Date | null>({
		get: () => isoToDate(form[key]),
		set: (value) => {
			form[key] = dateToIso(value)
		},
	})

const recordStartDate = isoDateModel(recordForm, "start_date")
const recordEndDate = isoDateModel(recordForm, "end_date")
const editStartDate = isoDateModel(editForm, "start_date")
const editEndDate = isoDateModel(editForm, "end_date")

watch(
	() => editForm.start_date,
	(startDate) => {
		if (editForm.is_half_day && startDate) {
			editForm.end_date = startDate
		}
	}
)

const maxEditAttachments = 5
const maxAttachmentSize = 5 * 1024 * 1024
const editFileUpload = ref<InstanceType<typeof FileUpload> | null>(null)

const onEditAttachmentsChanged = (event: { files: File[] }) => {
	editForm.attachments = [...event.files]
	editForm.clearErrors("attachments")
}

const formatFileSize = (bytes: number) => (bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`)

const fileUploadPt = {
	root: { class: "!border-0 !bg-transparent" },
	header: { class: "!p-0 !border-0 !bg-transparent" },
	content: { class: "!p-0 !pt-2 !border-0 !bg-transparent" },
}

const closeRecordModal = () => {
	isRecordModalOpen.value = false
}

const recordCoverOptions = computed(() => coverOptionsFor(recordForm.employee_id, recordForm.start_date, recordForm.end_date))

const editCoverOptions = computed(() => coverOptionsFor(selectedLeave.value?.employee_id ?? "", editForm.start_date, editForm.end_date))

const approveForm = useForm({
	cover_employee_id: "" as string | number,
})

const approveCoverOptions = computed(() =>
	coverOptionsFor(selectedLeave.value?.employee_id ?? "", selectedLeave.value?.start_date ?? "", selectedLeave.value?.end_date ?? "")
)

const openApproveModal = (leave: any) => {
	selectedLeave.value = leave
	approveForm.clearErrors()
	approveForm.cover_employee_id = leave.cover_employee_id ? String(leave.cover_employee_id) : ""
	isApproveModalOpen.value = true
}

const closeApproveModal = () => {
	isApproveModalOpen.value = false
	selectedLeave.value = null
	approveForm.clearErrors()
}

const submitApprove = () => {
	if (!selectedLeave.value) return

	approveForm.post(route("grp.org.hr.leaves.approve", { ...route().params, leave: selectedLeave.value.id }), {
		preserveScroll: true,
		onSuccess: closeApproveModal,
	})
}

const submitRecord = () => {
	recordForm.post(
		route("grp.org.hr.leaves.store", { ...route().params, employee: recordForm.employee_id }),
		{
			preserveScroll: true,
			onSuccess: () => {
				isRecordModalOpen.value = false
				recordForm.reset()
			},
		}
	)
}

onMounted(() => {
	if (props.can_record && new URLSearchParams(window.location.search).get("record")) {
		isRecordModalOpen.value = true
	}
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
		return ctrans("Completed")
	}

	if (leave.status === "rejected") {
		return ctrans("Rejected")
	}

	return ctrans("In Progress")
}

const approvalProgressText = (leave: any): string => {
	const total = approvalTotalSteps(leave)
	if (total === 0) {
		return ctrans("No steps")
	}

	if (leave.status === "approved") {
		return ctrans(":steps steps completed", { steps: String(total) })
	}

	const currentStep = Math.min(
		Math.max(1, toNumber(leave.approval_current_step) || approvalCompletedSteps(leave) + 1),
		total
	)

	return ctrans("Level :current of :total", {
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
	editForm.cover_employee_id = leave.cover_employee_id ? String(leave.cover_employee_id) : ""
	editForm.clearErrors()
	isEditModalOpen.value = true
}

const closeEditModal = () => {
	isEditModalOpen.value = false
	editFileUpload.value?.clear()
	editForm.reset()
	editForm.clearErrors()
	selectedLeave.value = null
}

const submitEdit = () => {
	if (!selectedLeave.value) return

	const orgId = route().params.organisation
	isSubmitting.value = true
	editForm
		.transform((data) => ({ ...data, _method: "patch" }))
		.post(
			route("grp.org.hr.leaves.admin.update", {
				organisation: orgId,
				leave: selectedLeave.value.id,
			}),
			{
				preserveScroll: true,
				forceFormData: true,
				onSuccess: closeEditModal,
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

const filterState = reactive({
	search: props.filters?.search ?? "",
	status: props.filters?.status ?? "all",
	type: props.filters?.type ?? "",
	from: props.filters?.from ?? "",
	to: props.filters?.to ?? "",
})
const isLoadingList = ref(false)

const statusFilterOptions = computed(() => [
	{ label: ctrans("All"), value: "all" },
	{ label: ctrans("Pending"), value: "pending", tone: "amber" as const },
	{ label: ctrans("Approved"), value: "approved", tone: "emerald" as const },
	{ label: ctrans("Rejected"), value: "rejected", tone: "red" as const },
])

const typeFilterModel = computed<string | null>({
	get: () => filterState.type || null,
	set: (value) => {
		filterState.type = value ?? ""
	},
})

const rows = computed<any[]>(() => props.leaves?.data ?? [])
const paginationMeta = computed(() => props.leaves?.meta ?? props.leaves ?? {})
const totalRequests = computed<number>(() => paginationMeta.value?.total ?? rows.value.length)
const hasActiveFilters = computed(
	() => filterState.search.trim() !== "" || filterState.status !== "all" || filterState.type !== "" || filterState.from !== "" || filterState.to !== ""
)

const listVisitOptions = {
	preserveState: true,
	preserveScroll: true,
	replace: true,
	only: ["leaves", "filters"],
	onStart: () => {
		isLoadingList.value = true
	},
	onFinish: () => {
		isLoadingList.value = false
	},
}

const reloadList = (extraQuery: Record<string, any> = {}) => {
	const query: Record<string, any> = { ...extraQuery }
	const search = filterState.search.trim()

	if (search) query.filter = { global: search }
	if (filterState.status !== "all") query.status = filterState.status
	if (filterState.type) query.type = filterState.type
	if (filterState.from) query.from = filterState.from
	if (filterState.to) query.to = filterState.to

	router.get(route("grp.org.hr.leaves.index", { organisation: (route().params as Record<string, any>).organisation }), query, listVisitOptions)
}

const reloadListDebounced = debounce(() => reloadList(), 350)

watch(
	() => filterState.search,
	() => reloadListDebounced()
)

watch(
	() => [filterState.status, filterState.type, filterState.from, filterState.to],
	() => {
		reloadListDebounced.cancel()
		reloadList()
	}
)

const clearFilters = () => {
	filterState.search = ""
	filterState.status = "all"
	filterState.type = ""
	filterState.from = ""
	filterState.to = ""
}

const visitPage = (url?: string | null) => {
	if (!url) return
	router.get(url, {}, listVisitOptions)
}

const changePerPage = (perPage: number) => reloadList({ perPage })

const statusLabel = (status: string): string => props.status_options?.[status] ?? status

const typeTagStyle = (color: string) => ({
	backgroundColor: `color-mix(in srgb, ${color} 12%, white)`,
	borderColor: `color-mix(in srgb, ${color} 35%, white)`,
	color: `color-mix(in srgb, ${color} 75%, black)`,
})

const daysLabel = (leave: any): string => {
	const days = toNumber(leave.duration_days)

	return days === 1 ? ctrans("1 day") : ctrans(":days days", { days: String(days) })
}

const decidedApprovals = (leave: any): any[] => (leave.approvals ?? []).filter((approval: any) => approval.status !== "pending")

const parseYmdDate = (value: string): Date | null => {
	const [year, month, day] = (value ?? "").split("-").map(Number)

	return year && month && day ? new Date(year, month - 1, day) : null
}

const formatYmdDate = (date: Date | null): string =>
	date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : ""

const exportFromDateModel = computed<Date | null>({
	get: () => parseYmdDate(exportForm.from),
	set: (value) => {
		exportForm.from = formatYmdDate(value)
	},
})

const exportToDateModel = computed<Date | null>({
	get: () => parseYmdDate(exportForm.to),
	set: (value) => {
		exportForm.to = formatYmdDate(value)
	},
})

const exportStatusOptions = computed(() => Object.entries(props.status_options ?? {}).map(([value, label]) => ({ value, label })))

const exportDepartmentOptions = computed(() =>
	[...new Set(Object.values(props.employee_job_positions ?? {}).flatMap((positions) => positions.map((position) => position.department)))]
		.filter((department): department is string => !!department)
		.sort()
		.map((department) => ({ value: department, label: department }))
)

const exportEmployeeOptions = computed(() => employeeSelectOptions.value.map((option) => ({ value: Number(option.value), label: option.label })))

const exportFormatOptions = computed(() => [
	{ label: ctrans("Excel (XLSX)"), value: "xlsx", icon: faFileExcel, iconClass: "text-green-600" },
	{ label: ctrans("CSV"), value: "csv", icon: faFileCsv, iconClass: "text-sky-600" },
])

const confirm = useConfirm()
const deletingLeaveId = ref<number | null>(null)

const deleteLeaveMessage = (leave: any): string => {
	const details = {
		name: leave.employee_name,
		type: leave.type_label,
		from: formatDate(leave.start_date),
		to: formatDate(leave.end_date),
	}

	if (leave.status === "approved") {
		return ctrans(
			"This leave was already approved. Deleting it takes :name's :type (:from – :to) off their leave record and the leave calendar, ends any absence cover assigned to it, and returns the days it used to their leave balance.",
			details
		)
	}

	if (leave.status === "rejected") {
		return ctrans(
			"This request was already rejected. Deleting it removes :name's :type request (:from – :to), and the reason it was rejected, from their leave record. Their leave balance is not affected.",
			details
		)
	}

	return ctrans("Delete :name's :type request (:from – :to)? It has not been approved or rejected yet, and the approvers will no longer see it.", details)
}

const confirmDeleteLeave = (leave: any) => {
	confirm.require({
		group: "leave-admin",
		header: leave.status === "approved" ? ctrans("Delete approved leave") : leave.status === "rejected" ? ctrans("Delete rejected leave request") : ctrans("Delete leave request"),
		message: deleteLeaveMessage(leave),
		rejectProps: { label: ctrans("Cancel") },
		acceptProps: { label: leave.status === "pending" ? ctrans("Delete") : ctrans("Delete anyway"), severity: "danger" },
		accept: () => {
			router.delete(route("grp.org.hr.leaves.delete", { organisation: route().params.organisation, leave: leave.id }), {
				preserveScroll: true,
				preserveState: true,
				onStart: () => (deletingLeaveId.value = leave.id),
				onFinish: () => (deletingLeaveId.value = null),
			})
		},
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #other>
			<Button
				v-if="can_record"
				type="create"
				:icon="faPlus"
				:label="ctrans('Record leave')"
				@click="isRecordModalOpen = true" />
			<Button
				type="secondary"
				:icon="faDownload"
				:label="ctrans('Export')"
				@click="openExportModal" />
		</template>
	</PageHeading>

	<Dialog
		v-model:visible="isRecordModalOpen"
		modal
		:draggable="false"
		:header="ctrans('Record leave')"
		:style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }">
		<p class="mb-4 text-sm text-gray-500">{{ ctrans("Recorded by HR on behalf of the employee, approved immediately.") }}</p>

		<form class="space-y-4" @submit.prevent="submitRecord">
			<div>
				<label for="record-leave-employee" class="mb-1 block text-sm font-medium text-gray-700">
					{{ ctrans("Employee") }} <span class="text-red-500">*</span>
				</label>
				<Select
					v-model="recordForm.employee_id"
					inputId="record-leave-employee"
					:options="employeeSelectOptions"
					optionLabel="label"
					optionValue="value"
					filter
					fluid
					:invalid="!!(recordForm.errors as Record<string, string>).employee_id"
					:placeholder="ctrans('Select employee')"
					:class="fieldFocusClass" />
				<p v-if="(recordForm.errors as Record<string, string>).employee_id" class="mt-1 text-sm text-red-500">
					{{ (recordForm.errors as Record<string, string>).employee_id }}
				</p>
			</div>

			<div>
				<label for="record-leave-type" class="mb-1 block text-sm font-medium text-gray-700">
					{{ ctrans("Leave Type") }} <span class="text-red-500">*</span>
				</label>
				<Select
					v-model="recordForm.type"
					inputId="record-leave-type"
					:options="parsedTypeOptions"
					optionLabel="label"
					optionValue="value"
					filter
					fluid
					:invalid="!!recordForm.errors.type"
					:placeholder="ctrans('Select type')"
					:class="fieldFocusClass" />
				<p v-if="recordForm.errors.type" class="mt-1 text-sm text-red-500">{{ recordForm.errors.type }}</p>
			</div>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<label for="record-leave-start" class="mb-1 block text-sm font-medium text-gray-700">
						{{ ctrans("Start Date") }} <span class="text-red-500">*</span>
					</label>
					<DatePicker
						v-model="recordStartDate"
						inputId="record-leave-start"
						:maxDate="recordEndDate ?? undefined"
						:manualInput="false"
						:invalid="!!recordForm.errors.start_date"
						dateFormat="d M yy"
						showIcon
						iconDisplay="input"
						fluid
						:placeholder="ctrans('Select start date')"
						:class="fieldFocusClass">
						<template #date="{ date }">
							<span :title="holidayLabel(date)" :class="holidayLabel(date) ? 'text-red-500 underline decoration-dotted' : ''">{{ date.day }}</span>
						</template>
					</DatePicker>
					<p v-if="recordForm.errors.start_date" class="mt-1 text-sm text-red-500">{{ recordForm.errors.start_date }}</p>
				</div>
				<div>
					<label for="record-leave-end" class="mb-1 block text-sm font-medium text-gray-700">
						{{ ctrans("End Date") }} <span class="text-red-500">*</span>
					</label>
					<DatePicker
						v-model="recordEndDate"
						inputId="record-leave-end"
						:minDate="recordStartDate ?? undefined"
						:manualInput="false"
						:invalid="!!recordForm.errors.end_date"
						dateFormat="d M yy"
						showIcon
						iconDisplay="input"
						fluid
						:placeholder="ctrans('Select end date')"
						:class="fieldFocusClass">
						<template #date="{ date }">
							<span :title="holidayLabel(date)" :class="holidayLabel(date) ? 'text-red-500 underline decoration-dotted' : ''">{{ date.day }}</span>
						</template>
					</DatePicker>
					<p v-if="recordForm.errors.end_date" class="mt-1 text-sm text-red-500">{{ recordForm.errors.end_date }}</p>
				</div>
			</div>

			<LeaveCoverSelect
				v-model="recordForm.cover_employee_id"
				:options="recordCoverOptions"
				:error="recordForm.errors.cover_employee_id" />

			<div>
				<label for="record-leave-reason" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Reason") }}</label>
				<Textarea
					v-model="recordForm.reason"
					id="record-leave-reason"
					rows="2"
					autoResize
					fluid
					maxlength="1000"
					:invalid="!!recordForm.errors.reason"
					:class="inputFocusClass" />
				<p v-if="recordForm.errors.reason" class="mt-1 text-sm text-red-500">{{ recordForm.errors.reason }}</p>
			</div>

			<div class="flex justify-end gap-2 pt-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click.prevent="closeRecordModal">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" nativeType="submit" :label="ctrans('Record leave')" :loading="recordForm.processing" :disabled="!recordForm.employee_id">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>

	<HrRequestFilters
		v-model:search="filterState.search"
		v-model:status="filterState.status"
		v-model:from="filterState.from"
		v-model:to="filterState.to"
		:total="totalRequests"
		:countLabel="ctransChoice('leave request|leave requests', totalRequests)"
		:loading="isLoadingList"
		:statusOptions="statusFilterOptions"
		:searchPlaceholder="ctrans('Search employee, type or reason')"
		:extraFilterActive="filterState.type !== ''"
		@clear="clearFilters">
		<template #extra>
			<Select
				v-model="typeFilterModel"
				:options="parsedTypeOptions"
				optionLabel="label"
				optionValue="value"
				size="small"
				showClear
				:placeholder="ctrans('All leave types')"
				:aria-label="ctrans('Leave type')"
				class="w-full sm:w-48"
				:class="fieldFocusClass" />
		</template>
	</HrRequestFilters>

	<div class="px-4 py-4 transition-opacity" :class="{ 'pointer-events-none opacity-50': isLoadingList }" :aria-busy="isLoadingList">
		<div v-if="rows.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
			<FontAwesomeIcon :icon="faCalendarAlt" class="text-3xl text-gray-300" aria-hidden="true" />
			<p class="mt-3 text-sm font-medium text-gray-900">
				{{ hasActiveFilters ? ctrans("No leave requests match these filters") : ctrans("No leave requests yet") }}
			</p>
			<p v-if="hasActiveFilters" class="mt-1 text-sm text-gray-500">
				{{ ctrans("Try a different search, status, leave type or date range.") }}
			</p>
			<div v-if="hasActiveFilters" class="mt-4 flex justify-center">
				<Button type="tertiary" :label="ctrans('Clear filters')" @click="clearFilters">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</div>

		<div v-else class="flex flex-col gap-4">
			<HrRequestCard v-for="leave in rows" :key="leave.id" :status="leave.status" :statusLabel="statusLabel(leave.status)">
				<template #avatar>
					<EmployeeAvatar :name="leave.employee_name" :image="leave.employee_avatar" size="md" />
				</template>
				<template #title>{{ leave.employee_name }}</template>
				<template #meta>
					<span
						class="inline-flex items-center gap-1.5 whitespace-nowrap rounded border px-1.5 py-0.5 text-xs font-medium"
						:style="typeTagStyle(leave.type_color_hex)">
						<span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: leave.type_color_hex }" aria-hidden="true" />
						{{ leave.type_label }}
					</span>
				</template>

				<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
					<div class="rounded-md bg-gray-50 px-3 py-2">
						<div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Leave period") }}</div>
						<div class="mt-1 text-sm font-medium tabular-nums text-gray-900">
							{{ formatDate(leave.start_date) }}
							<template v-if="leave.end_date && leave.end_date !== leave.start_date"> – {{ formatDate(leave.end_date) }}</template>
						</div>
						<div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm tabular-nums text-gray-600">
							{{ daysLabel(leave) }}
							<span v-if="leave.is_half_day" class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">
								{{ ctrans("Half day") }}<template v-if="leave.session && leave.session !== 'Full'"> · {{ leave.session }}</template>
							</span>
						</div>
					</div>

					<div class="rounded-md bg-gray-50 px-3 py-2">
						<div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Approval") }}</div>
						<div class="mt-1 flex items-center justify-between gap-2 text-sm">
							<span class="font-medium text-gray-900">{{ approvalStatusLabel(leave) }}</span>
							<span class="tabular-nums text-gray-600">{{ approvalProgressText(leave) }}</span>
						</div>
						<div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
							<div
								class="h-full rounded-full transition-all duration-300 ease-out"
								:class="approvalBarClass(leave)"
								:style="{ width: `${approvalProgressPercent(leave)}%` }" />
						</div>
						<ul v-if="decidedApprovals(leave).length" class="mt-2 space-y-0.5 text-xs text-gray-600">
							<li v-for="approval in decidedApprovals(leave)" :key="`${approval.level}-${approval.approver_name}`" class="flex flex-wrap items-center gap-x-1.5">
								<FontAwesomeIcon
									:icon="approval.status === 'rejected' ? faTimes : faCheck"
									:class="approval.status === 'rejected' ? 'text-red-500' : 'text-green-600'"
									fixed-width
									aria-hidden="true" />
								<span>{{ ctrans("Level :level", { level: String(approval.level) }) }}</span>
								<span v-if="approval.approver_name" class="inline-flex items-center gap-1 font-medium text-gray-800">
									<EmployeeAvatar :name="approval.approver_name" :image="approval.approver_avatar" size="xs" />
									{{ approval.approver_name }}
								</span>
								<span v-if="approval.decided_at" class="tabular-nums text-gray-500">{{ useFormatTime(approval.decided_at, { formatTime: "hm", localeCode: locale?.language?.code }) }}</span>
							</li>
						</ul>
						<p v-else-if="leave.status !== 'pending' && (leave.approver_name || leave.approved_at)" class="mt-2 text-xs text-gray-600">
							<span v-if="leave.approver_name" class="inline-flex items-center gap-1 align-middle font-medium text-gray-800">
								<EmployeeAvatar :name="leave.approver_name" :image="leave.approver_avatar" size="xs" />
								{{ leave.approver_name }}
							</span>
							<span v-if="leave.approver_name && leave.approved_at" class="text-gray-300"> · </span>
							<span v-if="leave.approved_at" class="tabular-nums">{{ useFormatTime(leave.approved_at, { formatTime: "hm", localeCode: locale?.language?.code }) }}</span>
						</p>
					</div>
				</div>

				<dl class="mt-4 space-y-2 text-sm empty:hidden">
					<div v-if="leave.reason">
						<dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Reason") }}</dt>
						<dd class="whitespace-pre-line break-words text-gray-700">{{ leave.reason }}</dd>
					</div>
					<div v-if="leave.status === 'rejected' && leave.rejection_reason">
						<dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Rejection reason") }}</dt>
						<dd class="mt-1 whitespace-pre-line break-words rounded border border-red-200 bg-red-50 px-3 py-2 text-red-900">{{ leave.rejection_reason }}</dd>
					</div>
					<div v-if="leave.cover_employee_name">
						<dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Covered by") }}</dt>
						<dd class="flex items-center gap-1.5 text-gray-700">
							<EmployeeAvatar :name="leave.cover_employee_name" :image="leave.cover_employee_avatar" size="xs" />
							{{ leave.cover_employee_name }}
						</dd>
					</div>
					<div v-if="leave.attachments?.length">
						<dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Attachments") }}</dt>
						<dd class="mt-1 flex flex-wrap gap-1.5">
							<a
								v-for="attachment in leave.attachments"
								:key="attachment.id"
								:href="attachment.url"
								target="_blank"
								rel="noopener"
								class="inline-flex items-center gap-1 rounded bg-[--app-accent-soft] px-2 py-0.5 text-xs font-medium text-[--app-accent-strong] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]">
								<FontAwesomeIcon :icon="faPaperclip" fixed-width aria-hidden="true" />
								{{ attachment.name }}
							</a>
						</dd>
					</div>
				</dl>

				<template #actions>
					<template v-if="leave.status === 'pending'">
						<Button
							v-if="leave.can_approve_current_user"
							type="tertiary"
							size="xs"
							:label="ctrans('Approve')"
							@click="() => openApproveModal(leave)">
							<template #icon>
								<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
							</template>
						</Button>
						<Button
							v-if="leave.can_approve_current_user"
							type="tertiary"
							size="xs"
							:label="ctrans('Reject')"
							@click="() => openRejectModal(leave)">
							<template #icon>
								<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
							</template>
						</Button>
						<Button
							v-if="props.can_edit_admin"
							type="tertiary"
							size="xs"
							:label="ctrans('Edit')"
							@click="() => openEditModal(leave)">
							<template #icon>
								<FontAwesomeIcon :icon="faEdit" class="text-[--app-accent]" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</template>
					<span v-else class="mr-auto whitespace-nowrap text-xs text-gray-400">
						{{ ctrans("Processed") }}
					</span>
					<Button
						v-if="can_delete !== false"
						type="tertiary"
						size="xs"
						:label="ctrans('Delete')"
						:loading="deletingLeaveId === leave.id"
						@click="() => confirmDeleteLeave(leave)">
						<template #icon>
							<FontAwesomeIcon :icon="faTrash" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</template>
			</HrRequestCard>
		</div>

		<Pagination
			v-if="rows.length > 0 && Number(paginationMeta?.total ?? 0) > 15"
			:on-click="visitPage"
			:has-data="true"
			:meta="paginationMeta"
			:on-per-page-change="changePerPage"
			custom-wrapper-class="mt-4 rounded-lg border border-gray-200" />
	</div>

	<Dialog
		:visible="isEditModalOpen"
		modal
		:draggable="false"
		:header="ctrans('Edit Leave Request')"
		:style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }"
		@update:visible="(isVisible: boolean) => !isVisible && closeEditModal()">
		<form v-if="selectedLeave" class="space-y-4" @submit.prevent="submitEdit">
			<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">
				{{ selectedLeave.employee_name }}
			</div>

			<div>
				<label for="edit-leave-type" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Leave Type") }}</label>
				<Select
					v-model="editForm.type"
					inputId="edit-leave-type"
					:options="parsedTypeOptions"
					optionLabel="label"
					optionValue="value"
					filter
					fluid
					:invalid="!!editForm.errors.type"
					:placeholder="ctrans('Select type')"
					:class="fieldFocusClass" />
				<p v-if="editForm.errors.type" class="mt-1 text-sm text-red-500">{{ editForm.errors.type }}</p>
			</div>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<label for="edit-leave-start" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Start Date") }}</label>
					<DatePicker
						v-model="editStartDate"
						inputId="edit-leave-start"
						:disabledDays="[0, 6]"
						:disabledDates="holidayDates"
						:manualInput="false"
						:invalid="!!editForm.errors.start_date"
						dateFormat="d M yy"
						showIcon
						iconDisplay="input"
						showClear
						fluid
						:placeholder="ctrans('Select start date')"
						:class="fieldFocusClass">
						<template #date="{ date }">
							<span :title="holidayLabel(date)" :class="holidayLabel(date) ? 'text-red-500 underline decoration-dotted' : ''">{{ date.day }}</span>
						</template>
					</DatePicker>
					<p v-if="editForm.errors.start_date" class="mt-1 text-sm text-red-500">{{ editForm.errors.start_date }}</p>
				</div>
				<div>
					<label for="edit-leave-end" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("End Date") }}</label>
					<DatePicker
						v-model="editEndDate"
						inputId="edit-leave-end"
						:disabled="editForm.is_half_day"
						:minDate="editStartDate ?? undefined"
						:disabledDays="[0, 6]"
						:disabledDates="holidayDates"
						:manualInput="false"
						:invalid="!!editForm.errors.end_date"
						dateFormat="d M yy"
						showIcon
						iconDisplay="input"
						showClear
						fluid
						:placeholder="ctrans('Select end date')"
						:class="fieldFocusClass">
						<template #date="{ date }">
							<span :title="holidayLabel(date)" :class="holidayLabel(date) ? 'text-red-500 underline decoration-dotted' : ''">{{ date.day }}</span>
						</template>
					</DatePicker>
					<p v-if="editForm.errors.end_date" class="mt-1 text-sm text-red-500">{{ editForm.errors.end_date }}</p>
				</div>
			</div>

			<p v-if="editForm.is_half_day" class="text-xs text-gray-500">
				{{ ctrans("Half day leave is applied to one date only.") }}
			</p>

			<div>
				<label for="edit-leave-reason" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Reason") }}</label>
				<Textarea
					v-model="editForm.reason"
					id="edit-leave-reason"
					rows="3"
					autoResize
					fluid
					maxlength="1000"
					:invalid="!!editForm.errors.reason"
					:placeholder="ctrans('Enter reason...')"
					:class="inputFocusClass" />
				<p v-if="editForm.errors.reason" class="mt-1 text-sm text-red-500">{{ editForm.errors.reason }}</p>
			</div>

			<LeaveCoverSelect
				v-model="editForm.cover_employee_id"
				:options="editCoverOptions"
				:error="editForm.errors.cover_employee_id"
				:hint="ctrans('The cover gets this employee\'s permissions from approval until the leave ends')" />

			<div>
				<span class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Attachments") }}</span>
				<div v-if="selectedLeave.attachments?.length" class="mb-2 flex flex-wrap gap-1.5">
					<a
						v-for="attachment in selectedLeave.attachments"
						:key="attachment.id"
						:href="attachment.url"
						target="_blank"
						rel="noopener"
						class="inline-flex items-center gap-1 rounded bg-[--app-accent-soft] px-2 py-0.5 text-xs font-medium text-[--app-accent-strong] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]">
						<FontAwesomeIcon :icon="faPaperclip" fixed-width aria-hidden="true" />
						{{ attachment.name }}
					</a>
				</div>
				<FileUpload
					ref="editFileUpload"
					multiple
					customUpload
					accept=".pdf,.jpg,.jpeg,.png"
					:maxFileSize="maxAttachmentSize"
					:fileLimit="maxEditAttachments"
					:pt="fileUploadPt"
					@select="onEditAttachmentsChanged"
					@remove="onEditAttachmentsChanged">
					<template #header="{ chooseCallback }">
						<Button type="tertiary" :label="ctrans('Choose file')" @click.prevent="chooseCallback">
							<template #icon>
								<FontAwesomeIcon :icon="faPaperclip" class="text-[--app-accent]" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</template>
					<template #content="{ files, removeFileCallback }">
						<ul v-if="files.length" class="divide-y divide-gray-100 rounded-lg border border-gray-200">
							<li v-for="(file, index) in files" :key="file.name + file.size" class="flex items-center gap-2 px-3 py-2 text-sm">
								<FontAwesomeIcon :icon="faFileAlt" class="text-gray-400" fixed-width aria-hidden="true" />
								<span class="truncate text-gray-900">{{ file.name }}</span>
								<span class="ml-auto shrink-0 text-xs tabular-nums text-gray-500">{{ formatFileSize(file.size) }}</span>
								<button type="button" class="shrink-0 text-gray-400 hover:text-red-500" :aria-label="ctrans('Remove')" @click="removeFileCallback(index)">
									<FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
								</button>
							</li>
						</ul>
					</template>
					<template #empty>
						<p class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500">
							{{ ctrans("Drop files here, or use the button above. PDF, JPG or PNG, up to :count files of 5 MB each. New files replace the existing attachments.", { count: maxEditAttachments }) }}
						</p>
					</template>
				</FileUpload>
				<p v-if="editForm.errors.attachments" class="mt-1 text-sm text-red-500">{{ editForm.errors.attachments }}</p>
			</div>

			<div class="flex justify-end gap-2 pt-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click.prevent="closeEditModal">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" nativeType="submit" :label="ctrans('Save')" :loading="isSubmitting">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>

	<Dialog
		:visible="isExportModalOpen"
		modal
		:draggable="false"
		:header="ctrans('Export Leave Reports')"
		:style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }"
		@update:visible="(isVisible: boolean) => !isVisible && closeExportModal()">
		<p class="mb-4 text-sm text-gray-600">
			{{ ctrans("Select filters and export format for your leave report.") }}
		</p>

		<form class="space-y-4" @submit.prevent="submitExport">
			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<label for="leave-export-from" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("From Date") }}</label>
					<DatePicker
						v-model="exportFromDateModel"
						inputId="leave-export-from"
						:maxDate="exportToDateModel ?? undefined"
						dateFormat="d M yy"
						:manualInput="false"
						showIcon
						iconDisplay="input"
						showClear
						fluid
						:class="fieldFocusClass" />
				</div>
				<div>
					<label for="leave-export-to" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("To Date") }}</label>
					<DatePicker
						v-model="exportToDateModel"
						inputId="leave-export-to"
						:minDate="exportFromDateModel ?? undefined"
						dateFormat="d M yy"
						:manualInput="false"
						showIcon
						iconDisplay="input"
						showClear
						fluid
						:class="fieldFocusClass" />
				</div>
			</div>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<label for="leave-export-type" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Leave Type") }}</label>
					<Select
						v-model="exportForm.type"
						inputId="leave-export-type"
						:options="[{ value: '', label: ctrans('All Types') }, ...parsedTypeOptions]"
						optionLabel="label"
						optionValue="value"
						fluid
						:class="fieldFocusClass" />
				</div>
				<div>
					<label for="leave-export-status" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Status") }}</label>
					<Select
						v-model="exportForm.status"
						inputId="leave-export-status"
						:options="[{ value: '', label: ctrans('All Statuses') }, ...exportStatusOptions]"
						optionLabel="label"
						optionValue="value"
						fluid
						:class="fieldFocusClass" />
				</div>
			</div>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<label for="leave-export-department" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Team department") }}</label>
					<Select
						v-model="exportForm.department"
						inputId="leave-export-department"
						:options="[{ value: '', label: ctrans('All Departments') }, ...exportDepartmentOptions]"
						optionLabel="label"
						optionValue="value"
						fluid
						:class="fieldFocusClass" />
				</div>
				<div>
					<label for="leave-export-employee" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Employee") }}</label>
					<Select
						v-model="exportForm.employee_id"
						inputId="leave-export-employee"
						:options="[{ value: null, label: ctrans('All Employees') }, ...exportEmployeeOptions]"
						optionLabel="label"
						optionValue="value"
						filter
						fluid
						:class="fieldFocusClass" />
				</div>
			</div>

			<div>
				<span class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Export Format") }}</span>
				<div class="mt-2 flex gap-4">
					<label v-for="formatOption in exportFormatOptions" :key="formatOption.value" class="flex cursor-pointer items-center gap-2">
						<RadioButton v-model="exportForm.format" :value="formatOption.value" :inputId="`leave-export-format-${formatOption.value}`" />
						<FontAwesomeIcon :icon="formatOption.icon" :class="formatOption.iconClass" fixed-width aria-hidden="true" />
						<span class="text-sm">{{ formatOption.label }}</span>
					</label>
				</div>
			</div>

			<div class="flex justify-end gap-2 pt-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click.prevent="closeExportModal">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" nativeType="submit" :label="ctrans('Export')" :loading="isExporting">
					<template #icon>
						<FontAwesomeIcon :icon="faDownload" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>

	<Dialog
		:visible="isApproveModalOpen"
		modal
		:draggable="false"
		:header="ctrans('Approve leave')"
		:style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
		@update:visible="(isVisible: boolean) => !isVisible && closeApproveModal()">
		<form v-if="selectedLeave" class="space-y-4" @submit.prevent="submitApprove">
			<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
				<div class="font-medium text-gray-900">{{ selectedLeave.employee_name }}</div>
				<div class="tabular-nums text-gray-600">
					{{ selectedLeave.type_label }}
					<span class="text-gray-300">Â·</span>
					{{ formatDate(selectedLeave.start_date) }} – {{ formatDate(selectedLeave.end_date) }}
				</div>
			</div>

			<LeaveCoverSelect
				v-model="approveForm.cover_employee_id"
				:options="approveCoverOptions"
				:error="approveForm.errors.cover_employee_id"
				:hint="ctrans('The cover gets this employee\'s permissions from approval until the leave ends')" />

			<p v-if="(approveForm.errors as Record<string, string>).status" class="text-sm text-red-500">
				{{ (approveForm.errors as Record<string, string>).status }}
			</p>

			<div class="flex justify-end gap-2 pt-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click.prevent="closeApproveModal">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" nativeType="submit" :label="ctrans('Yes, approve')" :loading="approveForm.processing">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>

	<RejectLeaveModal
		:isOpen="isRejectModalOpen"
		:leave="selectedLeave"
		:route="{
			name: 'grp.org.hr.leaves.reject',
			parameters: { ...route().params, leave: selectedLeave?.id },
		}"
		@close="closeRejectModal" />

	<HrConfirmDialog group="leave-admin" />
</template>
