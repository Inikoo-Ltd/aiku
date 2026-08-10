<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"

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

// The stored pin is prefixed with the organisation id (e.g. "5:YEY173") since the kiosk needs
// it for lookup, but the employee only ever needs to show/type the code itself.
const displayCode = computed(() => props.pin?.replace(/^\d+:/, "") ?? null)
</script>

<template>
	<div class="space-y-3 text-center sm:space-y-4">
		<p class="text-xs sm:text-sm text-gray-500">
			{{ trans("Enter this PIN on the clocking machine to clock in or out") }}
		</p>

		<div
			class="flex min-h-[120px] sm:min-h-[140px] w-full min-w-0 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-5 sm:px-4 sm:py-6">
			<span
				v-if="displayCode"
				class="break-all text-2xl sm:text-3xl font-bold tracking-widest text-gray-800">
				{{ displayCode }}
			</span>
			<span v-else class="text-xs sm:text-sm text-amber-700">
				{{ trans("No PIN has been generated for you yet. Please contact HR.") }}
			</span>
		</div>
	</div>
</template>
