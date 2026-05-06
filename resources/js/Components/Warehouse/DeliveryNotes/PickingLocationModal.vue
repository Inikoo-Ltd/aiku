<!--
* Author: Vika Aqordi
* Created on: 2026-04-22 09:25
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory } from "@fal"
import Modal from "@/Components/Utils/Modal.vue"
import { RadioButton } from "primevue"
import { trans } from "laravel-vue-i18n"
import { twBreakPoint } from "@/Composables/useWindowSize"
import { RouteParams } from "@/types/route-params"

library.add(faInventory)

defineProps<{
    isOpen: boolean
    item: any | null
    selectedLocationCode: string
}>()

const emit = defineEmits<{
    close: []
    select: [locationCode: string]
}>()

const generateLocationRoute = (location: any): string => {
    if (!location.location_slug) return "#"
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        location.location_slug,
    ])
}
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="emit('close')" width="w-full max-w-3xl" xdialogStyle="{ background: '#ffffff' }">
        <div class="text-center font-semibold mb-4 text-2xl">
            {{ trans('Location list for') }} {{ item?.org_stock_code }}
        </div>
        <div class="rounded p-1 grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div
                v-for="location in item?.locations"
                :key="location.location_code"
                class="xbg-slate-100 border border-slate-300 rounded w-full flex justify-between gap-x-3 items-center px-2 py-1"
            >
                <label :for="location.location_code" class="flex flex-wrap">
                    <span
                        v-if="location.location_code"
                        v-tooltip="location.quantity <= 0 ? trans('Location has no stock') : ''"
                        :class="location.quantity <= 0 ? 'text-gray-400' : ''"
                    >
                        <Link :href="generateLocationRoute(location)" class="bg-gradient-to-t from-yellow-300/50 to-yellow-200/50 px-1">
                            {{ location.location_code }}
                        </Link>
                    </span>
                    <span v-else class="text-gray-400 italic">({{ trans('Unknown') }})</span>
                    <span v-tooltip="trans('Total stock in this location')" class="ml-1 whitespace-nowrap text-gray-400 tabular-nums border  rounded px-1 text-xs"
                        :class="Number(location.quantity) > 0 ? 'border-gray-300' : 'border-red-300 opacity-70'"
                    >
                        <span v-if="Number(location.quantity) > 0">{{ Number(location.quantity) }} {{ trans("stocks") }}</span>
                        <span v-else class="text-red-500 italic">{{ ctrans('Empty') }}</span>
                    </span>
                </label>
                <RadioButton
                    :modelValue="selectedLocationCode"
                    @update:modelValue="(e: string) => emit('select', e)"
                    :size="twBreakPoint().includes('lg') ? undefined : 'large'"
                    :inputId="location.location_code"
                    :disabled="location.quantity <= 0"
                    name="location"
                    :value="location.location_code"
                />
            </div>
        </div>
    </Modal>
</template>
