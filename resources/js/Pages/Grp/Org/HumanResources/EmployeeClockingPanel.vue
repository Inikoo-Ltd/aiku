<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faQrcode, faHashtag, faBarcode, faCamera, faCheck } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Dialog } from "primevue"
import ScanQrUser from "./ScanQrUser.vue"
import ShowMyPin from "./ShowMyPin.vue"
import ShowMyBarcode from "./ShowMyBarcode.vue"
import ShowMyQr from "./ShowMyQr.vue"
import ClockingStatusSummary from "@/Components/HumanResources/ClockingStatusSummary.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useEchoEmployeeClocking } from "@/Stores/echo-employee-clocking"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faQrcode, faHashtag, faBarcode, faCamera, faCheck)

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
	camera_qr: { label: trans("Camera QR"), icon: faCamera, component: ShowMyQr },
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

const echoStore = useEchoEmployeeClocking()
const clockEventModalOpen = ref(false)
const clockEventResult = ref<{ actionType: "clock_in" | "clock_out"; clockedAt: string | null } | null>(null)
let clockEventAutoCloseTimer: ReturnType<typeof setTimeout> | null = null

const closeClockEventModal = () => {
	clockEventModalOpen.value = false
	if (clockEventAutoCloseTimer) {
		clearTimeout(clockEventAutoCloseTimer)
		clockEventAutoCloseTimer = null
	}
}

watch(
	() => echoStore.lastClockEvent,
	(event) => {
		if (!event) return

		clockEventResult.value = { actionType: event.actionType, clockedAt: event.clockedAt }
		clockEventModalOpen.value = true

		if (clockEventAutoCloseTimer) clearTimeout(clockEventAutoCloseTimer)
		clockEventAutoCloseTimer = setTimeout(() => {
			clockEventModalOpen.value = false
		}, 4000)
	}
)

onUnmounted(() => {
	if (clockEventAutoCloseTimer) clearTimeout(clockEventAutoCloseTimer)
})
</script>

<template>
	<div class="mx-auto w-full max-w-2xl p-3 sm:p-6 lg:w-1/2">
		<div class="overflow-hidden rounded-2xl sm:border sm:border-gray-200 bg-white shadow-sm">
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

				<div class="px-4 pt-2 sm:px-5 sm:pt-5">
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

		<Dialog
			v-model:visible="clockEventModalOpen"
			modal
			:closable="false"
			class="w-[95vw] max-w-[95vw] sm:w-[420px] sm:max-w-[420px]"
			appendTo="body">
			<div v-if="clockEventResult" class="text-center space-y-4 py-2 sm:py-4">
				<div class="flex justify-center">
					<div
						class="w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center bg-green-100">
						<FontAwesomeIcon :icon="faCheck" class="text-3xl sm:text-4xl text-green-600" />
					</div>
				</div>

				<h3 class="text-lg sm:text-xl font-semibold text-gray-800">
					{{
						clockEventResult.actionType === "clock_in"
							? trans("Clocked In")
							: trans("Clocked Out")
					}}
				</h3>

				<div
					v-if="clockEventResult.clockedAt"
					class="text-xs sm:text-sm text-gray-600 space-y-2 bg-gray-50 p-3 rounded-lg">
					<div class="flex justify-between gap-3 text-left">
						<span class="text-gray-500">{{ trans("Time") }}</span>
						<span class="text-right font-semibold text-gray-800">
							{{ useFormatTime(clockEventResult.clockedAt, { formatTime: "hms" }) }}
						</span>
					</div>
				</div>
			</div>
		</Dialog>
	</div>
</template>
