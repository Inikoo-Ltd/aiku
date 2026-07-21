<script setup lang="ts">
import QrcodeVue from "qrcode.vue"
import { ref, nextTick } from "vue"
import { faExpand, faCompress, faDownload, faQrcode } from '@fortawesome/free-solid-svg-icons'
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"

defineProps<{
    data: any,
    tab?: string
}>()

type QrCodeRow = {
    id: number
    label: string | null
    hash: string
    qr_value: string
    number_clockings: number
    last_used_at: string | null
    created_at: string | null
}

const selectedQrCode = ref<QrCodeRow | null>(null)
const isQrModalOpen = ref(false)
const qrContainer = ref<HTMLElement | null>(null)
const isFullscreen = ref(false)

const openQrCode = (qrCode: QrCodeRow) => {
    selectedQrCode.value = qrCode
    isQrModalOpen.value = true
}

const closeQrCode = async () => {
    if (document.fullscreenElement) {
        await document.exitFullscreen()
    }

    isFullscreen.value = false
    isQrModalOpen.value = false
    selectedQrCode.value = null
}

const toggleFullscreen = async () => {
    await nextTick()

    if (!qrContainer.value) {
        return
    }

    if (!document.fullscreenElement) {
        await qrContainer.value.requestFullscreen()
        isFullscreen.value = true
    } else {
        await document.exitFullscreen()
        isFullscreen.value = false
    }
}

const downloadQrCode = () => {
    if (!qrContainer.value || !selectedQrCode.value) {
        return
    }

    const qrCanvas = qrContainer.value.querySelector("canvas") as HTMLCanvasElement | null

    if (!qrCanvas) {
        return
    }

    const title = trans("Employee Scan")
    const subtitle = trans("Scan QR to clock in or out")
    const footerText = selectedQrCode.value.label ?? selectedQrCode.value.hash
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
    downloadLink.download = `employee-scan-${selectedQrCode.value.hash}.png`
    downloadLink.click()
}
</script>

<template>
    <div>
        <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(label)="{ item }">
                <Button type="transparent" size="xs" :icon="faQrcode"
                    :label="item.label ?? item.hash" @click="openQrCode(item)" />
            </template>

            <template #cell(hash)="{ item }">
                <span class="font-mono text-gray-600">{{ item.hash }}</span>
            </template>

            <template #cell(last_used_at)="{ item }">
                <div class="text-gray-500">
                    {{ item.last_used_at ? useFormatTime(item.last_used_at, { formatTime: "hms" }) : "-" }}
                </div>
            </template>

            <template #cell(created_at)="{ item }">
                <div class="text-gray-500">
                    {{ item.created_at ? useFormatTime(item.created_at, { formatTime: "hms" }) : "-" }}
                </div>
            </template>
        </Table>

        <Modal :isOpen="isQrModalOpen" width="w-full max-w-2xl" @onClose="closeQrCode">
            <div v-if="selectedQrCode" class="text-center space-y-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">{{ trans("Employee Scan") }}</h2>
                    <p class="text-sm text-gray-500">{{ trans("Scan QR to clock in or out") }}</p>
                </div>

                <div ref="qrContainer"
                    class="relative flex flex-col items-center p-6 rounded-xl bg-white border border-gray-200 shadow-md">

                    <Button @click="toggleFullscreen" type="secondary"
                        class="absolute top-3 right-3 text-gray-400 hover:text-gray-700"
                        :icon="isFullscreen ? faCompress : faExpand" :tooltip="trans('Toggle Fullscreen')" />

                    <QrcodeVue :value="selectedQrCode.qr_value" :size="isFullscreen ? 600 : 380" level="H" />

                    <div class="mt-4 text-md font-semibold text-gray-700">
                        {{ selectedQrCode.label ?? selectedQrCode.hash }}
                    </div>
                </div>

                <div class="flex justify-center gap-2">
                    <Button :label="trans('Download QR Code')" @click="downloadQrCode" type="tertiary"
                        :icon="faDownload" />
                    <Button type="cancel" @click="closeQrCode" />
                </div>
            </div>
        </Modal>
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
