
<script setup lang="ts">
import QrcodeVue from "qrcode.vue"
import { Head } from "@inertiajs/vue3"
import { computed, ref, onUnmounted } from "vue"
import { faExpand, faCompress, faStop } from "@fortawesome/free-solid-svg-icons"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import BlankLayout from "@/Layouts/BlankLayout.vue"

defineOptions({ layout: BlankLayout })

const props = defineProps<{
	title: string
	machineName: string
	kioskToken: string
}>()

const qrContainer = ref<HTMLElement | null>(null)
const isFullscreen = ref(false)
const isGenerating = ref(false)
const qrData = ref<any>(null)
const countdown = ref(0)
const expiresAt = ref<Date | null>(null)
const errorMessage = ref<string | null>(null)

let qrRefreshTimer: any = null
let countdownTimer: any = null

const hasQR = computed(() => !!qrData.value)

const toggleFullscreen = async () => {
	if (!qrContainer.value) return

	if (!document.fullscreenElement) {
		await qrContainer.value.requestFullscreen()
		isFullscreen.value = true
	} else {
		await document.exitFullscreen()
		isFullscreen.value = false
	}
}

const startCountdown = (seconds: number) => {
	countdown.value = seconds
	clearInterval(countdownTimer)

	countdownTimer = setInterval(() => {
		countdown.value--
		if (countdown.value <= 0) clearInterval(countdownTimer)
	}, 1000)
}

const fetchQR = async () => {
	isGenerating.value = true
	errorMessage.value = null
	try {
		const res = await axios.get(route("grp.kiosk.qr", { kioskToken: props.kioskToken }))
		if (res.data.success) {
			qrData.value = res.data.data.qr_code
			startCountdown(res.data.data.duration_seconds)
			expiresAt.value = new Date(Date.now() + res.data.data.duration_seconds * 1000)

			clearTimeout(qrRefreshTimer)
			qrRefreshTimer = setTimeout(fetchQR, res.data.data.duration_seconds * 1000)
		} else {
			errorMessage.value = res.data.message || trans("Unable to generate QR code.")
		}
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.message || trans("Unable to generate QR code.")
	} finally {
		isGenerating.value = false
	}
}

const stopQR = () => {
	clearTimeout(qrRefreshTimer)
	clearInterval(countdownTimer)

	qrData.value = null
	countdown.value = 0
	expiresAt.value = null
}

const formattedCountdown = computed(() => {
	const total = countdown.value

	const hours = Math.floor(total / 3600)
	const minutes = Math.floor((total % 3600) / 60)
	const seconds = total % 60

	if (hours > 0) {
		return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`
	}

	return `${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`
})

const formattedExpiredAt = computed(() => {
	if (!expiresAt.value) {
		return "-"
	}

	return new Intl.DateTimeFormat(undefined, {
		hour: "2-digit",
		minute: "2-digit",
		second: "2-digit",
	}).format(expiresAt.value)
})

onUnmounted(() => stopQR())
</script>

<template>
	<Head :title="title" />

	<div class="w-full max-w-[820px] px-4">
		<div class="rounded-2xl bg-white p-8 shadow-xl text-center space-y-6">
			<div>
				<h1 class="text-2xl font-bold text-gray-800">{{ trans("Employee Scan") }}</h1>
				<p class="text-sm text-gray-500">{{ trans("Scan QR to clock in or out") }}</p>
				<p class="mt-1 text-xs font-medium uppercase tracking-wider text-gray-400">{{ machineName }}</p>
			</div>

			<div v-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
				{{ errorMessage }}
			</div>

			<Button
				v-if="!hasQR"
				:label="trans(isGenerating ? 'Generating...' : 'Generate QR Code')"
				:loading="isGenerating"
				:disabled="isGenerating"
				@click="fetchQR"
				type="primary"
				icon="fal fa-qrcode" />

			<!-- QR DISPLAY -->
			<div
				v-else
				ref="qrContainer"
				class="relative flex flex-col items-center rounded-xl border border-gray-200 bg-white p-6 shadow-md">
				<Button
					@click="toggleFullscreen"
					type="secondary"
					class="absolute top-3 right-3 text-gray-400 hover:text-gray-700"
					:icon="isFullscreen ? faCompress : faExpand"
					:tooltip="trans('Toggle Fullscreen')" />

				<div
					class="relative flex items-center justify-center"
					:style="{ width: isFullscreen ? '600px' : '440px', height: isFullscreen ? '600px' : '440px' }">
					<div
						v-if="isGenerating"
						class="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/70">
						<LoadingIcon class="text-4xl text-gray-600" />
					</div>

					<QrcodeVue :value="qrData" :size="isFullscreen ? 600 : 440" level="H" />
				</div>

				<div class="mt-4 text-md font-semibold">
					{{ trans("Expires in") }}
					<span class="font-semibold text-gray-700">{{ formattedCountdown }}</span>
				</div>
				<div class="text-sm text-gray-500">{{ trans("Expires at") }}: {{ formattedExpiredAt }}</div>
			</div>

			<div v-if="hasQR" class="flex justify-center">
				<Button :label="trans('Stop QR Code')" @click="stopQR" type="cancel" :icon="faStop" />
			</div>
		</div>
	</div>
</template>

<style scoped>
:fullscreen {
	display: flex;
	justify-content: center;
	align-items: center;
	background: white;
}

:-webkit-full-screen {
	display: flex;
	justify-content: center;
	align-items: center;
	background: white;
}
</style>
