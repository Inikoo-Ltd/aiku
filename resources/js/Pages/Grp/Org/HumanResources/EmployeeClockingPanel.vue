<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faQrcode, faHashtag, faBarcode } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ScanQrUser from "./ScanQrUser.vue"
import ShowMyPin from "./ShowMyPin.vue"
import ShowMyBarcode from "./ShowMyBarcode.vue"

library.add(faQrcode, faHashtag, faBarcode)

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
	lastClockIn?: any
	lastClockOut?: any
	clockingSessions?: ClockingSession[]
	timezone?: string
	availableMethods?: string[]
	pin?: string | null
}>()

const methodDefinitions: Record<string, { label: string; icon: any; component: any }> = {
	qr_code: { label: trans("QR Code"), icon: faQrcode, component: ScanQrUser },
	pin: { label: trans("PIN"), icon: faHashtag, component: ShowMyPin },
	barcode: { label: trans("Barcode"), icon: faBarcode, component: ShowMyBarcode },
}

const availableMethods = computed(() => props.availableMethods ?? [])

const methodOptions = computed(() =>
	availableMethods.value
		.filter((method) => method in methodDefinitions)
		.map((method) => ({ value: method, ...methodDefinitions[method] }))
)

const selectedMethod = ref<string | null>(methodOptions.value[0]?.value ?? null)

watch(methodOptions, (options) => {
	if (!options.some((option) => option.value === selectedMethod.value)) {
		selectedMethod.value = options[0]?.value ?? null
	}
})

const activeComponent = computed(() => {
	if (!selectedMethod.value) return null
	return methodDefinitions[selectedMethod.value]?.component ?? null
})
</script>

<template>
	<div class="max-w-lg mx-auto p-4 sm:p-6 sm:pb-6">
		<h2 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6">
			{{ trans("Employee Clocking") }}
		</h2>

		<div v-if="!methodOptions.length" class="text-center text-sm text-gray-500">
			{{ trans("No clocking method is currently enabled for your organisation. Please contact HR.") }}
		</div>

		<template v-else>
			<div v-if="methodOptions.length > 1" class="mb-5 sm:mb-6">
				<div class="flex w-full gap-2 rounded-lg bg-gray-100 p-1">
					<button
						v-for="option in methodOptions"
						:key="option.value"
						type="button"
						class="flex-1 flex items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-2.5 text-sm font-medium transition"
						:class="
							selectedMethod === option.value
								? 'bg-white text-indigo-700 shadow-sm'
								: 'text-gray-500 hover:text-gray-700'
						"
						@click="selectedMethod = option.value">
						<FontAwesomeIcon :icon="option.icon" />
						{{ option.label }}
					</button>
				</div>
			</div>

			<component
				:is="activeComponent"
				:active-time-tracker="activeTimeTracker"
				:clocking-status="clockingStatus"
				:today-timesheet="todayTimesheet"
				:last-clock-in="lastClockIn"
				:last-clock-out="lastClockOut"
				:clocking-sessions="clockingSessions"
				:timezone="timezone"
				:pin="pin" />
		</template>
	</div>
</template>
