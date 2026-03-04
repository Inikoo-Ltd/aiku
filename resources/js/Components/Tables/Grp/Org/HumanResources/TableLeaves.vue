<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Tag from "@/Components/Tag.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { faEdit, faCalendarCheck, faMedkit, faCalendarTimes, faClock } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

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
	annualSubmittedDays?: number | null
	annualRemainingAfterSubmission?: number | null
	medicalRequestCount?: number | null
	unpaidRequestCount?: number | null
	isRequestLeaveModalOpen: boolean
}>()

const emit = defineEmits<{
	(e: "update:isRequestLeaveModalOpen", value: boolean): void
}>()

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

const typeOptions = [
	{ value: "annual", label: trans("Annual Leave") },
	{ value: "medical", label: trans("Medical Leave") },
	{ value: "unpaid", label: trans("Unpaid Leave") },
]

const leaveForm = useForm<{
	type: string
	start_date: string
	end_date: string
	is_half_day: boolean
	session: "Morning" | "Afternoon" | "Full"
	reason: string
	attachments: File[]
}>({
	type: "annual",
	start_date: "",
	end_date: "",
	is_half_day: false,
	session: "Full",
	reason: "",
	attachments: [],
})

const editForm = useForm<{
	attachments: File[]
}>({
	attachments: [],
})

const canSubmitLeave = computed(() => {
	if (!balanceSummary.value) return true
	if (leaveForm.type === "annual") {
		return annualRemaining.value > 0
	}

	return true
})

const showLeaveDuration = computed(() => {
	return !["annual", "medical"].includes(leaveForm.type)
})

const leaveDurationOptions = [
	{ value: "full", label: trans("Full Day") },
	{ value: "half_morning", label: trans("Half Day (Morning)") },
	{ value: "half_afternoon", label: trans("Half Day (Afternoon)") },
]

const leaveDuration = computed({
	get: () => {
		if (!leaveForm.is_half_day) return "full"
		return leaveForm.session === "Afternoon" ? "half_afternoon" : "half_morning"
	},
	set: (value: string) => {
		if (value === "full") {
			leaveForm.is_half_day = false
			leaveForm.session = "Full"
			return
		}

		leaveForm.is_half_day = true
		leaveForm.session = value === "half_afternoon" ? "Afternoon" : "Morning"
		if (leaveForm.start_date) {
			leaveForm.end_date = leaveForm.start_date
		}
	},
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
	(newValue, oldValue) => {
		if (["annual", "medical"].includes(newValue)) {
			// Switching to annual/medical, ensure half-day settings are reset
			leaveForm.is_half_day = false
			leaveForm.session = "Full"
		}
	}
)

// Watch showLeaveDuration to debug
watch(showLeaveDuration, (newValue) => {
	console.log("showLeaveDuration is now:", newValue)
})

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
							{{ trans("Requests Submitted") }}
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
							{{ trans("requests submitted") }}
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
							v-if="leave.is_half_day"
							icon="fal fa-clock"
							class="mr-1 text-blue-500" />
						{{ leave.is_half_day ? `Half Day (${leave.session})` : "Full Day" }}
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

		<Modal
			:isOpen="isRequestLeaveModalOpen"
			@onClose="closeCreateModal"
			width="w-full max-w-lg">
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

				<div v-if="showLeaveDuration">
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Leave Duration")
					}}</label>
					<select
						v-model="leaveDuration"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
						<option
							v-for="opt in leaveDurationOptions"
							:key="opt.value"
							:value="opt.value">
							{{ opt.label }}
						</option>
					</select>
					<p v-if="leaveForm.is_half_day" class="mt-1 text-xs text-gray-500">
						{{ trans("Half day leave is applied to one date only.") }}
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
							:disabled="leaveForm.is_half_day"
							class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
						<p v-if="leaveForm.errors.end_date" class="mt-1 text-sm text-red-600">
							{{ leaveForm.errors.end_date }}
						</p>
					</div>
				</div>

				<div v-if="showLeaveDuration">
					<label class="block text-sm font-medium text-gray-700">{{
						trans("Leave Duration")
					}}</label>
					<select
						v-model="leaveDuration"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
						<option
							v-for="opt in leaveDurationOptions"
							:key="opt.value"
							:value="opt.value">
							{{ opt.label }}
						</option>
					</select>
					<p v-if="leaveForm.is_half_day" class="mt-1 text-xs text-gray-500">
						{{ trans("Half day leave is applied to one date only.") }}
					</p>
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

				<p
					v-if="leaveForm.type === 'annual' && !canSubmitLeave"
					class="text-sm text-red-600">
					{{ trans("Insufficient leave balance for the selected type.") }}
				</p>

				<div class="mt-6 flex justify-end gap-2">
					<Button @click="closeCreateModal" :label="trans('Cancel')" type="tertiary" />
					<Button
						type="save"
						nativeType="submit"
						:label="trans('Submit Request')"
						:loading="isSubmitting"
						:disabled="leaveForm.type === 'annual' && !canSubmitLeave" />
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
