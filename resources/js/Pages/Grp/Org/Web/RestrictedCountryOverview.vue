<!--
  - Author: stewicca <wiccaalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { LMap, LTileLayer, LCircleMarker, LTooltip } from "@vue-leaflet/vue-leaflet"
import { onMounted, nextTick, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"

interface MapPoint {
    latitude: number
    longitude: number
    country: string
    city: string | null
    postcode: string | null
    was_blocked: boolean
    number_requests: number
    last_request_at: string | null
}

const props = defineProps<{
    data: MapPoint[]
}>()

const flagUrl = (code: string) => `/flags/${code.toLowerCase()}.png`
const locationLabel = (point: MapPoint) => point.city || point.postcode || point.country

const map = ref<any>(null)
const onMapReady = (leafletMap: any) => {
    map.value = leafletMap
    nextTick(() => {
        leafletMap.invalidateSize()
        leafletMap.fitWorld()
    })
}
onMounted(() => nextTick(() => map.value?.invalidateSize()))
watch(() => props.data, () => nextTick(() => map.value?.invalidateSize()))
</script>

<template>
    <div class="mt-5">
        <div class="relative rounded-lg overflow-hidden border border-gray-200 h-[600px]">
            <l-map
                :zoom="3"
                :center="[20, 0]"
                :min-zoom="3"
                :max-zoom="12"
                :max-bounds="[[-90, -180], [90, 180]]"
                class="h-full w-full"
                @ready="onMapReady"
            >
                <l-tile-layer
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    attribution="&copy; OpenStreetMap contributors"
                    :no-wrap="true"
                />
                <l-circle-marker
                    v-for="(point, i) in data"
                    :key="i"
                    :lat-lng="[point.latitude, point.longitude]"
                    :radius="7"
                    :weight="1"
                    :color="point.was_blocked ? '#dc2626' : '#16a34a'"
                    :fill-color="point.was_blocked ? '#dc2626' : '#16a34a'"
                    :fill-opacity="0.8"
                >
                    <l-tooltip>
                        <div class="flex items-center gap-2">
                            <img
                                :src="flagUrl(point.country)"
                                :alt="point.country"
                                class="h-3 w-auto rounded-[2px]"
                            />
                            <span class="font-semibold">{{ locationLabel(point) }}</span>
                            <span
                                class="inline-flex h-2 w-2 rounded-full"
                                :class="point.was_blocked ? 'bg-red-500' : 'bg-green-500'"
                            />
                            <span>{{ point.was_blocked ? trans('Blocked') : trans('Allowed') }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ point.number_requests }} {{ trans('requests') }}
                        </div>
                    </l-tooltip>
                </l-circle-marker>
            </l-map>

            <div class="absolute bottom-3 left-3 z-[500] bg-white/90 backdrop-blur rounded-md shadow px-3 py-2 text-xs flex items-center gap-4">
                <span class="flex items-center gap-1.5">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-red-500" />
                    {{ trans('Blocked') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-green-500" />
                    {{ trans('Allowed') }}
                </span>
            </div>
        </div>
    </div>
</template>
