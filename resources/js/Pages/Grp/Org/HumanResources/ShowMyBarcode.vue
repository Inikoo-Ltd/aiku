<script setup lang="ts">
import { computed, onMounted, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import JsBarcode from "jsbarcode"
import ClockingStatusSummary from "@/Components/HumanResources/ClockingStatusSummary.vue"

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
// it for lookup, but the barcode only needs to encode the code itself - the scanner endpoint
// already scopes the lookup to this machine's organisation.
const displayCode = computed(() => props.pin?.replace(/^\d+:/, "") ?? null)

const renderBarcode = () => {
	if (!displayCode.value) return

	JsBarcode("#employeeBarcode", displayCode.value, {
		format: "CODE128",
		lineColor: "rgb(41 37 36)",
		width: 2,
		height: 90,
		displayValue: true,
		font: "monospace",
	})
}

onMounted(renderBarcode)
watch(displayCode, renderBarcode)
</script>

<template>
	<ClockingStatusSummary
		:active-time-tracker="props.activeTimeTracker"
		:clocking-status="props.clockingStatus"
		:today-timesheet="props.todayTimesheet"
		:clocking-sessions="props.clockingSessions"
		:timezone="props.timezone" />

	<div class="rounded-xl border border-gray-200 bg-white p-6 text-center space-y-3">
		<p class="text-sm text-gray-500">{{ trans("Show this barcode to the scanner to clock in or out") }}</p>

		<div v-if="displayCode" class="flex justify-center overflow-x-auto">
			<svg id="employeeBarcode" class="bg-white"></svg>
		</div>
		<div v-else class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
			{{ trans("No barcode has been generated for you yet. Please contact HR.") }}
		</div>
	</div>
</template>
