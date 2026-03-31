<script setup lang="ts">
import { ref, computed, nextTick, onMounted } from "vue"
import { QrcodeStream } from "vue-qrcode-reader"
import { LMap, LTileLayer, LMarker, LTooltip } from "@vue-leaflet/vue-leaflet"
import { addMinutes, formatDuration, intervalToDuration, parseISO, set } from "date-fns"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck, faExclamationTriangle } from "@fal"
import { Dialog } from "primevue"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useFormatTime } from "@/Composables/useFormatTime"
import WorkHourSelectionModal from "@/Components/Utils/WorkHourSelectionModal.vue"

const pageProps = defineProps<{
	activeTimeTracker?: any
	clockingStatus?: string
	todayTimesheet?: any
	lastClockIn?: any
	lastClockOut?: any
	timezone?: string
}>()

library.add(faTimes, faCheck, faExclamationTriangle)

interface DetectedCode {
	rawValue: string
}

interface WorkSchedule {
	id: number
	name: string
	type: string
	is_active: boolean
}

interface LateClockInResult {
	isLate: boolean
	lateByMinutes: number
	lateMessage: string
	threshold: Date | null
	scanTime: Date | null
}

const lat = ref<number | null>(null)
const lng = ref<number | null>(null)
const mapZoom = ref(15)

const cameraOn = ref(false)
const loading = ref(false)
const errorMsg = ref<string | null>(null)
const lastResult = ref<string | null>(null)

const hasLocation = computed(() => lat.value !== null && lng.value !== null)
const canOpenCamera = computed(() => hasLocation.value)

const showWorkHourModal = ref(false)
const showSuccessModal = ref(false)
const showLateAlertModal = ref(false)
const notes = ref<string>("")
const scanTime = ref<string | null>(null)
const scanTimeRaw = ref<string | null>(null)
const now = new Date()
const clockType = ref<"clock_in" | "clock_out" | null>(null)
const clockingId = ref<number | null>(null)
const workingHours = ref<{ start: string; end: string } | null>(null)
const isProcessing = ref(false)
const shiftSchedules = ref<WorkSchedule[]>([])
const selectedWorkScheduleId = ref<number | null>(null)

// Status Computed Properties
const isClockedIn = computed(() => pageProps.clockingStatus === "clocked_in")

const statusClasses = computed(() => ({
	container: isClockedIn.value
		? "bg-green-50 border border-green-200"
		: "bg-gray-50 border border-gray-200",
	iconWrapper: isClockedIn.value ? "bg-green-100" : "bg-gray-200",
	icon: isClockedIn.value ? "text-green-600" : "text-gray-500",
	text: isClockedIn.value ? "text-green-800" : "text-gray-700",
}))

const statusText = computed(() =>
	isClockedIn.value ? trans("You're currently clocked in") : trans("You're currently clocked out")
)

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

const parseScanTime = (value?: string | null) => {
	if (!value) return null

	const parsed = parseISO(value)

	return Number.isNaN(parsed.getTime()) ? null : parsed
}

const parseWorkingOfficeHourStart = (value?: string | null, scanDate?: Date | null) => {
	if (!value || !scanDate) return null

	const match = value.match(/(\d{2}):(\d{2})(?::(\d{2}))?/)
	if (!match) return null

	const [, hours, minutes, seconds = "00"] = match

	return set(scanDate, {
		hours: Number(hours),
		minutes: Number(minutes),
		seconds: Number(seconds),
		milliseconds: 0,
	})
}

const formatLateDuration = (lateByMinutes: number) => {
	if (lateByMinutes <= 0) return ""

	const duration = intervalToDuration({
		start: 0,
		end: lateByMinutes * 60 * 1000,
	})

	const formatted = formatDuration(duration, {
		format: ["hours", "minutes"],
		delimiter: " and ",
	})

	return formatted || "less than 1 minute"
}

const getLateClockInState = ({
	scanTimeRaw,
	workingOfficeHourStart,
	gracePeriodMinutes = 15,
}: {
	scanTimeRaw?: string | null
	workingOfficeHourStart?: string | null
	gracePeriodMinutes?: number
}): LateClockInResult => {
	const scanTime = parseScanTime(scanTimeRaw)
	const workingStart = parseWorkingOfficeHourStart(workingOfficeHourStart, scanTime)

	if (!scanTime || !workingStart) {
		return {
			isLate: false,
			lateByMinutes: 0,
			lateMessage: "",
			threshold: null,
			scanTime,
		}
	}

	const threshold = addMinutes(workingStart, gracePeriodMinutes)
	const lateByMilliseconds = scanTime.getTime() - threshold.getTime()

	if (lateByMilliseconds <= 0) {
		return {
			isLate: false,
			lateByMinutes: 0,
			lateMessage: "",
			threshold,
			scanTime,
		}
	}

	const lateByMinutes = Math.floor(lateByMilliseconds / (60 * 1000))
	const formattedDuration = formatLateDuration(lateByMinutes)

	return {
		isLate: true,
		lateByMinutes,
		lateMessage: formattedDuration ? `You are ${formattedDuration} late` : "",
		threshold,
		scanTime,
	}
}

const fetchShiftSchedules = async () => {
	try {
		const { data } = await axios.get(route("grp.models.work-schedule.index"))
		shiftSchedules.value = data.data ?? []
	} catch (e) {
		console.error("Failed to fetch shift schedules:", e)
	}
}

onMounted(() => {
	fetchShiftSchedules()
})

const detectMyLocation = () => {
	errorMsg.value = null

	if (!navigator.geolocation) {
		errorMsg.value = "This browser does not support location access."
		return
	}

	navigator.geolocation.getCurrentPosition(
		(pos) => {
			lat.value = pos.coords.latitude
			lng.value = pos.coords.longitude
		},
		(err) => {
			errorMsg.value = getGeolocationErrorMessage(err)
		},
		{
			enableHighAccuracy: true,
			timeout: 10000,
			maximumAge: 0,
		}
	)
}

const startCamera = async () => {
	errorMsg.value = null

	if (!canOpenCamera.value) {
		console.warn("Camera blocked — missing type or location")
		return
	}

	showWorkHourModal.value = true
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

		const lateState = getLateClockInState({
			scanTimeRaw: scanTimeRaw.value,
			workingOfficeHourStart: workingHours.value?.start,
		})

		if (clockType.value === "clock_in" && lateState.isLate) {
			showLateAlertModal.value = true
		} else {
			showSuccessModal.value = true
		}
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

const modalTitle = computed(() => {
	if (isLateClockIn.value) return trans("Late Clock-in Recorded")
	if (clockType.value === "clock_in") return trans("Clock-in successful")
	if (clockType.value === "clock_out") return trans("Clock-out successful")
	return trans("Scan successful")
})

const lateClockInState = computed(() =>
	getLateClockInState({
		scanTimeRaw: scanTimeRaw.value,
		workingOfficeHourStart: workingHours.value?.start,
	})
)

const isLateClockIn = computed(
	() => clockType.value === "clock_in" && lateClockInState.value.isLate
)

const workingHoursFormatted = computed(() => {
	if (!workingHours.value) return "-"

	const start = useFormatTime(workingHours.value.start, { formatTime: "HH:mm" })
	const end = useFormatTime(workingHours.value.end, { formatTime: "HH:mm" })

	return `${start} - ${end}`
})

const isSubmitDisabled = computed(() => isLateClockIn.value && !notes.value.trim())

const notesLabel = computed(() =>
	isLateClockIn.value ? trans("Notes") : trans("Notes (optional)")
)

const notesPlaceholder = computed(() =>
	isLateClockIn.value ? trans("Please provide a reason for being late...") : trans("Input Notes")
)

const closeLateAlertModal = () => {
	showLateAlertModal.value = false
	showSuccessModal.value = true
}

const submitNotes = async () => {
	if (!clockingId.value || isSubmitDisabled.value) return

	try {
		await axios.patch(
			route("grp.models.clocking-machine.clocking.notes.update", clockingId.value),
			{
				notes: notes.value,
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

// Formatting Helpers
const formatDurationLocal = (seconds: number) => {
	const hours = Math.floor(seconds / 3600)
	const minutes = Math.floor((seconds % 3600) / 60)
	if (hours === 0 && minutes === 0) return trans("0m")
	return hours === 0 ? `${minutes}m` : `${hours}h ${minutes}m`
}

const formatInTimezone = (dateString: string | undefined, options: Intl.DateTimeFormatOptions) => {
	if (!dateString) return "-"
	return new Date(dateString).toLocaleString("en-US", {
		...options,
		timeZone: pageProps.timezone || "UTC",
	})
}

const displayDate = computed(() =>
	formatInTimezone(pageProps.todayTimesheet?.date, {
		year: "numeric",
		month: "2-digit",
		day: "2-digit",
	})
)

const displayTime = (date?: string) =>
	formatInTimezone(date, {
		hour: "numeric",
		minute: "2-digit",
		second: "2-digit",
		hour12: true,
	})

const trackFunction = () => ({
	facingMode: "environment",
	width: { ideal: 1080 },
	height: { ideal: 1440 },
})
</script>

<template>
	<div class="relative z-0">
		<div v-if="!cameraOn" class="max-w-lg mx-auto p-6">
			<h2 class="text-2xl font-bold mb-6">{{ trans("Employee Clocking") }}</h2>

			<div
				v-if="pageProps?.clockingStatus"
				class="mb-6 p-4 rounded-lg"
				:class="statusClasses.container">
				<div class="flex items-center justify-between mb-3">
					<div class="flex items-center gap-3">
						<div
							class="w-10 h-10 rounded-full flex items-center justify-center"
							:class="statusClasses.iconWrapper">
							<FontAwesomeIcon
								:icon="isClockedIn ? faCheck : faTimes"
								class="text-lg"
								:class="statusClasses.icon" />
						</div>
						<div>
							<p class="text-sm font-medium" :class="statusClasses.text">
								{{ statusText }}
							</p>
							<p
								v-if="isClockedIn && pageProps.activeTimeTracker?.starts_at"
								class="text-xs text-gray-500">
								{{ trans("Since") }}:
								{{
									useFormatTime(pageProps.activeTimeTracker.starts_at, {
										formatTime: "hms",
									})
								}}
							</p>
						</div>
					</div>
					<div class="text-right">
						<p class="text-xs text-gray-400">{{ trans("Date") }}</p>
						<p class="text-sm font-semibold text-gray-700">
							{{ displayDate }}
						</p>
					</div>
				</div>

				<div
					v-if="pageProps.todayTimesheet?.start_at || pageProps.todayTimesheet?.end_at"
					class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-200">
					<div
						v-if="pageProps.todayTimesheet?.start_at"
						class="text-center p-2 rounded bg-white/50">
						<p class="text-xs text-gray-400">{{ trans("First Clock In") }}</p>
						<p class="text-sm font-semibold text-gray-800">
							{{ displayTime(pageProps.todayTimesheet.start_at) }}
						</p>
					</div>
					<div
						v-if="pageProps.todayTimesheet?.end_at"
						class="text-center p-2 rounded bg-white/50">
						<p class="text-xs text-gray-400">{{ trans("Last Clock Out") }}</p>
						<p class="text-sm font-semibold text-gray-800">
							{{ displayTime(pageProps.todayTimesheet.end_at) }}
						</p>
					</div>
				</div>

				<div
					v-if="pageProps.todayTimesheet"
					class="flex gap-4 mt-3 pt-3 border-t border-gray-200">
					<span class="text-xs text-gray-500">
						{{ trans("Working") }}:
						{{ formatDurationLocal(pageProps.todayTimesheet.working_duration || 0) }}
					</span>
					<span class="text-xs text-gray-500">
						{{ trans("Breaks") }}:
						{{ formatDurationLocal(pageProps.todayTimesheet.breaks_duration || 0) }}
					</span>
				</div>
			</div>

			<div class="mb-6">
				<p class="text-sm font-semibold text-gray-500 mb-2">
					{{ trans("Detect Location") }}
				</p>
				<Button
					label="Detect My Location"
					type="secondary"
					@click="detectMyLocation"
					full />

				<div v-if="hasLocation" class="mt-3 h-48 rounded-xl overflow-hidden border shadow">
					<LMap :zoom="mapZoom" :center="[lat, lng]" style="height: 100%">
						<LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
						<LMarker :lat-lng="[lat, lng]">
							<LTooltip>
								Lat: {{ lat?.toFixed(6) }}<br />Lng: {{ lng?.toFixed(6) }}
							</LTooltip>
						</LMarker>
					</LMap>
				</div>
			</div>

			<!-- OPEN CAMERA -->
			<Button
				label="Open Camera"
				type="primary"
				@click="startCamera"
				:disabled="!canOpenCamera"
				full />
		</div>
		<Teleport to="body">
			<div v-if="cameraOn" class="fixed inset-0 bg-black z-[9999] flex flex-col">
				<div class="flex justify-between items-center text-white p-4">
					<h3 class="font-semibold">{{ trans("Scan QR Code") }}</h3>
					<Button
						@click="stopCamera"
						class="!text-white text-2xl"
						type="tertiary"
						:icon="faTimes" />
				</div>

				<div class="flex-1 flex items-center justify-center relative">
					<div class="w-full max-w-xl relative" style="aspect-ratio: 3/4">
						<QrcodeStream
							@detect="onDetect"
							@error="onStreamError"
							:paused="loading"
							:formats="['qr_code']"
							:track="trackFunction" />

						<!-- TARGET OVERLAY -->
						<div
							class="absolute inset-0 flex items-center justify-center pointer-events-none">
							<div class="scanner-frame">
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
				<div class="text-center text-white pb-6 text-sm opacity-70">
					{{ trans("Align QR code inside the frame") }}
				</div>
			</div>
		</Teleport>

		<WorkHourSelectionModal
			v-model:visible="showWorkHourModal"
			:allow-shift="true"
			:shift-schedules="shiftSchedules"
			@confirm="handleWorkHourConfirm" />

		<Dialog
			v-model:visible="showLateAlertModal"
			modal
			:closable="false"
			:style="{ width: '420px' }"
			appendTo="body">
			<div class="text-center space-y-4 py-4">
				<div class="flex justify-center">
					<div class="w-20 h-20 rounded-full flex items-center justify-center bg-red-100">
						<FontAwesomeIcon
							:icon="faExclamationTriangle"
							class="text-4xl text-red-600" />
					</div>
				</div>

				<h3 class="text-xl font-semibold text-red-700">
					{{ trans("Late Clock-in Alert") }}
				</h3>

				<p
					class="text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-3">
					{{ trans("You are late today.") }}
				</p>

				<p v-if="lateClockInState.lateMessage" class="text-sm text-gray-600">
					{{ lateClockInState.lateMessage }}
				</p>

				<div class="pt-2">
					<Button label="Continue" type="warning" @click="closeLateAlertModal" full />
				</div>
			</div>
		</Dialog>

		<Dialog
			v-model:visible="showSuccessModal"
			modal
			:closable="false"
			:style="{ width: '420px' }"
			appendTo="body">
			<div class="text-center space-y-4 py-4">
				<!-- ICON -->
				<div class="flex justify-center">
					<div
						class="w-20 h-20 rounded-full flex items-center justify-center"
						:class="isLateClockIn ? 'bg-amber-100' : 'bg-green-100'">
						<FontAwesomeIcon
							:icon="isLateClockIn ? faExclamationTriangle : faCheck"
							class="text-4xl"
							:class="isLateClockIn ? 'text-amber-600' : 'text-green-600'" />
					</div>
				</div>

				<!-- TITLE -->
				<h3
					class="text-xl font-semibold"
					:class="isLateClockIn ? 'text-amber-700' : 'text-gray-800'">
					{{ modalTitle }}
				</h3>

				<p
					v-if="isLateClockIn && lateClockInState.lateMessage"
					class="text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
					{{ lateClockInState.lateMessage }}
				</p>

				<!-- INFO -->
				<div class="text-sm text-gray-600 space-y-2 bg-gray-50 p-3 rounded-lg">
					<div
						v-if="isLateClockIn"
						class="flex justify-between items-center bg-amber-50 p-2 rounded border border-amber-200">
						<div class="flex items-center gap-2">
							<FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" />
							<span class="font-semibold text-amber-700">{{
								trans("Late Arrival")
							}}</span>
						</div>
					</div>

					<div class="flex justify-between">
						<span class="text-gray-500">{{ trans("Schedule ") }}</span>
						<span class="font-semibold text-gray-800">
							{{ useFormatTime(now) ?? "-" }}
						</span>
					</div>
					<div class="flex justify-between">
						<span class="text-gray-500">{{ trans("Working Office Hour ") }}</span>
						<span class="font-semibold text-gray-800">{{ workingHoursFormatted }}</span>
					</div>
					<div class="flex justify-between">
						<span class="text-gray-500">{{ trans("Scan Time") }}</span>
						<span class="font-semibold text-gray-800">{{ scanTime ?? "-" }}</span>
					</div>
				</div>

				<!-- NOTES INPUT -->
				<div class="pt-3">
					<label class="text-sm text-gray-600 block mb-1 text-left">
						{{ notesLabel }}
					</label>
					<InputText
						v-model="notes"
						class="w-full"
						:required="isLateClockIn"
						:placeholder="notesPlaceholder" />
				</div>

				<!-- ACTIONS -->
				<div class="flex gap-2 pt-4">
					<Button
						label="Close"
						type="exit"
						@click="
							() => {
								showSuccessModal = false
								window.location.reload()
							}
						"
						full />
					<Button
						label="Submit"
						type="save"
						@click="submitNotes"
						:disabled="isSubmitDisabled"
						full />
				</div>
			</div>
		</Dialog>

		<div v-if="errorMsg" class="text-red-500 text-sm mt-2 text-center">{{ errorMsg }}</div>
	</div>
</template>
<style scoped>
.leaflet-pane {
	z-index: 1 !important;
}

.leaflet-top,
.leaflet-bottom {
	z-index: 1 !important;
}

.p-dialog {
	z-index: 9999 !important;
}

.scanner-frame {
	position: relative;
	width: 280px;
	height: 373px;
}

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
