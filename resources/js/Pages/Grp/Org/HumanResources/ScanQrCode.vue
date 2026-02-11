<script setup lang="ts">
import QrcodeVue from "qrcode.vue"
import { computed, ref, onUnmounted } from "vue"
import { faExpand, faCompress, faStop } from '@fortawesome/free-solid-svg-icons'
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

onUnmounted(() => stopQR())
</script>

<template>
    <div class="flex items-center justify-center py-12">
        <div class="rounded-xl w-full max-w-[760px] text-center space-y-6">

            <div>
                <h2 class="text-xl font-semibold text-gray-800">{{ trans("Employee Check-In") }}</h2>
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
            </div>

            <Button :label="trans('Stop QR Code')" @click="stopQR" type="cancel" :icon="faStop" v-if="hasQR" />
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