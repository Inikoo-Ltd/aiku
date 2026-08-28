<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import QrcodeVue from "qrcode.vue"

interface ClockingRecord {
	id: number
	clocked_at: string | null
	type: string | null
	is_late: boolean
	notes: string | null
}

interface ClockingSession {
	id: number
	sequence: number
	status: string | null
	is_open: boolean
	starts_at: string | null
	ends_at: string | null
	duration: number | null
	clock_in: ClockingRecord | null
	clock_out: ClockingRecord | null
}

const props = defineProps<{
	activeTimeTracker?: any
	clockingStatus?: string
	todayTimesheet?: any
	clockingSessions?: ClockingSession[]
	timezone?: string
	pin?: string | null
}>()

const displayCode = computed(() => props.pin?.replace(/^\d+:/, "") ?? null)
</script>

<template>
	<div class="space-y-3 text-center sm:space-y-4">
		<p class="text-xs sm:text-sm text-gray-500">
			{{ trans("Show this QR code to the camera to clock in or out") }}
		</p>

		<div
			class="flex min-h-[120px] sm:min-h-[140px] w-full min-w-0 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-5 sm:px-4 sm:py-6">
			<div v-if="displayCode" class="rounded-lg bg-white p-3">
				<QrcodeVue :value="displayCode" :size="180" level="H" />
			</div>
			<span v-else class="text-xs sm:text-sm text-amber-700">
				{{ trans("No QR code has been generated for you yet. Please contact HR.") }}
			</span>
		</div>
	</div>
</template>
