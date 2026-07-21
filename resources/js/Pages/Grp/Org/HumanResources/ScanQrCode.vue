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
        device_uuid: string,
        kiosk_token?: string | null,
        kiosk_url?: string | null
    },
}>()

const kioskUrl = ref<string | null>(props.data.kiosk_url ?? null)
const isSettingKioskToken = ref(false)
const kioskCopied = ref(false)

const setKioskToken = async (revoke: boolean) => {
    if (revoke && !window.confirm(trans("Revoke the kiosk link? Any tablet using it will stop working."))) {
        return
    }

    isSettingKioskToken.value = true
    try {
        const res = await axios.post(
            route('grp.models.clocking-machine.kiosk_token.set', { clockingMachine: props.data.slug }),
            { revoke }
        )
        kioskUrl.value = res.data.kiosk_url ?? null
        kioskCopied.value = false
    } finally {
        isSettingKioskToken.value = false
    }
}

const copyKioskUrl = async () => {
    if (!kioskUrl.value) return
    await navigator.clipboard.writeText(kioskUrl.value)
    kioskCopied.value = true
    setTimeout(() => (kioskCopied.value = false), 2000)
}

const qrContainer = ref<HTMLElement | null>(null)
const isFullscreen = ref(false)
const isGenerating = ref(false)
const qrData = ref<string | null>(null)
const qrLabel = ref<string | null>(null)
const countdown = ref(0)
const expiresAt = ref<Date | null>(null)

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
    if (isGenerating.value || hasQR.value) {
        return
    }

    isGenerating.value = true
    try {
        const res = await axios.get(route('grp.models.clocking-machine.qr.generate', {
            clockingMachine: props.data.slug
        }))
        if (res.data.success) {
            const { label, hash, expires_at } = res.data.data

            qrLabel.value = label ?? null
            qrData.value = [label, hash].filter(Boolean).join(':')
            qrKey.value++

            expiresAt.value = expires_at ? new Date(expires_at) : null

            if (expiresAt.value) {
                startCountdown(Math.max(0, Math.round((expiresAt.value.getTime() - Date.now()) / 1000)))
            }
        }
    } finally {
        isGenerating.value = false
    }
}

const stopQR = () => {
    clearInterval(countdownTimer)

    qrData.value = null
    qrLabel.value = null
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
    const footerText = expiresAt.value
        ? `${trans("Expires at")}: ${formattedExpiredAt.value}`
        : (qrLabel.value ?? "")
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
    context.fillText(footerText, canvasWidth / 2, qrY + qrSize + 52)

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

                    <QrcodeVue :key="qrKey" :value="qrData ?? ''" :size="isFullscreen ? 600 : 440" level="H" />
                </div>

                <!-- <div v-if="qrLabel" class="mt-4 text-md font-semibold text-gray-700">
                    {{ qrLabel }}
                </div> -->

                <template v-if="expiresAt">
                    <div class="mt-2 text-md font-semibold">
                        {{ trans("Expires in") }} <span class="font-semibold text-gray-700">{{ formattedCountdown }}</span>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ trans("Expires at") }}: {{ formattedExpiredAt }}
                    </div>
                </template>
            </div>

            <div v-if="hasQR" class="flex justify-center gap-2">
                <Button :label="trans('Download QR Code')" @click="downloadQrCode" type="tertiary" :icon="faDownload" />
                <Button :label="trans('Stop QR Code')" @click="stopQR" type="cancel" :icon="faStop" />
            </div>

            <!-- Kiosk mode: login free URL for a wall tablet -->
            <div class="mt-8 rounded-xl border border-gray-200 bg-gray-50 p-5 text-left">
                <h3 class="text-base font-semibold text-gray-800">{{ trans("Kiosk mode") }}</h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ trans("Open this link on the tablet to show the QR code without logging in. Keep it secret: anyone with the link can display a valid QR code.") }}
                </p>

                <div v-if="kioskUrl" class="mt-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <input
                            :value="kioskUrl"
                            readonly
                            class="w-full rounded-md border-gray-300 bg-white text-sm text-gray-700 shadow-sm" />
                        <Button
                            :label="trans(kioskCopied ? 'Copied' : 'Copy')"
                            @click="copyKioskUrl"
                            type="tertiary" />
                    </div>
                    <div class="flex gap-2">
                        <Button
                            :label="trans('Regenerate link')"
                            :loading="isSettingKioskToken"
                            :disabled="isSettingKioskToken"
                            @click="setKioskToken(false)"
                            type="secondary" />
                        <Button
                            :label="trans('Revoke link')"
                            :loading="isSettingKioskToken"
                            :disabled="isSettingKioskToken"
                            @click="setKioskToken(true)"
                            type="cancel" />
                    </div>
                </div>

                <div v-else class="mt-4">
                    <Button
                        :label="trans('Generate kiosk link')"
                        :loading="isSettingKioskToken"
                        :disabled="isSettingKioskToken"
                        @click="setKioskToken(false)"
                        type="primary" />
                </div>
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
