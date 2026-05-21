<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { computed, inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInventory, faListOl } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { String } from "lodash"

library.add(faInventory, faListOl)

const props = defineProps<{
    locations: any[]
    selectedOrgStockId: string
    warehouseArea?: String
    warehouse_slug?: string
}>()

const emit = defineEmits<{
    openLocationModal: []
}>()

const locale = inject("locale", aikuLocaleStructure)

const currentLocation = computed(() => {
    return props.locations.find(x => x.location_code == props.selectedOrgStockId) || props.locations[0]
})

const locationHref = computed(() => {
    if (!currentLocation.value?.location_slug) {
        return "#"
    }
    
    const warehouseParam = currentLocation.value.warehouse_slug || currentLocation.value.warehouse_id || props.warehouse_slug ||'DEFAULT_WAREHOUSE'; 

    return route("grp.org.warehouses.show.infrastructure.locations.show", {
        organisation: route().params["organisation"],
        warehouse: warehouseParam,
        location: currentLocation.value.location_slug
    });
})
</script>

<template>
    <Transition name="spin-to-down">
        <div :key="currentLocation?.location_code">
            <span
                v-if="locations.length > 1"
                @click="console.log('open modal'), emit('openLocationModal')"
                v-tooltip="`Other ${locations.length - 1} locations`"
                class="mr-1 cursor-pointer hover:bg-orange-50 whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-orange-300 rounded px-1"
            >
                <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                {{ locations.length - 1 }}
            </span>

            <span v-if="currentLocation" class="text-base">
                <Link v-tooltip="`${warehouseArea}`" :href="locationHref" class="secondaryLink">
                    {{ currentLocation.location_code }}
                </Link>
            </span>

            <span v-else v-tooltip="ctrans('Unknown location')" class="text-gray-400 italic">
                ({{ ctrans("Unknown") }})
            </span>

            <span
                v-tooltip="ctrans(':stockAvailable stock available on location :stockLocation', { stockAvailable: locale.number(currentLocation?.quantity || 0), stockLocation: currentLocation?.location_code || '' })"
                class="align-middle whitespace-nowrap text-base py-0.5 xopacity-70 tabular-nums xborder border-gray-300 rounded xpx-1"
            >
                (<span class="text-lg font-bold">
                    <FractionDisplay
                        v-if="currentLocation?.quantity_fractional"
                        :fractionData="currentLocation?.quantity_fractional"
                    />
                    <template v-else>
                        {{ locale.number(currentLocation?.quantity ?? 0) }}
                    </template>
                </span>
                <span class="text-sm ml-1">{{ ctrans("stocks") }}</span>)
            </span>
        </div>
    </Transition>
</template>
