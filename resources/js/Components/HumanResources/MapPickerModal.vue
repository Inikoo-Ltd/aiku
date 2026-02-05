<script setup lang="ts">
import { ref, watch } from "vue"
import { LMap, LTileLayer, LMarker, LTooltip } from "@vue-leaflet/vue-leaflet"
import axios from "axios"
import { debounce } from "lodash"
import Button from "../Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { faMapPin } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faMapPin)
const props = defineProps<{
    modelValue: boolean
    lat?: number | null
    lng?: number | null
}>()

const emit = defineEmits(["update:modelValue", "selected"])

const mapRef = ref()
const filter = ref({
    lat: props.lat ?? null,
    lng: props.lng ?? null,
    zoom: 13,
    location: "",
    loadingMap: false,
    lastSource: "map",
    resolved: false
})

watch(() => props.lat, v => {
    if (v !== undefined) {
        filter.value.lat = v
    }
})
watch(() => props.lng, v => {
    if (v !== undefined) {
        filter.value.lng = v
    }
})

const close = () => emit("update:modelValue", false)

const getLatLngToLocation = async (filter: any, forceMode?: 'forward' | 'reverse') => {
    const v = filter.value
    let params: any = {}

    const mode = forceMode || (v.lastSource === 'map' ? 'reverse' : 'forward')

    if (mode === 'reverse') {
        if (!v.lat || !v.lng) return
        params.latitude = v.lat
        params.longitude = v.lng
    } else {
        if (!v.location) return
        params.location = v.location
    }

    v.loadingMap = true

    try {
        const res = await axios.get(route('grp.json.get_geocode'), { params })
        const data = res.data


        if (data.latitude && data.longitude) {
            v.lat = Number(data.latitude)
            v.lng = Number(data.longitude)
        }

        if (data.formatted_address) {
            v.location = data.formatted_address
        }

        v.resolved = true
    } finally {
        v.loadingMap = false
    }
}

const debouncedReverseGeocode = debounce(getLatLngToLocation, 500)

const onMapClick = (e: any) => {
    filter.value.lat = e.latlng.lat
    filter.value.lng = e.latlng.lng
    filter.value.lastSource = "map"
    debouncedReverseGeocode(filter)
}

const onMarkerDrag = (e: any) => {
    const pos = e.target.getLatLng()
    filter.value.lat = pos.lat
    filter.value.lng = pos.lng
    filter.value.lastSource = "map"
    debouncedReverseGeocode(filter)
}

const detectMyLocation = () => {
    if (!navigator.geolocation) {
        notify({
            title: trans("Error"),
            text: trans("Browser does not support GPS, Please allow GPS in your browser"),
            type: "error",
        })
        return
    }
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            filter.value.lat = pos.coords.latitude
            filter.value.lng = pos.coords.longitude
            filter.value.zoom = 15
            filter.value.lastSource = "map"
            debouncedReverseGeocode(filter)
        },
        () => {
            notify({
                title: trans("Error"),
                text: trans("Browser does not support GPS, Please allow GPS in your browser"),
                type: "error",
            })
        }
    )
}

const confirmLocation = () => {
    emit("selected", {
        lat: filter.value.lat,
        lng: filter.value.lng,
        address: filter.value.location
    })
    close()
}
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl p-5 space-y-4 animate-fadeIn">
            <div class="flex justify-between items-center border-b pb-2">
                <h3 class="font-semibold text-lg">Pick Location</h3>
                <Button type="exit" @click="close">✕</Button>
            </div>

            <div class="h-96 flex items-center justify-center border-2 border-dashed rounded-lg bg-gray-50"
                v-if="!filter.lat || !filter.lng">

                <div class="text-center space-y-3 max-w-xs">
                    <div class="text-4xl">
                        <font-awesome-icon :icon="faMapPin" />
                    </div>
                    <p class="text-sm text-gray-600">
                        The location has not been selected. <br>
                        Please press <b>Use My Location</b><br>
                        or click on the map after the location appears.
                    </p>

                    <Button label="Use My Location" :type="'secondary'" :icon="faMapPin" @click="detectMyLocation" />
                </div>
            </div>


            <div class="h-96" v-if="filter.lat && filter.lng">
                <l-map v-model:zoom="filter.zoom" :center="[filter.lat, filter.lng]" class="h-full w-full"
                    @click="onMapClick">
                    <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
                    <l-marker :lat-lng="[filter.lat, filter.lng]" draggable @dragend="onMarkerDrag">
                        <l-tooltip :permanent="true" direction="top">
                            📍 {{ filter.location || "Resolving address..." }}
                        </l-tooltip>
                    </l-marker>
                </l-map>
            </div>

            <div class="bg-gray-50 border rounded p-2 text-xs text-gray-600">
                <div><b>Latitude:</b> {{ filter.lat }}</div>
                <div><b>Longitude:</b> {{ filter.lng }}</div>
                <div><b>Address:</b> {{ filter.location || "Find Address..." }}</div>
            </div>

            <div class="flex justify-end items-center gap-2">
                <div class="flex gap-2">
                    <Button label="Cancel" type="exit" @click="close" />
                    <Button label="Use This Location" type="primary" @click="confirmLocation" />
                </div>
            </div>
        </div>
    </div>
</template>
