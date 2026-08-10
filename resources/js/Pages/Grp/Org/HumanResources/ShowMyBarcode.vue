<script setup lang="ts">
import { computed, onMounted, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import JsBarcode from "jsbarcode"

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
	<div class="space-y-3 text-center sm:space-y-4">
		<p class="text-xs sm:text-sm text-gray-500">
			{{ trans("Show this barcode to the scanner to clock in or out") }}
		</p>

		<div
			class="flex min-h-[120px] sm:min-h-[140px] w-full min-w-0 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-5 sm:px-4 sm:py-6">
			<div v-if="displayCode" class="w-full min-w-0 rounded-lg bg-white px-2 py-2">
				<svg id="employeeBarcode" class="mx-auto block h-auto max-w-full"></svg>
			</div>
			<span v-else class="text-xs sm:text-sm text-amber-700">
				{{ trans("No barcode has been generated for you yet. Please contact HR.") }}
			</span>
		</div>
	</div>
</template>
