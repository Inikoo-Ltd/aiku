<!--
  - Author: Oggie Sutrisna
  - Created: Thu, 09 Apr 2026 09:00:00 Singapore Standard Time, Singapore
  - Copyright (c) 2026
-->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import DatePicker from "primevue/datepicker"
import { trans } from "laravel-vue-i18n"
import type { routeType } from "@/types/route"
import { format } from "date-fns"

const props = defineProps<{
	isOpen: boolean
	submitRoute: routeType
	timesheetDate: string
}>()

const emit = defineEmits<{
	(e: "onClose"): void
}>()

const isSubmitting = ref(false)
const clockedAtTime = ref<Date | null>(new Date())
const errorMsg = ref<string | null>(null)

const closeModal = () => {
	emit("onClose")
}

const submitClockOut = () => {
	if (!clockedAtTime.value) {
		errorMsg.value = trans("Please select a time.")
		return
	}

	errorMsg.value = null
	isSubmitting.value = true

	// Standardize time formatting
	const timeString = format(clockedAtTime.value, "HH:mm:ss")

	const body = props.submitRoute.body ?? {}
	body.time = timeString

	router.post(route(props.submitRoute.name, props.submitRoute.parameters), body, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => {
			isSubmitting.value = false
			closeModal()
		},
		onError: (errors) => {
			isSubmitting.value = false
			errorMsg.value = errors.timesheet || trans("An error occurred while clocking out.")
		},
	})
}
</script>

<template>
	<Modal :isOpen="isOpen" @onClose="closeModal" width="w-full max-w-md">
		<h2 class="text-lg font-semibold text-gray-800 mb-4">
			{{ trans("Manual Clock Out") }}
		</h2>

		<form @submit.prevent="submitClockOut" class="space-y-4">
			<div class="space-y-4">
				<p class="text-sm text-gray-500">
					{{ trans("Please provide the time for the clock out.") }}
					<br />
					<span class="font-medium text-indigo-600">{{ trans("Target Date") }}: {{ timesheetDate }}</span>
				</p>

				<div>
					<label class="block text-sm font-medium text-gray-700">
						{{ trans("Clock Out Time") }}
					</label>
					<DatePicker
						v-model="clockedAtTime"
						timeOnly
						showSeconds
						hourFormat="24"
						showIcon
						fluid
						class="mt-1" />
                    <p class="mt-1 text-xs text-gray-400 italic">
                        {{ trans("This will record the clock out on the target date above.") }}
                    </p>
				</div>

				<p v-if="errorMsg" class="text-sm text-red-600">
					{{ errorMsg }}
				</p>
			</div>

			<div class="flex justify-end space-x-3 pt-2">
				<Button
					type="secondary"
					:label="trans('Cancel')"
					:disabled="isSubmitting"
					@click="closeModal" />
				<Button
					type="primary"
					:label="isSubmitting ? trans('Saving...') : trans('Confirm')"
					:disabled="isSubmitting || !clockedAtTime"
					nativeType="submit" />
			</div>
		</form>
	</Modal>
</template>