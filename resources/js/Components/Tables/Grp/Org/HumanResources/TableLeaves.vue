<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { useForm, usePage } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Tag from "@/Components/Tag.vue"
import Modal from "@/Components/Utils/Modal.vue"
import AlertMessage from "@/Components/Utils/AlertMessage.vue"
import { faEdit, faCalendarCheck, faMedkit, faCalendarTimes, faClock } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import DatePicker from "@vuepic/vue-datepicker"
import "@vuepic/vue-datepicker/dist/main.css"

library.add(faEdit, faCalendarCheck, faMedkit, faCalendarTimes, faClock)

type LeaveBalanceSummary = {
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

const props = defineProps<{
	data: {}
	tab?: string
	organisation?: string | null
	balance?: LeaveBalanceSummary | { data?: LeaveBalanceSummary }
	typeOptions?: Record<string, string> | null
	annualSubmittedDays?: number | null
	annualRemainingAfterSubmission?: number | null
	medicalRequestCount?: number | null
	unpaidRequestCount?: number | null
	isRequestLeaveModalOpen: boolean
}>()

const emit = defineEmits<{
	(e: "update:isRequestLeaveModalOpen", value: boolean): void
}>()

const page = usePage()
const errors = computed(() => page.props.errors as Record<string, string>)
const hasErrors = computed(() => Object.keys(errors.value).length > 0)

const locale = useLocaleStore()
const isEditModalOpen = ref(false)
const selectedLeave = ref<any>(null)
const isSubmitting = ref(false)

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

const normalizedBalance = computed<LeaveBalanceSummary | null>(() => {
	if (!props.balance || typeof props.balance !== "object") {
		return null
	}

	if ("data" in props.balance && props.balance.data && typeof props.balance.data === "object") {
		return props.balance.data
	}

	return props.balance as LeaveBalanceSummary
})

const balanceSummary = computed(() => {
	if (!normalizedBalance.value) {
		return null
	}

	const annualDays = toNumber(normalizedBalance.value.annual_days)
	const annualUsed = toNumber(normalizedBalance.value.annual_used)
	const annualRemainingRaw = toNumber(normalizedBalance.value.annual_remaining)

	const medicalDays = toNumber(normalizedBalance.value.medical_days)
	const medicalUsed = toNumber(normalizedBalance.value.medical_used)
	const medicalRemainingRaw = toNumber(normalizedBalance.value.medical_remaining)

	const unpaidDays = toNumber(normalizedBalance.value.unpaid_days)
	const unpaidUsed = toNumber(normalizedBalance.value.unpaid_used)
	const unpaidRemainingRaw = toNumber(normalizedBalance.value.unpaid_remaining)

	return {
		annual_days: annualDays,
		annual_used: annualUsed,
		annual_remaining:
			annualRemainingRaw > 0 || annualDays === 0
				? annualRemainingRaw
				: Math.max(0, annualDays - annualUsed),
		medical_days: medicalDays,
		medical_used: medicalUsed,
		medical_remaining:
			medicalRemainingRaw > 0 || medicalDays === 0
				? medicalRemainingRaw
				: Math.max(0, medicalDays - medicalUsed),
		unpaid_days: unpaidDays,
		unpaid_used: unpaidUsed,
		unpaid_remaining:
			unpaidRemainingRaw > 0 || unpaidDays === 0
				? unpaidRemainingRaw
				: Math.max(0, unpaidDays - unpaidUsed),
	}
})

const displayedMedicalCount = computed(() => {
	if (
		typeof props.medicalRequestCount === "number" &&
		Number.isFinite(props.medicalRequestCount)
	) {
		return props.medicalRequestCount
	}

	return balanceSummary.value?.medical_used ?? 0
})

const annualSubmitted = computed(() => {
	if (
		typeof props.annualSubmittedDays === "number" &&
		Number.isFinite(props.annualSubmittedDays)
	) {
		return props.annualSubmittedDays
	}

	return balanceSummary.value?.annual_used ?? 0
})

const annualRemaining = computed(() => {
	if (
		typeof props.annualRemainingAfterSubmission === "number" &&
		Number.isFinite(props.annualRemainingAfterSubmission)
	) {
		return props.annualRemainingAfterSubmission
	}

	return balanceSummary.value?.annual_remaining ?? 0
})

const displayedUnpaidCount = computed(() => {
	if (typeof props.unpaidRequestCount === "number" && Number.isFinite(props.unpaidRequestCount)) {
		return props.unpaidRequestCount
	}

	return balanceSummary.value?.unpaid_used ?? 0
})

const fallbackTypeOptions: Record<string, { label: string; category?: string }> = {
	annual: { label: "Annual Leave", category: "annual" },
	personal: { label: "Personal Leave", category: "personal" },
	medical: { label: "Medical Leave", category: "medical" },
	special: { label: "Special Leave", category: "special" },
}

const isHalfDayLeave = (leave: any): boolean => {
	const leaveTypeValue =
		typeof leave?.leave_type_value === "number" ? leave.leave_type_value : 1

	return leaveTypeValue === 0.5
}

const typeOptions = computed(() => {
	const options =
		props.typeOptions && Object.keys(props.typeOptions).length > 0
			? props.typeOptions
			: fallbackTypeOptions

	return Object.entries(options).map(([value, data]) => ({
		value,
		label: typeof data === "string" ? data : data.label,
		category: typeof data === "string" ? null : data.category,
		max_days_per_year:
			typeof data === "object" && "max_days_per_year" in data ? data.max_days_per_year : null,
		leave_type_value: typeof data === "object" && "value" in data ? toNumber(data.value) : 1,
	}))
})

const disabledWeekends = (date: Date): boolean => {
	const day = date.getDay()
	return day === 0 || day === 6
}

const leaveForm = useForm<{
	type: string
	start_date: string
	end_date: string
	is_half_day: boolean
	session: "Morning" | "Afternoon" | "Full"
	reason: string
	attachments: File[]
}>({
	type: "",
	start_date: "",
	end_date: "",
	is_half_day: false,
	session: "Full",
	reason: "",
	attachments: [],
})

watch(
	typeOptions,
	(options) => {
		const hasSelectedType = options.some((option) => option.value === leaveForm.type)
		if (!hasSelectedType) {
			leaveForm.type = options[0]?.value ?? ""
		}
	},
	{ immediate: true }
)

const editForm = useForm<{
	attachments: File[]
}>({
	attachments: [],
})

const isMedicalType = computed(() => {
	const selectedOption = typeOptions.value.find((opt) => opt.value === leaveForm.type)
	return selectedOption?.category === "medical"
})

const selectedLeaveType = computed(() => {
	return typeOptions.value.find((opt) => opt.value === leaveForm.type)
})

const maxDaysPerYear = computed(() => {
	return selectedLeaveType.value?.max_days_per_year ?? null
})

const selectedLeaveTypeValue = computed(() => {
	return toNumber(selectedLeaveType.value?.leave_type_value ?? 1)
})

const calculateBusinessDays = (startDate: string, endDate: string): number => {
	if (!startDate || !endDate) return 0

	const start = new Date(startDate)
	const end = new Date(endDate)
	let days = 0
	let current = new Date(start)

	while (current <= end) {
		const day = current.getDay()
		if (day !== 0 && day !== 6) {
			days++
		}
		current.setDate(current.getDate() + 1)
	}

	return days
}

const exceedsLimit = computed(() => {
	if (!maxDaysPerYear.value || !leaveForm.start_date || !leaveForm.end_date) {
		return false
	}

	const selectedDays = calculateBusinessDays(leaveForm.start_date, leaveForm.end_date)

	return selectedDays > maxDaysPerYear.value
})

const selectedLeaveBucket = computed(() => {
	const selectedCategory = selectedLeaveType.value?.category
	const normalizedCategory =
		typeof selectedCategory === "string"
			? selectedCategory.toLowerCase()
			: typeof selectedCategory?.value === "string"
				? selectedCategory.value.toLowerCase()
				: ""

	if (normalizedCategory === "annual" || normalizedCategory === "medical" || normalizedCategory === "unpaid") {
		return normalizedCategory
	}

	const typeCode = typeof leaveForm.type === "string" ? leaveForm.type.toLowerCase() : ""
	if (typeCode.includes("medical") || typeCode.includes("sick")) {
		return "medical"
	}
	if (typeCode.includes("unpaid")) {
		return "unpaid"
	}
	if (typeCode.includes("annual") || typeCode.includes("holiday")) {
		return "annual"
	}

	return null
})

const requestedLeaveDays = computed(() => {
	if (!leaveForm.start_date || !leaveForm.end_date) {
		return 0
	}

	const businessDays = leaveForm.is_half_day
		? 1
		: calculateBusinessDays(leaveForm.start_date, leaveForm.end_date)
	let requestedDays = businessDays * selectedLeaveTypeValue.value

	if (leaveForm.is_half_day && selectedLeaveTypeValue.value === 1) {
		requestedDays = 0.5
	}

	return requestedDays
})

const selectedBucketRemaining = computed(() => {
	if (!balanceSummary.value) {
		return Number.POSITIVE_INFINITY
	}

	switch (selectedLeaveBucket.value) {
		case "annual":
			return toNumber(annualRemaining.value)
		case "medical":
			return toNumber(balanceSummary.value.medical_remaining)
		case "unpaid":
			return toNumber(balanceSummary.value.unpaid_remaining)
		default:
			return Number.POSITIVE_INFINITY
	}
})

const canSubmitLeave = computed(() => {
	if (requestedLeaveDays.value <= 0) {
		return true
	}

	if (selectedLeaveBucket.value === "unpaid" || selectedLeaveBucket.value === "medical") {
		return true
	}

	return selectedBucketRemaining.value >= requestedLeaveDays.value
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

const formatDate = (date: string) => {
	return useFormatTime(date, { localeCode: locale.language.code })
}

const submitLeave = () => {
	isSubmitting.value = true
	leaveForm
		.transform((data) => ({
			...data,
			end_date: data.is_half_day && data.start_date ? data.start_date : data.end_date,
			organisation: props.organisation ?? undefined,
		}))
		.post(route("grp.clocking_employees.leaves.store"), {
			preserveScroll: true,
			forceFormData: true,
			onSuccess: () => {
				emit("update:isRequestLeaveModalOpen", false)
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
	emit("update:isRequestLeaveModalOpen", false)
	leaveForm.reset()
	leaveForm.is_half_day = false
	leaveForm.session = "Full"
}

// Watch for changes in leave type to reset half-day settings when switching between types
watch(
	() => leaveForm.type,
	() => {
		leaveForm.is_half_day = false
		leaveForm.session = "Full"
	}
)

watch(
	() => leaveForm.start_date,
	(newStartDate) => {
		if (leaveForm.is_half_day && newStartDate) {
			leaveForm.end_date = newStartDate
		}
	}
)

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
		<div v-if="balanceSummary" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
			<div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-gray-500">{{ trans("Annual Leave") }}</p>
						<p class="text-2xl font-bold text-blue-600">
							{{ annualRemaining }}
						</p>
						<p class="text-xs text-gray-400">
							{{
								trans(":submitted of :total days submitted", {
									submitted: annualSubmitted,
									total: balanceSummary.annual_days,
								})
							}}
						</p>
					</div>
					<div class="text-3xl text-blue-200">
						<FontAwesomeIcon icon="fal fa-calendar-check" />
					</div>
				</div>
			</div>

			<div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-gray-500">{{ trans("Medical Leave") }}</p>
						<p class="text-2xl font-bold text-red-600">
							{{ displayedMedicalCount }}
						</p>
						<p class="text-xs text-gray-400">
							{{ trans("Days This Month") }}
						</p>
					</div>
					<div class="text-3xl text-red-200">
						<FontAwesomeIcon icon="fal fa-medkit" />
					</div>
				</div>
			</div>

			<div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-gray-500">{{ trans("Unpaid Leave") }}</p>
						<p class="text-2xl font-bold text-gray-600">
							{{ displayedUnpaidCount }}
						</p>
						<p class="text-xs text-gray-400">
							{{ trans("Days This Month") }}
						</p>
					</div>
					<div class="text-3xl text-gray-200">
						<FontAwesomeIcon icon="fal fa-calendar-times" />
					</div>
				</div>
			</div>
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

				<template #cell(duration)="{ item: leave }">
					<span class="whitespace-nowrap block sm:inline">
						<FontAwesomeIcon
							v-if="isHalfDayLeave(leave)"
							icon="fal fa-clock"
							class="mr-1 text-blue-500" />
						{{ isHalfDayLeave(leave) ? "Half Day" : "Full Day" }}
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
							class="mt-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900 animate:bounce">
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

				<template #cell(actions)="{ item: leave }">
					<div
						v-if="
							leave.status === 'pending' &&
							typeOptions.find((opt) => opt.value === leave.type)?.category ===
								'medical'
						">
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

		<Modal
			:isOpen="isRequestLeaveModalOpen"
			@onClose="closeCreateModal"
			width="w-full max-w-lg">
			<h2 class="text-lg font-semibold text-gray-800 mb-4">
				{{ trans("Request Leave") }}
			</h2>

			<AlertMessage
				v-if="hasErrors"
				:alert="{
					status: 'danger',
					title: trans('There was a problem with your request.'),
					description: Object.values(errors)[0],
				}"
				class="mb-4" />

			<form @submit.prevent="submitLeave" class="space-y-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Leave Type")
					}}</label>
					<select
						v-model="leaveForm.type"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
						<option value="" disabled>
							{{ trans("Please Select Your Leave Type!") }}
						</option>
						<option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
							{{ opt.label }}
						</option>
					</select>
					<p v-if="leaveForm.errors.type" class="mt-1 text-sm text-red-600">
						{{ leaveForm.errors.type }}
					</p>
					<p v-if="maxDaysPerYear" class="mt-1 text-sm text-gray-500">
						Maximum allowance: {{ maxDaysPerYear }} days
					</p>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700">{{
							trans("Start Date")
						}}</label>
						<DatePicker
							:modelValue="
								leaveForm.start_date ? new Date(leaveForm.start_date) : null
							"
							@update:modelValue="
								(date: Date) =>
									(leaveForm.start_date = date
										? date.toISOString().split('T')[0]
										: '')
							"
							:disabledDates="disabledWeekends"
							:enableTimePicker="false"
							:clearable="true"
							:autoApply="true"
							:placeholder="trans('Select start date')"
							class="mt-1 block w-full"
							inputClassName="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
						<p v-if="leaveForm.errors.start_date" class="mt-1 text-sm text-red-600">
							{{ leaveForm.errors.start_date }}
						</p>
					</div>

					<div>
						<label class="block text-sm font-medium text-gray-700">{{
							trans("End Date")
						}}</label>
						<DatePicker
							:modelValue="leaveForm.end_date ? new Date(leaveForm.end_date) : null"
							@update:modelValue="
								(date: Date) =>
									(leaveForm.end_date = date
										? date.toISOString().split('T')[0]
										: '')
							"
							:disabled="leaveForm.is_half_day"
							:disabledDates="disabledWeekends"
							:enableTimePicker="false"
							:clearable="true"
							:autoApply="true"
							:placeholder="trans('Select end date')"
							class="mt-1 block w-full"
							inputClassName="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
						<p v-if="leaveForm.errors.end_date" class="mt-1 text-sm text-red-600">
							{{ leaveForm.errors.end_date }}
						</p>
					</div>
				</div>

				<p v-if="exceedsLimit" class="text-sm text-red-600">
					Selected dates ({{
						calculateBusinessDays(leaveForm.start_date, leaveForm.end_date)
					}}
					days) exceed the maximum allowance of {{ maxDaysPerYear }} days
				</p>

				<p v-if="leaveForm.is_half_day" class="text-xs text-gray-500">
					{{ trans("Half day leave is applied to one date only.") }}
				</p>

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

				<div v-if="isMedicalType">
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

				<p v-if="!canSubmitLeave" class="text-sm text-red-600">
					{{ trans("Sorry, your leave balance isn’t enough for this request") }}
				</p>

				<div class="mt-6 flex justify-end gap-2">
					<Button
						@click="closeCreateModal"
						:label="trans('Cancel')"
						type="tertiary"
						nativeType="button" />
					<Button
						type="save"
						nativeType="submit"
						:label="trans('Submit Request')"
						:loading="isSubmitting"
						:disabled="!canSubmitLeave || exceedsLimit" />
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
					<Button
						@click="closeEditModal"
						:label="trans('Cancel')"
						type="tertiary"
						nativeType="button" />
					<Button
						type="save"
						nativeType="submit"
						:label="trans('Save')"
						:loading="isSubmitting" />
				</div>
			</form>
		</Modal>
	</div>
</template>
