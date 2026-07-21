<script setup lang="ts">
import QrcodeVue from "qrcode.vue"
import jsPDF from "jspdf"
import { ref, nextTick } from "vue"
import { faExpand, faCompress, faDownload, faQrcode, faPencil, faToggleOn, faToggleOff } from '@fortawesome/free-solid-svg-icons'
import { Link, router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"

defineProps<{
    data: any,
    tab?: string
}>()

type RouteData = {
    name: string
    parameters: Record<string, string | number>
}

type QrCodeRow = {
    id: number
    label: string | null
    hash: string
    qr_value: string
    active: boolean
    number_clockings: number
    last_used_at: string | null
    created_at: string | null
    edit_route: RouteData
    toggle_active_route: RouteData
}

const selectedQrCode = ref<QrCodeRow | null>(null)
const isQrModalOpen = ref(false)
const togglingId = ref<number | null>(null)

const toggleActive = (qrCode: QrCodeRow) => {
    togglingId.value = qrCode.id

    router.patch(
        route(qrCode.toggle_active_route.name, qrCode.toggle_active_route.parameters),
        {},
        {
            preserveScroll: true,
            onFinish: () => (togglingId.value = null),
        }
    )
}
const qrContainer = ref<HTMLElement | null>(null)
const isFullscreen = ref(false)

const openQrCode = (qrCode: QrCodeRow) => {
    selectedQrCode.value = qrCode
    isQrModalOpen.value = true
}

defineExpose({ openQrCode })

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

    const upscaledQr = document.createElement("canvas")
    upscaledQr.width = 1200
    upscaledQr.height = 1200
    const upscaleContext = upscaledQr.getContext("2d")

    if (!upscaleContext) {
        return
    }

    upscaleContext.fillStyle = "#ffffff"
    upscaleContext.fillRect(0, 0, upscaledQr.width, upscaledQr.height)
    upscaleContext.imageSmoothingEnabled = false
    upscaleContext.drawImage(qrCanvas, 0, 0, upscaledQr.width, upscaledQr.height)

    const title = trans("Employee Scan")
    const subtitle = trans("Scan QR to clock in or out")
    const footerText = selectedQrCode.value.label ?? selectedQrCode.value.hash

    const pdf = new jsPDF("p", "mm", "a4")
    const pageWidth = pdf.internal.pageSize.getWidth()
    const centerX = pageWidth / 2
    const qrSizeMm = 120
    const qrY = 70

    pdf.setFont("helvetica", "bold")
    pdf.setFontSize(22)
    pdf.setTextColor(17, 24, 39)
    pdf.text(title, centerX, 40, { align: "center" })

    pdf.setFont("helvetica", "normal")
    pdf.setFontSize(13)
    pdf.setTextColor(107, 114, 128)
    pdf.text(subtitle, centerX, 52, { align: "center" })

    pdf.addImage(upscaledQr.toDataURL("image/png"), "PNG", centerX - (qrSizeMm / 2), qrY, qrSizeMm, qrSizeMm)

    pdf.setFont("helvetica", "bold")
    pdf.setFontSize(15)
    pdf.setTextColor(55, 65, 81)
    pdf.text(footerText, centerX, qrY + qrSizeMm + 15, { align: "center" })

    pdf.save(`employee-scan-${selectedQrCode.value.hash}.pdf`)
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

            <template #cell(actions)="{ item }">
                <div class="flex items-center gap-2">
                    <Link :href="route(item.edit_route.name, item.edit_route.parameters)">
                        <Button type="tertiary" size="xs" :icon="faPencil" :tooltip="trans('Edit')" />
                    </Link>
                    <Button
                        type="tertiary"
                        size="xs"
                        :icon="item.active ? faToggleOn : faToggleOff"
                        :tooltip="item.active ? trans('Deactivate') : trans('Activate')"
                        :loading="togglingId === item.id"
                        @click="toggleActive(item)" />
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
                    <Button :label="trans('Download QR Code (PDF)')" @click="downloadQrCode" type="tertiary"
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
