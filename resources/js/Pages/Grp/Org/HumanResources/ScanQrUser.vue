<script setup lang="ts">
import { ref, computed } from 'vue'
import { QrcodeStream } from 'vue-qrcode-reader'
import { LMap, LTileLayer, LMarker, LTooltip } from "@vue-leaflet/vue-leaflet"
import axios from 'axios'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faTimes, faCheck } from "@fal";
import { Dialog } from 'primevue'
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(faTimes, faCheck);

interface DetectedCode {
    rawValue: string
}

const lat = ref<number | null>(null)
const lng = ref<number | null>(null)
const mapZoom = ref(15)

const cameraOn = ref(false)
const loading = ref(false)
const errorMsg = ref<string | null>(null)
const lastResult = ref<string | null>(null)

const hasLocation = computed(() => lat.value !== null && lng.value !== null)
const canOpenCamera = computed(() => hasLocation.value)

const showSuccessModal = ref(false)
const notes = ref<string>("")
const scanTime = ref<string | null>(null)
const now = new Date().toLocaleString()
const clockType = ref<'clock_in' | 'clock_out' | null>(null)
const clockingId = ref<number | null>(null)
const workingHours = ref<{ start: string; end: string } | null>(null)


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

const onDetect = async (detectedCodes: DetectedCode[]) => {
    const result = detectedCodes[0]?.rawValue

    lastResult.value = result
    loading.value = true

    try {
        const { data } = await axios.post(route('grp.models.clocking-machine.qr.validate'), {
            qr_code: result,
            latitude: lat.value,
            longitude: lng.value,
        })

        clockType.value = data.clocking?.type
        scanTime.value = useFormatTime(data.clocking?.clocked_at, { formatTime: 'hms' })
        clockingId.value = data.clocking?.id
        workingHours.value = data.working_hours ?? null
        showSuccessModal.value = true
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

const modalTitle = computed(() => {
    if (clockType.value === 'clock_in') return trans('Clock-in successful')
    if (clockType.value === 'clock_out') return trans('Clock-out successful')
    return trans('Scan successful')
})

const workingHoursFormatted = computed(() => {
    if (!workingHours.value) return '-'

    const start = useFormatTime(workingHours.value.start, { formatTime: 'HH:mm' })
    const end = useFormatTime(workingHours.value.end, { formatTime: 'HH:mm' })

    return `${start} - ${end}`
})


const submitNotes = async () => {
    if (!clockingId.value) return

    try {
        await axios.patch(
            route('grp.models.clocking-machine.clocking.notes.update', clockingId.value),
            {
                notes: notes.value
            }
        )

        showSuccessModal.value = false
        notes.value = ''
        clockingId.value = null
        notify({
            title: trans('Success'),
            text: trans(`submit notes`),
            type: 'success',
        })
    } catch (e: any) {
        notify({
            title: trans('Failed submit notes'),
            text: trans(`${e.response?.data?.message}`),
            type: 'error',
        })

        errorMsg.value = e.response?.data?.message || "Failed submit notes"
        console.error(e)
    }
}

</script>

<template>
    <div class="relative">
        <div v-if="!cameraOn" class="max-w-lg mx-auto p-6">

            <h2 class="text-2xl font-bold mb-6">{{ trans("Employee Clocking") }}</h2>

            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-500 mb-2">{{ trans("Detect Location") }}</p>
                <Button label="Detect My Location" type="secondary" @click="detectMyLocation" full />

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
                <Button @click="stopCamera" class="!text-white text-2xl" type="tertiary" :icon="faTimes" />
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

        <Dialog v-model:visible="showSuccessModal" modal :closable="false" :style="{ width: '420px' }">
            <div class="text-center space-y-4 py-4">

                <!-- ICON -->
                <div class="flex justify-center">
                    <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center">
                        <FontAwesomeIcon :icon="faCheck" class="text-4xl text-green-600" />
                    </div>
                </div>

                <!-- TITLE -->
                <h3 class="text-xl font-semibold text-gray-800">
                    {{ modalTitle }}
                </h3>

                <!-- INFO -->
                <div class="text-sm text-gray-600 space-y-2 bg-gray-50 p-3 rounded-lg">
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ trans("Schedule ") }}</span>
                        <span class="font-semibold text-gray-800">
                            {{ useFormatTime(now) ?? '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ trans("Working Office Hour ") }}</span>
                        <span class="font-semibold text-gray-800">{{ workingHoursFormatted }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ trans("Scan Time") }}</span>
                        <span class="font-semibold text-gray-800">{{ scanTime ?? '-' }}</span>
                    </div>
                </div>

                <!-- NOTES INPUT -->
                <div class="pt-3">
                    <label class="text-sm text-gray-600 block mb-1 text-left">
                        {{ trans("Notes (optional)") }}
                    </label>
                    <InputText v-model="notes" class="w-full" required placeholder="Input Notes" />
                </div>

                <!-- ACTIONS -->
                <div class="flex gap-2 pt-4">
                    <Button label="Close" type="exit" @click="showSuccessModal = false" full />
                    <Button label="Submit" type="save" @click="submitNotes" full />
                </div>
            </div>
        </Dialog>

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