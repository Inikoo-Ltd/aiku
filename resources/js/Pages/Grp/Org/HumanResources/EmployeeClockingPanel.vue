<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faQrcode, faHashtag, faBarcode } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ScanQrUser from "./ScanQrUser.vue"
import ShowMyPin from "./ShowMyPin.vue"
import ShowMyBarcode from "./ShowMyBarcode.vue"
import ClockingStatusSummary from "@/Components/HumanResources/ClockingStatusSummary.vue"

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
	<div class="mx-auto w-full max-w-2xl p-3 sm:p-6 lg:w-1/2">
		<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
			<div class="border-b border-gray-200 px-4 py-4 text-center sm:px-5">
				<h2 class="text-base sm:text-lg md:text-xl font-bold text-gray-800">
					{{ trans("Employee Clocking") }}
				</h2>
				<p class="mt-0.5 text-[11px] sm:text-xs text-gray-500">
					{{ trans("Clock in or out with your preferred method") }}
				</p>
			</div>

			<div
				v-if="!methodOptions.length"
				class="px-4 py-10 text-center text-sm text-gray-500 sm:px-5">
				{{ trans("No clocking method is currently enabled for your organisation. Please contact HR.") }}
			</div>

			<template v-else>
				<ClockingStatusSummary
					class="border-b border-gray-200"
					:active-time-tracker="activeTimeTracker"
					:clocking-status="clockingStatus"
					:today-timesheet="todayTimesheet"
					:clocking-sessions="clockingSessions"
					:timezone="timezone" />

				<div class="px-4 pt-4 sm:px-5 sm:pt-5">
					<div class="flex w-full gap-1 rounded-xl bg-gray-100 p-1 sm:gap-1.5">
						<button
							v-for="option in methodOptions"
							:key="option.value"
							type="button"
							class="flex min-w-0 flex-1 basis-0 items-center justify-center gap-1.5 rounded-lg px-1.5 py-2.5 text-xs font-medium transition sm:gap-2 sm:px-2 sm:text-sm"
							:class="
								selectedMethod === option.value
									? 'bg-white text-indigo-700 shadow-sm'
									: 'text-gray-500 hover:text-gray-700'
							"
							@click="selectedMethod = option.value">
							<FontAwesomeIcon :icon="option.icon" class="w-4 shrink-0" />
							<span class="truncate">{{ option.label }}</span>
						</button>
					</div>
				</div>

				<div class="w-full min-w-0 px-4 py-4 sm:px-5 sm:py-5">
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
				</div>
			</template>
		</div>
	</div>
</template>
