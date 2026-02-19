<script setup lang="ts">
import { useForm, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Tag from "@/Components/Tag.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { ref, computed } from "vue"

import { library } from "@fortawesome/fontawesome-svg-core";
import { faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup } from "@fal";

library.add(faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup)

const props = defineProps<{
	data: {}
	tab?: string
	organisation?: string | null
	overtimeTypeOptions?: { value: number; label: string }[]
	isRequestOvertimeModalOpen?: boolean
}>()

const emit = defineEmits<{
	(e: "update:isRequestOvertimeModalOpen", value: boolean): void
}>()

const isEditMode = ref(false)
const editingOvertimeId = ref<number | null>(null)

const filterSpecificDate = ref<string>("")
const filterMonth = ref<string>("")
const filterYear = ref<string>("")

const monthOptions = computed(() =>
	Array.from({ length: 12 }, (_, index) => {
		const date = new Date(2000, index, 1)
		return {
			value: index + 1,
			label: date.toLocaleString("default", { month: "long" }),
		}
	})
)

const yearOptions = computed(() => {
	const currentYear = new Date().getFullYear()
	return Array.from({ length: 5 }, (_, index) => {
		return {
			value: currentYear - 2 + index,
			label: String(currentYear - 2 + index),
		}
	})
})

const initializeFiltersFromUrl = () => {
	const url = new URL(window.location.href)

	const specificDate = url.searchParams.get("requested_date")
	const month = url.searchParams.get("month")
	const year = url.searchParams.get("year")

	filterSpecificDate.value = specificDate ?? ""
	filterMonth.value = month ?? ""
	filterYear.value = year ?? ""
}

initializeFiltersFromUrl()

const applyDateFilters = () => {
	const params: Record<string, unknown> = {
		...route().params,
		tab: props.tab ?? "overtime",
	}

	if (filterSpecificDate.value) {
		params.requested_date = filterSpecificDate.value
		params.month = undefined
		params.year = undefined
	} else {
		if (filterMonth.value) {
			params.month = filterMonth.value
		}

		if (filterYear.value) {
			params.year = filterYear.value
		}

		params.requested_date = undefined
	}

	router.get(route("grp.clocking_employees.index", params), {}, {
		preserveState: true,
		preserveScroll: true,
	})
}

const resetDateFilters = () => {
	filterSpecificDate.value = ""
	filterMonth.value = ""
	filterYear.value = ""

	const params: Record<string, unknown> = {
		...route().params,
		tab: props.tab ?? "overtime",
	}

	params.requested_date = undefined
	params.month = undefined
	params.year = undefined

	router.get(route("grp.clocking_employees.index", params), {}, {
		preserveState: true,
		preserveScroll: true,
	})
}

const overtimeForm = useForm<{
	overtime_type_id: number | null
	requested_date: string
	start_hour: string
	start_minute: string
	duration_hours: string
	duration_minutes: string
	reason: string
}>({
	overtime_type_id: null,
	requested_date: "",
	start_hour: "00",
	start_minute: "00",
	duration_hours: "0",
	duration_minutes: "0",
	reason: "",
})

const hourOptions = Array.from({ length: 24 }, (_, index) =>
	index < 10 ? `0${index}` : `${index}`
)

const minuteOptions = Array.from({ length: 60 }, (_, index) =>
	index < 10 ? `0${index}` : `${index}`
)

const formatDuration = (minutes?: number | null): string => {
	if (!minutes) {
		return "-"
	}

	const hours = Math.floor(minutes / 60)
	const remainingMinutes = minutes % 60

	if (hours && remainingMinutes) {
		return `${hours}h ${remainingMinutes}m`
	}

	if (hours) {
		return `${hours}h`
	}

	return `${remainingMinutes}m`
}

const extractTimeParts = (value?: string | null): { hour: string; minute: string } => {
	if (!value) {
		return { hour: "00", minute: "00" }
	}

	const date = new Date(value)

	if (!Number.isNaN(date.getTime())) {
		const hour = date.getHours()
		const minute = date.getMinutes()
		return {
			hour: hour < 10 ? `0${hour}` : String(hour),
			minute: minute < 10 ? `0${minute}` : String(minute),
		}
	}

	const timePart = value.split(" ")[1] ?? value
	const [hour, minute] = timePart.split(":")

	return {
		hour: hour?.padStart(2, "0") ?? "00",
		minute: minute?.padStart(2, "0") ?? "00",
	}
}

const resetFormState = () => {
	overtimeForm.reset()
	overtimeForm.clearErrors()
	overtimeForm.start_hour = "00"
	overtimeForm.start_minute = "00"
	overtimeForm.duration_hours = "0"
	overtimeForm.duration_minutes = "0"
}

const openModal = () => {
	isEditMode.value = false
	editingOvertimeId.value = null
	resetFormState()
	emit("update:isRequestOvertimeModalOpen", true)
}

const openEditModal = (item: any) => {
	isEditMode.value = true
	editingOvertimeId.value = item.id

	resetFormState()
	overtimeForm.clearErrors()

	overtimeForm.overtime_type_id = item.overtime_type_id ?? null
	overtimeForm.requested_date = item.requested_date ?? ""

	const { hour, minute } = extractTimeParts(item.requested_start_at)
	overtimeForm.start_hour = hour
	overtimeForm.start_minute = minute

	const durationMinutes = item.requested_duration_minutes ?? 0
	const hours = Math.floor(durationMinutes / 60)
	const minutes = durationMinutes % 60
	overtimeForm.duration_hours = String(hours)
	overtimeForm.duration_minutes = String(minutes)

	overtimeForm.reason = item.reason ?? ""
	emit("update:isRequestOvertimeModalOpen", true)
}

const closeModal = () => {
	emit("update:isRequestOvertimeModalOpen", false)
	isEditMode.value = false
	editingOvertimeId.value = null
	resetFormState()
}

const submitOvertimeRequest = () => {
	overtimeForm
		.transform((data) => ({
			...data,
			organisation: props.organisation ?? undefined,
		}))
		.post(
			isEditMode.value && editingOvertimeId.value
				? route("grp.clocking_employees.overtime_requests.update", {
						overtimeRequest: editingOvertimeId.value,
				  })
				: route("grp.clocking_employees.overtime_requests.store"),
			{
				preserveScroll: true,
				onSuccess: () => {
					closeModal()
				},
			}
		)
}
</script>

<template>
	<div class="mt-4 space-y-4">
		<div class="flex flex-col gap-3 px-4 md:flex-row md:items-center md:justify-between">
			<div class="flex flex-wrap items-center gap-2">
				<div class="flex items-center gap-2">
					<label class="text-xs font-medium text-gray-600">
						{{ trans("Date") }}
					</label>
					<input
						v-model="filterSpecificDate"
						type="date"
						class="block rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
				</div>

				<div class="flex items-center gap-2">
					<label class="text-xs font-medium text-gray-600">
						{{ trans("Month") }}
					</label>
					<select
						v-model="filterMonth"
						class="block rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 min-w-[7rem]">
						<option value="">
							{{ trans("All") }}
						</option>
						<option
							v-for="month in monthOptions"
							:key="month.value"
							:value="month.value">
							{{ month.label }}
						</option>
					</select>
				</div>

				<div class="flex items-center gap-2">
					<label class="text-xs font-medium text-gray-600">
						{{ trans("Year") }}
					</label>
					<select
						v-model="filterYear"
						class="block rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 min-w-[6rem]">
						<option value="">
							{{ trans("All") }}
						</option>
						<option
							v-for="year in yearOptions"
							:key="year.value"
							:value="year.value">
							{{ year.label }}
						</option>
					</select>
				</div>

				<Button
					@click="applyDateFilters"
					size="xs"
					type="secondary"
					:label="trans('Filter')" />

				<Button
					@click="resetDateFilters"
					size="xs"
					type="tertiary"
					:label="trans('Reset')" />
			</div>

		</div>

		<div class="flex justify-end px-4">
			<Button
				@click="openModal"
				:label="trans('New overtime request')"
				icon="fal fa-plus"
				type="create" />
		</div>

		<Table :resource="data" :name="tab">
			<template #cell(requested_date)="{ item }">
				<span class="whitespace-nowrap">
					{{ useFormatTime(item.requested_date) }}
				</span>
			</template>

			<template #cell(overtime_type_name)="{ item }">
				<span class="whitespace-nowrap">
					{{ item.overtime_type_name }}
				</span>
			</template>

			<template #cell(requested_start_at)="{ item }">
				<span class="whitespace-nowrap">
					{{ useFormatTime(item.requested_start_at, { formatTime: "hm" }) }}
				</span>
			</template>

			<template #cell(requested_duration_minutes)="{ item }">
				<span class="whitespace-nowrap">
					{{ formatDuration(item.requested_duration_minutes) }}
				</span>
			</template>

			<template #cell(lieu_requested_minutes)="{ item }">
				<span class="whitespace-nowrap">
					{{ formatDuration(item.lieu_requested_minutes) }}
				</span>
			</template>

			<template #cell(status)="{ item }">
				<Tag
					:theme="
						item.status === 'approved'
							? 3
							: item.status === 'pending'
								? 1
								: item.status === 'rejected'
									? 7
									: 99
					"
					size="xs"
					:label="item.status">
					<template #label>
						<span class="capitalize">
							{{ item.status }}
						</span>
					</template>
				</Tag>
			</template>

			<template #cell(reason)="{ item }">
				<span class="whitespace-nowrap">
					{{ item.reason ?? "—" }}
				</span>
			</template>

			<template #cell(actions)="{ item }">
				<div v-if="item.status === 'pending'">
					<Button
						@click="openEditModal(item)"
						size="xs"
						type="transparent"
						icon="fal fa-edit" />
				</div>
			</template>
		</Table>

		<Modal
			:isOpen="isRequestOvertimeModalOpen"
			@onClose="closeModal"
			width="w-full max-w-lg">
			<h2 class="text-lg font-semibold text-gray-800 mb-4 p-4">
				{{
					isEditMode
						? trans("Edit overtime request")
						: trans("Create overtime request")
				}}
			</h2>

			<form class="space-y-4" @submit.prevent="submitOvertimeRequest">
				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("What type of overtime do you want to submit?") }}
					</label>
					<select
						v-model="overtimeForm.overtime_type_id"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
						<option :value="null" disabled>
							{{ trans("Select overtime type") }}
						</option>
						<option
							v-for="overtimeType in overtimeTypeOptions ?? []"
							:key="overtimeType.value"
							:value="overtimeType.value">
							{{ overtimeType.label }}
						</option>
					</select>
					<div v-if="overtimeForm.errors.overtime_type_id" class="mt-1 text-sm text-red-600">
						{{ overtimeForm.errors.overtime_type_id }}
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("What date did the overtime occur?") }}
					</label>
					<input
						v-model="overtimeForm.requested_date"
						type="date"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
					<div v-if="overtimeForm.errors.requested_date" class="mt-1 text-sm text-red-600">
						{{ overtimeForm.errors.requested_date }}
					</div>
				</div>

				<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700">
							{{ trans("What time did it start?") }}
						</label>
						<div class="mt-1 flex gap-2">
							<select
								v-model="overtimeForm.start_hour"
								class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
								<option v-for="hour in hourOptions" :key="hour" :value="hour">
									{{ hour }}
								</option>
							</select>
							<select
								v-model="overtimeForm.start_minute"
								class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
								<option v-for="minute in minuteOptions" :key="minute" :value="minute">
									{{ minute }}
								</option>
							</select>
						</div>
					</div>

					<div>
						<label class="block text-sm font-medium text-gray-700">
							{{ trans("How long was the overtime?") }}
						</label>
						<div class="mt-1 flex gap-2">
							<select
								v-model="overtimeForm.duration_hours"
								class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
								<option v-for="hour in Array.from({ length: 13 }, (_, index) => String(index))" :key="hour" :value="hour">
									{{ hour }}
								</option>
							</select>
							<select
								v-model="overtimeForm.duration_minutes"
								class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
								<option v-for="minute in ['0', '15', '30', '45']" :key="minute" :value="minute">
									{{ minute }}
								</option>
							</select>
						</div>
						<div v-if="overtimeForm.errors.requested_duration_minutes" class="mt-1 text-sm text-red-600">
							{{ overtimeForm.errors.requested_duration_minutes }}
						</div>
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Why did you work overtime?") }}
					</label>
					<textarea
						v-model="overtimeForm.reason"
						rows="3"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
						:placeholder="trans('Please provide a reason for your overtime request')" />
					<div v-if="overtimeForm.errors.reason" class="mt-1 text-sm text-red-600">
						{{ overtimeForm.errors.reason }}
					</div>
				</div>

				<div class="mt-6 flex justify-end gap-2">
					<Button @click="closeModal" :label="trans('Cancel')" type="tertiary" />
					<Button
						type="save"
						nativeType="submit"
						:label="isEditMode ? trans('Update Request') : trans('Submit Request')"
						:loading="overtimeForm.processing" />
				</div>
			</form>
		</Modal>
	</div>
</template>
