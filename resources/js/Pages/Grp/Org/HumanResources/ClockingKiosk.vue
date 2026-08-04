
<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, onMounted, onUnmounted, ref } from "vue"
import { faExpand, faCompress, faBackspace, faCheckCircle, faTimesCircle, faBarcode, faCamera } from "@fortawesome/free-solid-svg-icons"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { QrcodeStream } from "vue-qrcode-reader"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import BlankLayout from "@/Layouts/BlankLayout.vue"

defineOptions({ layout: BlankLayout })

const props = defineProps<{
	title: string
	machineName: string
	kioskToken: string
	mode: "pin" | "barcode" | "camera_qr"
	pinCharacterSet?: {
		letters: string[]
		numbers: string[]
	}
}>()

// Employees see their pin with the organisation id prefix (e.g. "5:ABC123") and there's
// no ':' key on this keypad, so entries are commonly a few characters longer than the
// 6-character generated code. Cap generously rather than silently swallowing taps.
const maxPinLength = 16
const idleClearMs = 20000
const resultDisplayMs = 3500

const kioskContainer = ref<HTMLElement | null>(null)
const isFullscreen = ref(false)
const enteredPin = ref<string[]>([])
const barcodeValue = ref("")
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)
const result = ref<{ alias: string; actionType: string | null; clockedAt: string } | null>(null)

const scannerContainer = ref<HTMLElement | null>(null)
const cameraActive = ref(false)
const cameraError = ref<string | null>(null)
const isDetecting = ref(false)
const recentCodeCooldownMs = 8000
let lastProcessedCode: string | null = null
let lastProcessedAt = 0

let idleTimer: ReturnType<typeof setTimeout> | null = null
let resultTimer: ReturnType<typeof setTimeout> | null = null

const pinDisplay = computed(() => enteredPin.value.join(" "))
const canSubmit = computed(() => enteredPin.value.length > 0 && !isSubmitting.value)

const resetIdleTimer = () => {
	if (idleTimer) clearTimeout(idleTimer)
	idleTimer = setTimeout(() => {
		enteredPin.value = []
	}, idleClearMs)
}

const tapCharacter = (character: string) => {
	if (isSubmitting.value || result.value || enteredPin.value.length >= maxPinLength) return

	enteredPin.value.push(character)
	errorMessage.value = null
	resetIdleTimer()
}

const backspace = () => {
	if (isSubmitting.value || result.value) return

	enteredPin.value.pop()
	resetIdleTimer()
}

const clearPin = () => {
	enteredPin.value = []
	errorMessage.value = null
}

const applyResult = (data: any) => {
	result.value = {
		alias: data.employee.alias,
		actionType: data.clocking.type,
		clockedAt: data.clocking.clocked_at,
	}

	resultTimer = setTimeout(() => {
		result.value = null
		enteredPin.value = []
		barcodeValue.value = ""
	}, resultDisplayMs)
}

const submitPin = async () => {
	if (!canSubmit.value) return

	isSubmitting.value = true
	errorMessage.value = null

	try {
		const res = await axios.post(route("grp.kiosk.pin.submit", { kioskToken: props.kioskToken }), {
			pin: enteredPin.value.join(""),
		})

		applyResult(res.data)
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.message || trans("Invalid PIN.")
		enteredPin.value = []
	} finally {
		isSubmitting.value = false
	}
}

const scanIdleResetMs = 3000
let lastKeystrokeAt = 0

const submitBarcode = async () => {
	const code = barcodeValue.value.trim()
	if (!code || isSubmitting.value) return

	isSubmitting.value = true
	errorMessage.value = null

	try {
		const res = await axios.post(route("grp.kiosk.barcode.submit", { kioskToken: props.kioskToken }), {
			barcode: code,
		})

		applyResult(res.data)
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.message || trans("Invalid barcode.")
		barcodeValue.value = ""
	} finally {
		isSubmitting.value = false
	}
}

const handleGlobalKeydown = (event: KeyboardEvent) => {
	if (props.mode !== "barcode" || isSubmitting.value || result.value) return

	const target = event.target as HTMLElement | null
	if (target?.isContentEditable) return

	const now = Date.now()
	if (now - lastKeystrokeAt > scanIdleResetMs) {
		barcodeValue.value = ""
	}
	lastKeystrokeAt = now

	if (event.key === "Enter") {
		event.preventDefault()
		submitBarcode()
		return
	}

	if (event.key.length === 1) {
		event.preventDefault()
		barcodeValue.value += event.key
	}
}

const startCamera = () => {
	cameraError.value = null
	cameraActive.value = true
}

const stopCamera = () => {
	cameraActive.value = false
}

const captureSnapshot = (): string | null => {
	const videoEl = scannerContainer.value?.querySelector("video") as HTMLVideoElement | null
	if (!videoEl || !videoEl.videoWidth) return null

	const maxWidth = 640
	const scale = Math.min(1, maxWidth / videoEl.videoWidth)
	const canvas = document.createElement("canvas")
	canvas.width = Math.round(videoEl.videoWidth * scale)
	canvas.height = Math.round(videoEl.videoHeight * scale)

	const ctx = canvas.getContext("2d")
	if (!ctx) return null

	ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height)

	return canvas.toDataURL("image/jpeg", 0.7)
}

const onCameraDetect = async (detectedCodes: { rawValue: string }[]) => {
	if (isSubmitting.value || isDetecting.value || result.value) return

	const code = detectedCodes[0]?.rawValue?.trim()
	if (!code) return

	const now = Date.now()
	if (code === lastProcessedCode && now - lastProcessedAt < recentCodeCooldownMs) return

	isDetecting.value = true
	isSubmitting.value = true
	errorMessage.value = null

	try {
		let snapshot: string | null = null
		try {
			snapshot = captureSnapshot()
		} catch (snapshotError) {
			console.error("Snapshot capture failed:", snapshotError)
		}

		const res = await axios.post(route("grp.kiosk.camera-qr.submit", { kioskToken: props.kioskToken }), {
			code,
			snapshot,
		})

		lastProcessedCode = code
		lastProcessedAt = Date.now()

		applyResult(res.data)
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.message || trans("Invalid QR code.")
	} finally {
		isSubmitting.value = false
		isDetecting.value = false
	}
}

const onCameraError = (err: any) => {
	console.error("Camera error:", err)

	if (err?.name === "NotAllowedError") {
		cameraError.value = trans("Camera permission denied. Please enable camera access in browser settings.")
	} else if (err?.name === "NotFoundError") {
		cameraError.value = trans("No camera found")
	} else if (err?.name === "NotReadableError") {
		cameraError.value = trans("Camera already in use")
	} else if (err?.name === "NotSupportedError") {
		cameraError.value = trans("HTTPS is required for camera access")
	} else {
		cameraError.value = trans("Camera error occurred")
	}

	cameraActive.value = false
}

const toggleFullscreen = async () => {
	if (!kioskContainer.value) return

	if (!document.fullscreenElement) {
		await kioskContainer.value.requestFullscreen()
		isFullscreen.value = true
	} else {
		await document.exitFullscreen()
		isFullscreen.value = false
	}
}

const formattedClockedAt = computed(() => {
	if (!result.value) return "-"

	return new Intl.DateTimeFormat(undefined, {
		hour: "2-digit",
		minute: "2-digit",
		second: "2-digit",
	}).format(new Date(result.value.clockedAt))
})

onMounted(() => {
	if (props.mode === "barcode") window.addEventListener("keydown", handleGlobalKeydown)
})

onUnmounted(() => {
	if (props.mode === "barcode") window.removeEventListener("keydown", handleGlobalKeydown)
	if (idleTimer) clearTimeout(idleTimer)
	if (resultTimer) clearTimeout(resultTimer)
})
</script>

<template>
	<Head :title="title" />

	<div
		ref="kioskContainer"
		class="relative min-h-[100dvh] w-full flex items-center justify-center bg-gradient-to-bl from-indigo-400 to-indigo-600 px-3 py-5 sm:px-6 sm:py-6 lg:px-8">
		<div class="w-full max-w-[820px]">
			<div
				class="w-full overflow-hidden rounded-2xl border border-white/40 bg-white shadow-2xl">
				<div
					class="relative border-b border-gray-200 px-4 py-3.5 text-center sm:px-10 sm:py-6">
					<Button
						@click="toggleFullscreen"
						type="secondary"
						class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 sm:top-3 sm:right-3"
						:icon="isFullscreen ? faCompress : faExpand"
						:tooltip="trans('Toggle Fullscreen')" />

					<h1 class="px-8 text-lg sm:text-3xl font-bold text-gray-800 sm:px-0">
						{{ trans("Employee Clocking") }}
					</h1>
					<p class="mt-1 text-xs sm:text-base text-gray-500">
						{{
							mode === "pin"
								? trans("Enter your PIN to clock in or out")
								: mode === "barcode"
									? trans("Scan your barcode to clock in or out")
									: trans("Show your QR code to the camera to clock in or out")
						}}
					</p>
					<span
						class="mt-2 sm:mt-3 inline-block max-w-full truncate rounded-full bg-gray-100 px-3 py-1 text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-gray-500">
						{{ machineName }}
					</span>
				</div>

				<div class="px-4 py-4 sm:px-10 sm:py-8 text-center space-y-3.5 sm:space-y-5">
					<div
						v-if="result"
						class="flex flex-col items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-6 sm:py-10">
						<font-awesome-icon :icon="faCheckCircle" class="text-4xl sm:text-5xl text-green-500" />
						<div class="text-lg sm:text-xl font-semibold text-gray-800 break-all">
							{{ result.alias }}
						</div>
						<div class="text-2xl sm:text-3xl font-bold text-green-700">
							{{
								result.actionType === "clock_in"
									? trans("Clocked In")
									: result.actionType === "clock_out"
										? trans("Clocked Out")
										: trans("Clocked")
							}}
						</div>
						<div class="text-sm sm:text-base text-gray-500">{{ formattedClockedAt }}</div>
					</div>

					<template v-else>
						<div
							v-if="errorMessage"
							class="flex items-center justify-center gap-2 rounded-lg bg-red-50 px-3 py-2.5 text-xs sm:px-4 sm:py-3 sm:text-sm text-red-700">
							<font-awesome-icon :icon="faTimesCircle" />
							{{ errorMessage }}
						</div>

						<template v-if="mode === 'pin' && pinCharacterSet">
							<div
								class="flex min-h-[3.5rem] sm:min-h-[5rem] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-2.5 sm:px-4 sm:py-4 text-xl sm:text-3xl font-bold tracking-[0.2em] sm:tracking-widest text-gray-800">
								<span v-if="pinDisplay" class="break-all">{{ pinDisplay }}</span>
								<span
									v-else
									class="text-sm sm:text-base font-normal tracking-normal text-gray-400">
									{{ trans("Tap your PIN") }}
								</span>
							</div>

							<div class="space-y-2 sm:space-y-2.5">
								<div class="grid grid-cols-5 gap-1.5 sm:gap-2.5 sm:grid-cols-10">
									<button
										v-for="letter in pinCharacterSet.letters"
										:key="`letter-${letter}`"
										type="button"
										:disabled="isSubmitting"
										@click="tapCharacter(letter)"
										class="min-h-[2.75rem] sm:min-h-0 rounded-lg sm:rounded-xl border border-gray-200 bg-white py-2.5 sm:py-4 text-base sm:text-xl font-semibold text-gray-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 active:scale-95 disabled:opacity-50">
										{{ letter }}
									</button>
								</div>

								<div class="grid grid-cols-5 gap-1.5 sm:gap-2.5 sm:grid-cols-10">
									<button
										v-for="number in pinCharacterSet.numbers"
										:key="`number-${number}`"
										type="button"
										:disabled="isSubmitting"
										@click="tapCharacter(number)"
										class="min-h-[2.75rem] sm:min-h-0 rounded-lg sm:rounded-xl border border-gray-200 bg-white py-2.5 sm:py-4 text-base sm:text-xl font-semibold text-gray-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 active:scale-95 disabled:opacity-50">
										{{ number }}
									</button>
								</div>
							</div>

							<div
								class="grid grid-cols-2 gap-2 border-t border-gray-200 pt-4 sm:flex sm:flex-wrap sm:justify-center sm:gap-3 sm:pt-5">
								<Button
									:label="trans('Clear')"
									type="cancel"
									size="l"
									class="w-full justify-center sm:w-auto"
									:disabled="!enteredPin.length || isSubmitting"
									@click="clearPin" />
								<Button
									:icon="faBackspace"
									type="secondary"
									size="l"
									class="w-full justify-center sm:w-auto"
									:disabled="!enteredPin.length || isSubmitting"
									@click="backspace"
									:tooltip="trans('Backspace')" />
								<Button
									:label="trans(isSubmitting ? 'Checking...' : 'Clock In / Out')"
									type="primary"
									size="l"
									class="col-span-2 w-full justify-center sm:w-auto"
									:loading="isSubmitting"
									:disabled="!canSubmit"
									@click="submitPin" />
							</div>
						</template>

						<template v-else-if="mode === 'barcode'">
							<div
								class="flex flex-col items-center gap-3 sm:gap-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-6 sm:px-4 sm:py-12">
								<font-awesome-icon
									:icon="faBarcode"
									class="text-4xl sm:text-5xl text-gray-400"
									:class="{ 'animate-pulse': isSubmitting }" />
								<p class="text-sm sm:text-base font-medium text-gray-600">
									{{ isSubmitting ? trans("Checking...") : trans("Ready to scan") }}
								</p>

								<input
									id="kioskBarcodeValue"
									:value="barcodeValue"
									type="text"
									readonly
									autocomplete="off"
									:aria-label="trans('Scanned barcode')"
									class="w-full max-w-xs rounded-lg border border-gray-200 bg-white px-3 py-2.5 sm:px-4 sm:py-3 text-center text-base sm:text-lg tracking-widest text-gray-700"
									:placeholder="trans('Waiting for scan…')" />
							</div>
						</template>

						<template v-else-if="mode === 'camera_qr'">
							<div
								ref="scannerContainer"
								class="relative overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50">
								<div
									v-if="!cameraActive"
									class="flex flex-col items-center gap-3 sm:gap-4 px-3 py-10 sm:px-4 sm:py-16">
									<font-awesome-icon :icon="faCamera" class="text-4xl sm:text-5xl text-gray-400" />
									<p class="text-sm sm:text-base font-medium text-gray-600">
										{{ trans("Start the camera so employees can clock in or out by showing their QR code") }}
									</p>
									<p v-if="cameraError" class="text-xs sm:text-sm text-red-600">{{ cameraError }}</p>
									<Button :label="trans('Start Camera')" type="primary" size="l" @click="startCamera" />
								</div>

								<div v-else class="relative aspect-video w-full bg-black">
									<QrcodeStream
										@detect="onCameraDetect"
										@error="onCameraError"
										:formats="['qr_code']"
										class="h-full w-full" />

									<div
										class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-black/50 px-3 py-2">
										<span class="text-xs sm:text-sm font-medium text-white">
											{{ isSubmitting ? trans("Checking...") : trans("Show your QR code to the camera") }}
										</span>
										<Button :label="trans('Stop')" type="secondary" size="xs" @click="stopCamera" />
									</div>
								</div>
							</div>
						</template>
					</template>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
:fullscreen {
	width: 100%;
	height: 100%;
	overflow-y: auto;
}

:-webkit-full-screen {
	width: 100%;
	height: 100%;
	overflow-y: auto;
}
</style>
