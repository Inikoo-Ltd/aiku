<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import FractionDisplayFE from "@/Components/DataDisplay/FractionDisplayFE.vue"
import { useUnitsOverPack, type PackedFractionData } from "@/Composables/useFractionUnits"

const props = defineProps<{
    qty_waiting_warehouse?: number
    fractionData?: PackedFractionData | null
    href?: string
}>()

const waitingInUnitsOverPack = computed(() => useUnitsOverPack(props.fractionData))
</script>

<template>
    <div class="w-fit flex gap-x-2">
        <component
            :is="href ? Link : 'div'"
            :href="href"
            v-tooltip="ctrans('Quantity of items waiting for warehouse')"
            class="border-l-2 border-yellow-400 relative rounded-r bg-yellow-500/20 py-1 pl-2 pr-3 text-yellow-700 whitespace-nowrap w-fit"
            :class="href ? 'cursor-pointer transition-colors hover:bg-yellow-500/30 hover:text-yellow-800' : ''">
            <FontAwesomeIcon icon="fal fa-hourglass-start" class="mr-1 opacity-70" fixed-width aria-hidden="true" />
            <span v-if="waitingInUnitsOverPack" class="inline-flex items-center gap-x-1">
                <FractionDisplayFE
                    :numerator="waitingInUnitsOverPack.numerator"
                    :denominator="waitingInUnitsOverPack.denominator"
                />
                {{ ctrans("items are waiting for warehouse") }}
            </span>
            <span v-else>{{ ctrans(":quantityWaitingWarehouse items are waiting for warehouse", { quantityWaitingWarehouse: Number(props.qty_waiting_warehouse || 0) }) }}</span>
            <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
            <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px]" fixed-width aria-hidden="true" />
        </component>
    </div>
</template>
