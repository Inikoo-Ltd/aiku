<script setup lang="ts">
import QrcodeVue from "qrcode.vue"
import { computed, ref, onUnmounted } from "vue"
import { faExpand, faCompress } from '@fortawesome/free-solid-svg-icons'
import axios from "axios";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n";

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

let qrRefreshTimer: any = null
let countdownTimer: any = null
let validateTimer: any = null

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
    try {
        const res = await axios.get(route('grp.models.clocking-machine.qr.generate', {
            clockingMachine: props.data.slug
        }))
        console.log("QR fetch response", res)
        if (res.data.success) {
            qrData.value = res.data.data.qr_code
            console.log("QR Data", qrData.value)
            startCountdown(res.data.data.duration_seconds)

            clearTimeout(qrRefreshTimer)
            qrRefreshTimer = setTimeout(fetchQR, 5000)
        }
    } finally {
        isGenerating.value = false
    }
}

const validateQR = async () => {
    if (!qrData.value) return

    try {
        const res = await axios.post(route('grp.models.clocking-machine.qr.validate'), {
            qr_code: qrData.value,
        })
        console.log("QR validate response", res)
        if (res.data.success) {
            stopQR()
            // Handle successful validation (e.g., show a message)
        }
    } catch (error) {
        console.error("QR validation error", error)
    }
}

const stopQR = () => {
    clearTimeout(qrRefreshTimer)
    clearInterval(countdownTimer)
    clearInterval(validateTimer)

    qrData.value = null
    countdown.value = 0
}
onUnmounted(() => stopQR())
</script>

<template>
    <div class="flex items-center justify-center py-12">
        <div class="bg-white border border-gray-200 rounded-xl shadow-md p-8 w-[460px] text-center space-y-6">

            <div>
                <h2 class="text-xl font-semibold text-gray-800">Employee Check-In</h2>
                <p class="text-sm text-gray-500">Scan QR to clock in or out</p>
            </div>

            <!-- GENERATE BUTTON -->
            <Button :label="trans(isGenerating ? 'Generating...' : 'Generate QR Code')" :loading="isGenerating"
                :disabled="isGenerating" @click="fetchQR" type="primary" icon="fal fa-qrcode" v-if="!hasQR" />

            <!-- QR DISPLAY -->
            <div v-else ref="qrContainer"
                class="relative flex flex-col items-center justify-center bg-white p-6 rounded-xl border border-gray-200">

                <Button @click="toggleFullscreen" type="secondary"
                    class="absolute top-3 right-3 text-gray-400 hover:text-gray-700"
                    :icon="isFullscreen ? faCompress : faExpand" :tooltip="trans('Toggle Fullscreen')" />

                <QrcodeVue :value="qrData.value" :size="isFullscreen ? 420 : 240" level="H" />

                <div class="mt-4 text-sm text-gray-500">
                    Expires in <span class="font-semibold text-gray-700">{{ countdown }}s</span>
                </div>
            </div>

            <!-- STOP BUTTON -->
            <Button :label="trans('Stop QR Code')" @click="stopQR" type="cancel" v-if="hasQR" />
        </div>
    </div>

</template>
