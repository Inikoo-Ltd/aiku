<script setup lang="ts">
import QrcodeVue from "qrcode.vue"
import { computed, ref, onUnmounted } from "vue"
import { faExpand, faCompress, faStop, faDownload } from '@fortawesome/free-solid-svg-icons'
import axios from "axios";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";

const props = defineProps<{
    data: {
        slug: string,
        qr_code: string,
        status: string,
        name: string,
        type: string,
        device_name: string,
        device_uuid: string
    },
}>()

const qrContainer = ref<HTMLElement | null>(null)
const isFullscreen = ref(false)
const isGenerating = ref(false)
const qrData = ref<any>(null)
const countdown = ref(0)
const expiresAt = ref<Date | null>(null)

let qrRefreshTimer: any = null
let countdownTimer: any = null

const hasQR = computed(() => !!qrData.value)
const qrKey = ref(0)

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
    try {
        const res = await axios.get(route('grp.models.clocking-machine.qr.generate', {
            clockingMachine: props.data.slug
        }))
        if (res.data.success) {
            qrData.value = res.data.data.qr_code
            qrKey.value++
            startCountdown(res.data.data.duration_seconds)
            expiresAt.value = new Date(Date.now() + (res.data.data.duration_seconds * 1000))

            clearTimeout(qrRefreshTimer)
            qrRefreshTimer = setTimeout(fetchQR, res.data.data.duration_seconds * 1000)
        }
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
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
    }

    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
})

const formattedExpiredAt = computed(() => {
    if (!expiresAt.value) {
        return "-"
    }

    return new Intl.DateTimeFormat(undefined, {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    }).format(expiresAt.value)
})

const downloadQrCode = async () => {
    if (!qrContainer.value || !hasQR.value) {
        return
    }

    const qrCanvas = qrContainer.value.querySelector("canvas") as HTMLCanvasElement | null

    if (!qrCanvas) {
        return
    }

    const title = trans("Employee Scan")
    const subtitle = trans("Scan QR to clock in or out")
    const expiredAtText = `${trans("Expired at")}: ${formattedExpiredAt.value}`
    const qrSize = qrCanvas.width
    const canvasPadding = 48
    const textTopHeight = 120
    const textBottomHeight = 80
    const canvasWidth = Math.max(900, qrSize + (canvasPadding * 2))
    const canvasHeight = textTopHeight + qrSize + textBottomHeight + (canvasPadding * 2)
    const exportCanvas = document.createElement("canvas")
    exportCanvas.width = canvasWidth
    exportCanvas.height = canvasHeight
    const context = exportCanvas.getContext("2d")

    if (!context) {
        return
    }

    context.fillStyle = "#ffffff"
    context.fillRect(0, 0, canvasWidth, canvasHeight)

    context.textAlign = "center"
    context.fillStyle = "#111827"
    context.font = "bold 38px sans-serif"
    context.fillText(title, canvasWidth / 2, canvasPadding + 34)

    context.fillStyle = "#6b7280"
    context.font = "26px sans-serif"
    context.fillText(subtitle, canvasWidth / 2, canvasPadding + 78)

    const qrX = (canvasWidth - qrSize) / 2
    const qrY = textTopHeight + canvasPadding
    context.drawImage(qrCanvas, qrX, qrY, qrSize, qrSize)

    context.fillStyle = "#374151"
    context.font = "24px sans-serif"
    context.fillText(expiredAtText, canvasWidth / 2, qrY + qrSize + 52)

    const downloadLink = document.createElement("a")
    downloadLink.href = exportCanvas.toDataURL("image/png")
    downloadLink.download = `employee-scan-${props.data.slug}.png`
    downloadLink.click()
}

onUnmounted(() => stopQR())
</script>

<template>
    <div class="flex items-center justify-center py-12">
        <div class="rounded-xl w-full max-w-[760px] text-center space-y-6">

            <div>
                <h2 class="text-xl font-semibold text-gray-800">{{ trans("Employee Scan") }}</h2>
                <p class="text-sm text-gray-500">{{ trans("Scan QR to clock in or out") }}</p>
            </div>

            <Button :label="trans(isGenerating ? 'Generating...' : 'Generate QR Code')" :loading="isGenerating"
                :disabled="isGenerating" @click="fetchQR" type="primary" icon="fal fa-qrcode" v-if="!hasQR" />

            <!-- QR DISPLAY -->
            <div v-else ref="qrContainer"
                class="relative flex flex-col items-center p-6 rounded-xl bg-white border border-gray-200 shadow-md">

                <Button @click="toggleFullscreen" type="secondary"
                    class="absolute top-3 right-3 text-gray-400 hover:text-gray-700"
                    :icon="isFullscreen ? faCompress : faExpand" :tooltip="trans('Toggle Fullscreen')" />

                <div class="relative flex items-center justify-center"
                    :style="{ width: isFullscreen ? '600px' : '440px', height: isFullscreen ? '600px' : '440px' }">

                    <div v-if="isGenerating"
                        class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-lg z-10">
                        <LoadingIcon class="text-4xl text-gray-600" />
                    </div>

                    <QrcodeVue :value="qrData" :size="isFullscreen ? 600 : 440" level="H" />
                </div>

                <div class="mt-4 text-md font-semibold">
                    {{ trans("Expires in") }} <span class="font-semibold text-gray-700">{{ formattedCountdown }}</span>
                </div>
                <div class="text-sm text-gray-500">
                    {{ trans("Expired at") }}: {{ formattedExpiredAt }}
                </div>
            </div>

            <div v-if="hasQR" class="flex justify-center gap-2">
                <Button :label="trans('Download QR Code')" @click="downloadQrCode" type="tertiary" :icon="faDownload" />
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
