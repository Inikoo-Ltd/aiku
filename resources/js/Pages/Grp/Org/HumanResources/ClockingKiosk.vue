
<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, onMounted, onUnmounted, ref } from "vue"
import { faExpand, faCompress, faBackspace, faCheckCircle, faTimesCircle, faBarcode } from "@fortawesome/free-solid-svg-icons"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import BlankLayout from "@/Layouts/BlankLayout.vue"

defineOptions({ layout: BlankLayout })

const props = defineProps<{
	title: string
	machineName: string
	kioskToken: string
	mode: "pin" | "barcode"
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
		class="flex w-full items-center justify-center bg-gradient-to-bl from-indigo-400 to-indigo-600 p-4">
		<div class="w-full max-w-[820px]">
			<div class="relative rounded-2xl bg-white p-6 sm:p-10 shadow-xl text-center space-y-5 sm:space-y-7">
				<Button
					@click="toggleFullscreen"
					type="secondary"
					class="absolute top-3 right-3 text-gray-400 hover:text-gray-700"
					:icon="isFullscreen ? faCompress : faExpand"
					:tooltip="trans('Toggle Fullscreen')" />

				<div>
					<h1 class="text-3xl font-bold text-gray-800">{{ trans("Employee Clocking") }}</h1>
					<p class="mt-1 text-base text-gray-500">
						{{ mode === "pin" ? trans("Enter your PIN to clock in or out") : trans("Scan your barcode to clock in or out") }}
					</p>
					<p class="mt-2 text-xs font-medium uppercase tracking-wider text-gray-400">{{ machineName }}</p>
				</div>

				<!-- SUCCESS RESULT -->
				<div v-if="result" class="rounded-xl border border-green-200 bg-green-50 px-4 py-10 space-y-2">
					<font-awesome-icon :icon="faCheckCircle" class="text-5xl text-green-500" />
					<div class="text-xl font-semibold text-gray-800">{{ result.alias }}</div>
					<div class="text-3xl font-bold text-green-700">
						{{ result.actionType === "clock_in" ? trans("Clocked In") : result.actionType === "clock_out" ? trans("Clocked Out") : trans("Clocked") }}
					</div>
					<div class="text-base text-gray-500">{{ formattedClockedAt }}</div>
				</div>

				<template v-else>
					<div v-if="errorMessage" class="flex items-center justify-center gap-2 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
						<font-awesome-icon :icon="faTimesCircle" />
						{{ errorMessage }}
					</div>

					<!-- PIN ENTRY -->
					<template v-if="mode === 'pin' && pinCharacterSet">
						<div class="flex min-h-[3.5rem] items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-3xl tracking-widest">
							<span v-if="pinDisplay">{{ pinDisplay }}</span>
							<span v-else class="text-gray-300">{{ trans("Tap your PIN") }}</span>
						</div>

						<div class="space-y-2.5">
							<div class="grid grid-cols-5 gap-2.5 sm:grid-cols-10">
								<button
									v-for="letter in pinCharacterSet.letters"
									:key="`letter-${letter}`"
									type="button"
									:disabled="isSubmitting"
									@click="tapCharacter(letter)"
									class="rounded-xl border border-gray-200 bg-white py-4 text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-100 active:scale-95 disabled:opacity-50">
									{{ letter }}
								</button>
							</div>

							<div class="grid grid-cols-5 gap-2.5 sm:grid-cols-10">
								<button
									v-for="number in pinCharacterSet.numbers"
									:key="`number-${number}`"
									type="button"
									:disabled="isSubmitting"
									@click="tapCharacter(number)"
									class="rounded-xl border border-gray-200 bg-white py-4 text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-100 active:scale-95 disabled:opacity-50">
									{{ number }}
								</button>
							</div>
						</div>

						<div class="flex justify-center gap-3 pt-1">
							<Button :label="trans('Clear')" type="cancel" :disabled="!enteredPin.length || isSubmitting" @click="clearPin" />
							<Button :icon="faBackspace" type="secondary" :disabled="!enteredPin.length || isSubmitting" @click="backspace" :tooltip="trans('Backspace')" />
							<Button
								:label="trans(isSubmitting ? 'Checking...' : 'Clock In / Out')"
								type="primary"
								:loading="isSubmitting"
								:disabled="!canSubmit"
								@click="submitPin" />
						</div>
					</template>

					<!-- BARCODE SCAN -->
					<template v-else-if="mode === 'barcode'">
						<div class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-12">
							<font-awesome-icon :icon="faBarcode" class="text-5xl text-gray-400" :class="{ 'animate-pulse': isSubmitting }" />
							<p class="text-base font-medium text-gray-600">
								{{ isSubmitting ? trans("Checking...") : trans("Ready to scan") }}
							</p>

							<input
								:value="barcodeValue"
								type="text"
								readonly
								autocomplete="off"
								class="w-full max-w-xs rounded-lg border border-gray-200 px-4 py-3 text-center text-lg tracking-widest text-gray-700"
								:placeholder="trans('Waiting for scan…')" />
						</div>
					</template>
				</template>
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
