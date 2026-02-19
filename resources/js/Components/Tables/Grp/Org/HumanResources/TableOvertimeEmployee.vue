<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Tag from "@/Components/Tag.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"

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

const openModal = () => {
	overtimeForm.reset()
	overtimeForm.clearErrors()
	overtimeForm.start_hour = "00"
	overtimeForm.start_minute = "00"
	overtimeForm.duration_hours = "0"
	overtimeForm.duration_minutes = "0"
	emit("update:isRequestOvertimeModalOpen", true)
}

const closeModal = () => {
	emit("update:isRequestOvertimeModalOpen", false)
	overtimeForm.reset()
	overtimeForm.clearErrors()
}

const submitOvertimeRequest = () => {
	overtimeForm
		.transform((data) => ({
			...data,
			organisation: props.organisation ?? undefined,
		}))
		.post(route("grp.clocking_employees.overtime_requests.store"), {
			preserveScroll: true,
			onSuccess: () => {
				closeModal()
			},
		})
}
</script>

<template>
	<div class="mt-4 space-y-4">
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
		</Table>

		<Modal
			:isOpen="isRequestOvertimeModalOpen"
			@onClose="closeModal"
			width="w-full max-w-lg">
			<h2 class="text-lg font-semibold text-gray-800 mb-4 p-4">
				{{ trans("Create overtime request") }}
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
						:label="trans('Submit Request')"
						:loading="overtimeForm.processing" />
				</div>
			</form>
		</Modal>
	</div>
</template>
