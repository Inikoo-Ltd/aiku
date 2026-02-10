<script setup lang="ts">
import { ref, computed } from 'vue'
import { QrcodeStream } from 'vue-qrcode-reader'
import { LMap, LTileLayer, LMarker, LTooltip } from "@vue-leaflet/vue-leaflet"
import axios from 'axios'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'

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

// const startCamera = () => { if (canOpenCamera.value) cameraOn.value = true }
const startCamera = async () => {
    console.log("Start camera clicked")
    console.log("Type:", type.value)
    console.log("LatLng:", lat.value, lng.value)

    if (!canOpenCamera.value) {
        console.warn("Camera blocked — missing type or location")
        return
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true })
        console.log("Camera permission granted", stream)

        // Stop dummy stream (vue-qrcode-reader will reopen)
        stream.getTracks().forEach(t => t.stop())

        cameraOn.value = true
    } catch (err) {
        console.error("Camera permission error:", err)
        errorMsg.value = "Camera permission denied or not supported"
    }
}

const stopCamera = () => cameraOn.value = false

const onDecode = async (result: string) => {
    if (result === lastResult.value) return
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

// const onInit = (p: Promise<void>) => p.catch(() => errorMsg.value = "Camera error")
const onInit = (promise: Promise<void>) => {
    promise
        .then(() => console.log("QrcodeStream camera initialized"))
        .catch((err) => {
            console.error("QrcodeStream init error:", err)

            if (err.name === "NotAllowedError") {
                errorMsg.value = "Camera permission denied"
            } else if (err.name === "NotFoundError") {
                errorMsg.value = "No camera found"
            } else if (err.name === "NotSupportedError") {
                errorMsg.value = "HTTPS required for camera"
            } else {
                errorMsg.value = "Camera error"
            }
        })
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
                <button @click="stopCamera" class="text-2xl">✕</button>
            </div>

            <div class="flex-1 flex items-center justify-center">
                <div class="w-full max-w-xl aspect-square">
                    <QrcodeStream @decode="onDecode" @init="onInit" :paused="loading" />
                </div>
            </div>

            <div class="text-center text-white pb-6 text-sm opacity-70">
                {{ trans("Align QR code inside the frame") }}
            </div>

        </div>

        <div v-if="errorMsg" class="text-red-500 text-sm mt-2 text-center">{{ errorMsg }}</div>
    </div>
</template>
