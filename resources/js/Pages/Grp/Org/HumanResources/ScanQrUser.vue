<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from "vue"
import { QrcodeStream } from "vue-qrcode-reader"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck, faMapMarkerAlt, faSyncAlt, faCamera } from "@fal"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useFormatTime } from "@/Composables/useFormatTime"
import WorkHourSelectionModal from "@/Components/Utils/WorkHourSelectionModal.vue"

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

const pageProps = defineProps<{
	activeTimeTracker?: any
	clockingStatus?: string
	todayTimesheet?: any
	lastClockIn?: any
	lastClockOut?: any
	clockingSessions?: ClockingSession[]
	timezone?: string
}>()

library.add(faTimes, faCheck, faMapMarkerAlt, faSyncAlt, faCamera)

interface DetectedCode {
	rawValue: string
}

interface WorkSchedule {
	id: number
	name: string
	type: string
	is_active: boolean
}

const lat = ref<number | null>(null)
const lng = ref<number | null>(null)
const isDetectingLocation = ref(false)
const cameraOn = ref(false)
const loading = ref(false)
const errorMsg = ref<string | null>(null)
const lastResult = ref<string | null>(null)

const locationUnavailable = ref(false)

const hasLocation = computed(() => lat.value !== null && lng.value !== null)

/**
 * A phone that cannot produce a fix must still reach the scanner: whether that is allowed is the
 * server's call, through the employee's clocking policy, not something to decide on the handset.
 */
const canOpenCamera = computed(() => hasLocation.value || locationUnavailable.value)

const locationTitle = computed(() => {
	if (hasLocation.value) return trans("Location detected")
	if (isDetectingLocation.value) return trans("Detecting your location…")
	return trans("Location not detected")
})

const locationSubtitle = computed(() => {
	if (hasLocation.value) return `${lat.value?.toFixed(6)}, ${lng.value?.toFixed(6)}`
	if (isDetectingLocation.value) return trans("Please wait a moment")
	return trans("You can still scan, ask HR if it keeps failing")
})

const showWorkHourModal = ref(false)
const showSuccessModal = ref(false)
const isVisitingOffice = ref(false)
const notes = ref<string>("")
const scanTime = ref<string | null>(null)
const scanTimeRaw = ref<string | null>(null)
const clockType = ref<"clock_in" | "clock_out" | null>(null)
const clockingId = ref<number | null>(null)
const workingHours = ref<{ start: string; end: string } | null>(null)
const isProcessing = ref(false)
let successAutoCloseTimer: ReturnType<typeof setTimeout> | null = null

const cancelSuccessAutoClose = () => {
	if (successAutoCloseTimer) {
		clearTimeout(successAutoCloseTimer)
		successAutoCloseTimer = null
	}
}
const shiftSchedules = ref<WorkSchedule[]>([])
const selectedWorkScheduleId = ref<number | null>(null)

const isIOS = () => {
	return /iPhone|iPad|iPod/i.test(navigator.userAgent)
}

const getGeolocationErrorMessage = (err?: GeolocationPositionError) => {
	switch (err?.code) {
		case err?.PERMISSION_DENIED:
			return isIOS()
				? "Location blocked. Go to Settings > Safari > Location > Allow"
				: "Location blocked. Please enable location permission in browser settings."
		case err?.POSITION_UNAVAILABLE:
			return "Location unavailable"
		case err?.TIMEOUT:
			return "Location timeout"
		default:
			return "Location error"
	}
}

let shiftSchedulesRequest: Promise<void> | null = null

const fetchShiftSchedules = async () => {
	try {
		const { data } = await axios.get(route("grp.models.work-schedule.index"))
		shiftSchedules.value = data.data ?? []
	} catch (e) {
		console.error("Failed to fetch shift schedules:", e)
	}
}

onMounted(() => {
	shiftSchedulesRequest = fetchShiftSchedules()
	detectMyLocation()
})

onBeforeUnmount(() => {
	stopCamera()
	cancelSuccessAutoClose()
})

const detectMyLocation = () => {
	errorMsg.value = null

	if (!navigator.geolocation) {
		errorMsg.value = "This browser does not support location access."
		return
	}

	isDetectingLocation.value = true
	locationUnavailable.value = false

	const onSuccess = (pos) => {
		lat.value = pos.coords.latitude
		lng.value = pos.coords.longitude
		isDetectingLocation.value = false
	}

	const onError = (err) => {
		errorMsg.value = getGeolocationErrorMessage(err)
		isDetectingLocation.value = false
		locationUnavailable.value = true
	}

	const onHighAccuracyError = (err) => {
		if (err.code === err.TIMEOUT || err.code === err.POSITION_UNAVAILABLE) {
			navigator.geolocation.getCurrentPosition(onSuccess, onError, {
				enableHighAccuracy: false,
				timeout: 15000,
				maximumAge: 60000,
			})
			return
		}
		onError(err)
	}

	navigator.geolocation.getCurrentPosition(onSuccess, onHighAccuracyError, {
		enableHighAccuracy: true,
		timeout: 15000,
		maximumAge: 30000,
	})
}

const startCamera = async () => {
	errorMsg.value = null

	if (!canOpenCamera.value) {
		console.warn("Camera blocked — missing type or location")
		return
	}

	await shiftSchedulesRequest

	const hasActiveShifts = shiftSchedules.value.some((s) => s.type === "shift" && s.is_active)
	if (hasActiveShifts) {
		showWorkHourModal.value = true
	} else {
		handleWorkHourConfirm(null)
	}
}

const openCamera = async () => {
	try {
		const stream = await navigator.mediaDevices.getUserMedia({
			video: {
				facingMode: { ideal: "environment" },
				width: { ideal: 1080 },
				height: { ideal: 1920 },
			},
		})
		stream.getTracks().forEach((track) => track.stop())
		cameraOn.value = true
	} catch (err: any) {
		console.error("Camera error:", err)

		if (err.name === "NotAllowedError") {
			if (isIOS()) {
				errorMsg.value = "Camera blocked. Go to Settings > Safari > Camera > Allow"
			} else {
				errorMsg.value =
					"Camera blocked. Please enable camera permission in browser settings."
			}
		} else if (err.name === "NotFoundError") {
			errorMsg.value = "No camera found"
		} else if (err.name === "NotReadableError") {
			errorMsg.value = "Camera already in use"
		} else if (err.name === "OverconstrainedError") {
			errorMsg.value = "Camera constraint not supported"
		} else {
			errorMsg.value = "Camera error occurred"
		}
	}
}

const handleWorkHourConfirm = (workScheduleId: number | null) => {
	selectedWorkScheduleId.value = workScheduleId
	openCamera()
}

const stopCamera = async () => {
	cameraOn.value = false
	loading.value = false

	await nextTick()
}

const onDetect = async (detectedCodes: DetectedCode[]) => {
	if (isProcessing.value) return
	isProcessing.value = true

	const result = detectedCodes[0]?.rawValue

	lastResult.value = result
	loading.value = true

	stopCamera()

	try {
		const payload: any = {
			qr_code: result,
			latitude: lat.value,
			longitude: lng.value,
		}

		if (selectedWorkScheduleId.value) {
			payload.work_schedule_id = selectedWorkScheduleId.value
		}

		const { data } = await axios.post(route("grp.models.clocking-machine.qr.validate"), payload)

		clockType.value = data.clocking?.type
		isVisitingOffice.value = data.is_visiting ?? false
		scanTimeRaw.value = data.clocking?.clocked_at ?? null
		scanTime.value = useFormatTime(data.clocking?.clocked_at, { formatTime: "hms" })
		clockingId.value = data.clocking?.id
		if (data.working_hours) {
			const scanDate = new Date(data.clocking?.clocked_at)
			const dateOnly = scanDate.toISOString().split("T")[0]

			workingHours.value = {
				start: `${dateOnly}T${data.working_hours.start}`,
				end: `${dateOnly}T${data.working_hours.end}`,
			}
		} else {
			workingHours.value = null
		}

		showSuccessModal.value = true
		successAutoCloseTimer = setTimeout(() => window.location.reload(), 6000)
	} catch (e: any) {
		notify({
			title: trans("Failed Scan QR"),
			text: e.response?.data?.message,
			type: "error",
		})

		errorMsg.value = e.response?.data?.message || "QR invalid"
		stopCamera()
	} finally {
		loading.value = false
		isProcessing.value = false
		selectedWorkScheduleId.value = null
	}
}

const onStreamError = (err: Error) => {
	console.error("Camera Error:", err)

	if (err.name === "NotAllowedError") {
		errorMsg.value = "Camera permission denied"
	} else if (err.name === "NotFoundError") {
		errorMsg.value = "No camera found"
	} else if (err.name === "NotSupportedError") {
		errorMsg.value = "HTTPS required"
	} else {
		errorMsg.value = "Camera error"
	}
}

const workingHoursFormatted = computed(() => {
	if (!workingHours.value) return "-"

	const start = useFormatTime(workingHours.value.start, { formatTime: "HH:mm" })
	const end = useFormatTime(workingHours.value.end, { formatTime: "HH:mm" })

	return `${start} - ${end}`
})

watch(notes, cancelSuccessAutoClose)

const notesPlaceholder = computed(() => trans("Input Notes"))

const submitNotes = async () => {
	if (!clockingId.value) return

	try {
		await axios.patch(
			route("grp.models.clocking-machine.clocking.notes.update", clockingId.value),
			{
				notes: notes.value.trim() || null,
			}
		)

		showSuccessModal.value = false
		notes.value = ""
		clockingId.value = null
		notify({
			title: trans("Success"),
			text: trans(`submit notes`),
			type: "success",
		})

		window.location.reload()
	} catch (e: any) {
		notify({
			title: trans("Failed submit notes"),
			text: e.response?.data?.message,
			type: "error",
		})

		errorMsg.value = e.response?.data?.message || "Failed submit notes"
		console.error(e)
	}
}

const trackFunction = () => ({
	facingMode: "environment",
	width: { ideal: 1080 },
	height: { ideal: 1440 },
})
</script>

<template>
	<div class="relative z-0 space-y-4">
		<div v-if="!cameraOn" class="space-y-4">
			<div
				class="rounded-xl border p-3 sm:p-4 space-y-3 sm:space-y-4"
				:class="
					hasLocation
						? 'border-green-200 bg-green-50/60'
						: 'border-dashed border-gray-300 bg-gray-50'
				">
				<div class="flex items-center gap-2 sm:gap-3">
					<div
						class="w-10 h-10 sm:w-11 sm:h-11 shrink-0 rounded-full flex items-center justify-center"
						:class="hasLocation ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'">
						<FontAwesomeIcon :icon="faMapMarkerAlt" class="text-base sm:text-lg" />
					</div>
					<div class="min-w-0 flex-1">
						<p
							class="text-xs sm:text-sm font-semibold"
							:class="hasLocation ? 'text-green-800' : 'text-gray-700'">
							{{ locationTitle }}
						</p>
						<a
							v-if="hasLocation"
							:href="`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=17/${lat}/${lng}`"
							target="_blank"
							class="block text-[11px] sm:text-xs text-green-700 underline truncate">
							{{ locationSubtitle }} — {{ trans("view on map") }}
						</a>
						<p v-else class="text-[11px] sm:text-xs text-gray-500 truncate">
							{{ locationSubtitle }}
						</p>
					</div>
				</div>

				<Button
					:label="hasLocation ? trans('Update location') : trans('Detect my location')"
					:icon="hasLocation ? faSyncAlt : faMapMarkerAlt"
					:loading="isDetectingLocation"
					:disabled="isDetectingLocation"
					type="secondary"
					size="l"
					class="lg:min-h-[44px] md:min-h-[32px]  sm:min-h-[50px]"
					@click="detectMyLocation"
					full />
			</div>

			<div class="space-y-2">
				<p v-if="!canOpenCamera" class="text-center text-[11px] sm:text-xs text-gray-500">
					{{ trans("Waiting for your location…") }}
				</p>
				<Button
					:label="trans('Open camera')"
					:icon="faCamera"
					type="primary"
					size="l"
					class="min-h-[44px] sm:min-h-[50px]"
					@click="startCamera"
					:disabled="!canOpenCamera"
					full />
			</div>
		</div>
		<Teleport to="body">
			<div v-if="cameraOn" class="fixed inset-0 bg-black z-[9999] flex flex-col overflow-hidden">
				<div class="flex justify-between items-center text-white p-3 sm:p-4">
					<h3 class="text-sm sm:text-base font-semibold">{{ trans("Scan QR Code") }}</h3>
					<Button
						@click="stopCamera"
						class="!text-white text-2xl"
						type="tertiary"
						:icon="faTimes" />
				</div>

				<div class="flex-1 min-h-0 flex items-center justify-center relative overflow-hidden">
					<div class="relative h-full max-h-full w-full max-w-xl sm:aspect-[3/4] sm:h-auto">
						<QrcodeStream
							@detect="onDetect"
							@error="onStreamError"
							:paused="loading"
							:formats="['qr_code']"
							:track="trackFunction" />

						<!-- TARGET OVERLAY -->
						<div
							class="absolute inset-0 flex items-center justify-center pointer-events-none">
							<div
								class="relative aspect-[3/4] h-[45vh] max-h-[280px] w-auto max-w-[70vw]">
								<!-- 4 CORNERS -->
								<span class="corner tl"></span>
								<span class="corner tr"></span>
								<span class="corner bl"></span>
								<span class="corner br"></span>

								<!-- SCAN LINE -->
								<div class="scan-line"></div>
							</div>
						</div>
					</div>
				</div>
				<div class="px-4 pb-4 sm:pb-6 text-center text-xs sm:text-sm text-white opacity-70">
					{{ trans("Align QR code inside the frame") }}
				</div>
			</div>
		</Teleport>

		<WorkHourSelectionModal
			v-model:visible="showWorkHourModal"
			:allow-shift="true"
			:shift-schedules="shiftSchedules"
			@confirm="handleWorkHourConfirm" />

		<Teleport to="body">
			<div
				v-if="showSuccessModal"
				class="fixed inset-0 z-[9999] flex flex-col items-center justify-center gap-6 px-6 text-center text-white"
				:class="clockType === 'clock_out' ? 'bg-sky-600' : 'bg-green-600'">
				<FontAwesomeIcon :icon="faCheck" class="text-[7rem] sm:text-[9rem]" />
				<div class="text-4xl sm:text-6xl font-extrabold uppercase tracking-wide">
					{{ clockType === "clock_out" ? trans("Clocked out") : trans("Clocked in") }}
				</div>
				<div class="text-5xl sm:text-7xl font-black tabular-nums">{{ scanTime ?? "-" }}</div>
				<div v-if="isVisitingOffice" class="text-base opacity-90">
					{{ trans("Hey, you're in the wrong office \u2014 but we don't mind!") }}
				</div>
				<div v-if="workingHours" class="text-base opacity-90">
					{{ trans("Working hours") }}: {{ workingHoursFormatted }}
				</div>

				<div class="w-full max-w-sm space-y-2 pt-4" @click.stop>
					<InputText v-model="notes" class="w-full" :placeholder="notesPlaceholder" />
					<div class="flex gap-2">
						<Button
							:label="trans('Close')"
							type="exit"
							@click="
								() => {
									cancelSuccessAutoClose()
									showSuccessModal = false
									window.location.reload()
								}
							"
							full />
						<Button v-if="notes.trim()" :label="trans('Save note')" type="save" @click="submitNotes" full />
					</div>
				</div>
			</div>
		</Teleport>

		<div
			v-if="errorMsg"
			class="rounded-lg bg-red-50 px-3 py-2 text-center text-xs sm:text-sm text-red-700">
			{{ errorMsg }}
		</div>
	</div>
</template>
<style scoped>
/* ===== CORNERS ===== */
.corner {
	position: absolute;
	width: 50px;
	height: 50px;
	border-color: white;
}

.tl {
	top: 0;
	left: 0;
	border-top: 5px solid;
	border-left: 5px solid;
	border-top-left-radius: 12px;
}

.tr {
	top: 0;
	right: 0;
	border-top: 5px solid;
	border-right: 5px solid;
	border-top-right-radius: 12px;
}

.bl {
	bottom: 0;
	left: 0;
	border-bottom: 5px solid;
	border-left: 5px solid;
	border-bottom-left-radius: 12px;
}

.br {
	bottom: 0;
	right: 0;
	border-bottom: 5px solid;
	border-right: 5px solid;
	border-bottom-right-radius: 12px;
}

/* ===== SCAN LINE ===== */
.scan-line {
	position: absolute;
	left: 0;
	width: 100%;
	height: 3px;
	background: linear-gradient(90deg, transparent, #00ff88, transparent);
	box-shadow: 0 0 8px #00ff88;
	animation: scanMove 2s linear infinite;
}

@keyframes scanMove {
	0% {
		top: 0;
		opacity: 0;
	}

	10% {
		opacity: 1;
	}

	90% {
		opacity: 1;
	}

	100% {
		top: 100%;
		opacity: 0;
	}
}
</style>
