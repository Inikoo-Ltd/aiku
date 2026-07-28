<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from "vue"
import { QrcodeStream } from "vue-qrcode-reader"
import { LMap, LTileLayer, LMarker, LTooltip } from "@vue-leaflet/vue-leaflet"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck, faMapMarkerAlt, faSyncAlt, faCamera, faClock } from "@fal"
import { Dialog } from "primevue"
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

library.add(faTimes, faCheck, faMapMarkerAlt, faSyncAlt, faCamera, faClock)

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
const mapZoom = ref(15)
const isDetectingLocation = ref(false)

const cameraOn = ref(false)
const loading = ref(false)
const errorMsg = ref<string | null>(null)
const lastResult = ref<string | null>(null)

const hasLocation = computed(() => lat.value !== null && lng.value !== null)
const canOpenCamera = computed(() => hasLocation.value)

const showWorkHourModal = ref(false)
const showSuccessModal = ref(false)
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

onBeforeUnmount(() => {
	stopCamera()
})

const detectMyLocation = () => {
	errorMsg.value = null

	if (!navigator.geolocation) {
		errorMsg.value = "This browser does not support location access."
		return
	}

	isDetectingLocation.value = true

	const onSuccess = (pos) => {
		lat.value = pos.coords.latitude
		lng.value = pos.coords.longitude
		isDetectingLocation.value = false
	}

	const onError = (err) => {
		errorMsg.value = getGeolocationErrorMessage(err)
		isDetectingLocation.value = false
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

		showSuccessModal.value = true
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
	if (clockType.value === "clock_in") return trans("Clock-in successful")
	if (clockType.value === "clock_out") return trans("Clock-out successful")
	return trans("Scan successful")
})

const workingHoursFormatted = computed(() => {
	if (!workingHours.value) return "-"

	const start = useFormatTime(workingHours.value.start, { formatTime: "HH:mm" })
	const end = useFormatTime(workingHours.value.end, { formatTime: "HH:mm" })

	return `${start} - ${end}`
})

const notesLabel = computed(() => trans("Notes (optional)"))

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

const clockingSessions = computed<ClockingSession[]>(() => pageProps.clockingSessions ?? [])

const hasMultipleSessions = computed(() => clockingSessions.value.length > 1)

const sessionElapsedSeconds = (session: ClockingSession) => {
	if (session.duration !== null && session.duration !== undefined) return session.duration
	if (!session.starts_at) return null
	const end = session.ends_at ? new Date(session.ends_at) : new Date()
	return Math.max(0, Math.floor((end.getTime() - new Date(session.starts_at).getTime()) / 1000))
}

const trackFunction = () => ({
	facingMode: "environment",
	width: { ideal: 1080 },
	height: { ideal: 1440 },
})
</script>

<template>
	<div class="relative z-0">
		<div v-if="!cameraOn" class="max-w-lg mx-auto p-4 sm:p-6 sm:pb-6">
			<h2 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6">
				{{ trans("Employee Clocking") }}
			</h2>

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

				<div v-if="hasMultipleSessions" class="mt-3 pt-3 border-t border-gray-200">
					<p class="text-xs font-semibold text-gray-500 mb-2">
						{{ trans("Clocking Details") }} ({{ clockingSessions.length }})
					</p>
					<div class="space-y-2">
						<div
							v-for="session in clockingSessions"
							:key="session.id"
							class="rounded-lg bg-white/70 border border-gray-200 p-2">
							<div class="grid grid-cols-2 gap-2">
								<div class="text-center p-1 rounded bg-gray-50">
									<p class="text-[10px] text-gray-400">{{ trans("Clock In") }}</p>
									<p class="text-xs font-semibold text-gray-800">
										{{ displayTime(session.clock_in?.clocked_at ?? session.starts_at ?? undefined) }}
									</p>
								</div>
								<div class="text-center p-1 rounded bg-gray-50">
									<p class="text-[10px] text-gray-400">{{ trans("Clock Out") }}</p>
									<p class="text-xs font-semibold text-gray-800">
										{{
											session.is_open
												? "—"
												: displayTime(session.clock_out?.clocked_at ?? session.ends_at ?? undefined)
										}}
									</p>
								</div>
							</div>
							<div class="mt-1 flex items-center justify-center gap-1.5 text-[10px]">
								<FontAwesomeIcon
									v-if="session.is_open"
									:icon="faClock"
									class="text-amber-500"
									:title="trans('Ongoing')" />
								<template v-else>
									<FontAwesomeIcon
										:icon="faCheck"
										class="text-green-600"
										:title="trans('Completed')" />
									<span class="text-gray-500">
										{{ trans("Duration") }}:
										{{ formatDurationLocal(sessionElapsedSeconds(session) || 0) }}
									</span>
								</template>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div
				class="mb-6 rounded-xl border"
				:class="hasLocation ? 'border-green-200 bg-green-50/60' : 'border-gray-200 bg-white'">
				<div class="flex items-center gap-3 p-4">
					<div
						class="w-11 h-11 shrink-0 rounded-full flex items-center justify-center"
						:class="hasLocation ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'">
						<FontAwesomeIcon :icon="faMapMarkerAlt" class="text-lg" />
					</div>
					<div class="min-w-0 flex-1">
						<p
							class="text-sm font-semibold"
							:class="hasLocation ? 'text-green-800' : 'text-gray-700'">
							{{ hasLocation ? trans("Location detected") : trans("Location not detected") }}
						</p>
						<p class="text-xs text-gray-500 truncate">
							{{
								hasLocation
									? `${lat?.toFixed(6)}, ${lng?.toFixed(6)}`
									: trans("Required before you can scan")
							}}
						</p>
					</div>
				</div>

				<div v-if="hasLocation" class="px-4">
					<div class="h-40 sm:h-48 rounded-lg overflow-hidden border border-gray-200">
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

				<div class="p-4 sm:flex justify-center w-full">
					<Button
						:label="hasLocation ? trans('Update location') : trans('Detect my location')"
						:icon="hasLocation ? faSyncAlt : faMapMarkerAlt"
						:loading="isDetectingLocation"
						:disabled="isDetectingLocation"
						type="secondary"
						size="l"
						class="min-h-[44px] sm:min-h-[50px] w-full"
						@click="detectMyLocation"
						full />
				</div>
			</div>

			<!-- OPEN CAMERA -->
			<div>
				<p v-if="!canOpenCamera" class="mb-2 text-center text-xs text-gray-500">
					{{ trans("Detect your location first to enable scanning") }}
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
				<div class="flex justify-between items-center text-white p-4">
					<h3 class="font-semibold">{{ trans("Scan QR Code") }}</h3>
					<Button
						@click="stopCamera"
						class="!text-white text-2xl"
						type="tertiary"
						:icon="faTimes" />
				</div>

				<div class="flex-1 min-h-0 flex items-center justify-center relative overflow-hidden">
					<div class="relative w-full max-w-xl max-h-full aspect-[3/4]">
						<QrcodeStream
							@detect="onDetect"
							@error="onStreamError"
							:paused="loading"
							:formats="['qr_code']"
							:track="trackFunction" />

						<!-- TARGET OVERLAY -->
						<div
							class="absolute inset-0 flex items-center justify-center pointer-events-none">
							<div class="relative w-[70vw] max-w-[280px] aspect-[3/4]">
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
			v-model:visible="showSuccessModal"
			modal
			:closable="false"
			class="w-[95vw] max-w-[95vw] sm:w-[480px] sm:max-w-[480px]"
			appendTo="body">
			<div class="text-center space-y-4 py-4">
				<!-- ICON -->
				<div class="flex justify-center">
					<div class="w-20 h-20 rounded-full flex items-center justify-center bg-green-100">
						<FontAwesomeIcon :icon="faCheck" class="text-4xl text-green-600" />
					</div>
				</div>

				<!-- TITLE -->
				<h3 class="text-xl font-semibold text-gray-800">
					{{ modalTitle }}
				</h3>

				<!-- INFO -->
				<div class="text-sm text-gray-600 space-y-2 bg-gray-50 p-3 rounded-lg">
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
					<InputText v-model="notes" class="w-full" :placeholder="notesPlaceholder" />
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
					<Button label="Submit" type="save" @click="submitNotes" full />
				</div>
			</div>
		</Dialog>

		<div v-if="errorMsg" class="text-red-500 text-sm mt-2 text-center px-4">
			{{ errorMsg }}
		</div>
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
