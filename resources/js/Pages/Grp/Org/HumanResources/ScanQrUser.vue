<script setup lang="ts">
import { ref, computed } from 'vue'
import { QrcodeStream } from 'vue-qrcode-reader'
import { LMap, LTileLayer, LMarker, LTooltip } from "@vue-leaflet/vue-leaflet"
import axios from 'axios'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faArrowLeft } from "@fal";

library.add(faArrowLeft);

const type = ref<'check_in' | 'check_out' | null>(null)
const lat = ref<number | null>(null)
const lng = ref<number | null>(null)
const mapZoom = ref(15)

const cameraOn = ref(false)
const loading = ref(false)
const errorMsg = ref<string | null>(null)
const lastResult = ref<string | null>(null)

const hasLocation = computed(() => lat.value !== null && lng.value !== null)
const canOpenCamera = computed(() => hasLocation.value && type.value)

const detectMyLocation = () => {
    errorMsg.value = null
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            lat.value = pos.coords.latitude
            lng.value = pos.coords.longitude
        },
        () => errorMsg.value = "Location permission denied"
    )
}

const startCamera = async () => {
    if (!canOpenCamera.value) {
        console.warn("Camera blocked — missing type or location")
        return
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true })

        stream.getTracks().forEach(t => t.stop())

        cameraOn.value = true
    } catch (err) {
        console.error("Camera permission error:", err)
        errorMsg.value = "Camera permission denied or not supported"
    }
}

const stopCamera = () => cameraOn.value = false

interface DetectedCode {
    rawValue: string
}

const onDetect = async (detectedCodes: DetectedCode[]) => {
    const result = detectedCodes[0]?.rawValue
    // if (!result || result === lastResult.value) return

    lastResult.value = result
    loading.value = true

    try {
        await axios.post(route('grp.models.clocking-machine.qr.validate'), {
            qr_code: result,
            latitude: lat.value,
            longitude: lng.value,
            type: type.value
        })

        notify({
            title: trans('Success'),
            text: trans('Success Scan QR'),
            type: 'success',
        })

        stopCamera()
    } catch (e: any) {
        notify({
            title: trans('Failed Scan QR'),
            text: trans(`${e.response?.data?.message}`),
            type: 'error',
        })

        errorMsg.value = e.response?.data?.message || "QR invalid"
        stopCamera()
    } finally {
        loading.value = false
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

</script>

<template>
    <div class="relative">
        <div v-if="!cameraOn" class="max-w-lg mx-auto p-6">

            <h2 class="text-2xl font-bold mb-6">{{ trans("Employee Clocking") }}</h2>

            <!-- STEP 1 -->
            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-500 mb-2">{{ trans("STEP 1 — Choose Action") }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <Button label="Check In" type="green"
                        :class="type === 'check_in' ? 'ring-4 ring-green-200' : 'opacity-70'" @click="type = 'check_in'"
                        full />
                    <Button label="Check Out" type="red"
                        :class="type === 'check_out' ? 'ring-4 ring-red-200' : 'opacity-70'" @click="type = 'check_out'"
                        full />
                </div>
            </div>

            <!-- STEP 2 -->
            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-500 mb-2">{{ trans("STEP 2 — Your Location") }}</p>
                <Button label="Detect My Location" type="secondary" @click="detectMyLocation" :disabled="!type" full />

                <div v-if="hasLocation" class="mt-3 h-48 rounded-xl overflow-hidden border shadow">
                    <LMap :zoom="mapZoom" :center="[lat, lng]" style="height:100%">
                        <LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
                        <LMarker :lat-lng="[lat, lng]">
                            <LTooltip>
                                Lat: {{ lat?.toFixed(6) }}<br>Lng: {{ lng?.toFixed(6) }}
                            </LTooltip>
                        </LMarker>
                    </LMap>
                </div>
            </div>

            <!-- OPEN CAMERA -->
            <Button label="Open Camera" type="primary" @click="startCamera" :disabled="!canOpenCamera" full />
        </div>

        <div v-else class="fixed inset-0 bg-black z-50 flex flex-col">

            <div class="flex justify-between items-center text-white p-4">
                <h3 class="font-semibold">{{ trans("Scan QR Code") }}</h3>
                <Button @click="stopCamera" class="text-2xl" type="tertiary" :icon="faArrowLeft" />
            </div>

            <div class="flex-1 flex items-center justify-center relative">
                <div class="w-full max-w-xl aspect-square relative">

                    <QrcodeStream @detect="onDetect" @error="onStreamError" :paused="loading" :formats="['qr_code']" />

                    <!-- TARGET OVERLAY -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none ">
                        <div class="scanner-frame">

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

        <div v-if="errorMsg" class="text-red-500 text-sm mt-2 text-center">{{ errorMsg }}</div>
    </div>
</template>
<style scoped>
.scanner-frame {
    position: relative;
    width: 260px;
    height: 260px;
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