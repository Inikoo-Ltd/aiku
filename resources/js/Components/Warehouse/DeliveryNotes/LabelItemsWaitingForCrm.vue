<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
// import {  } from "@fal"
// import { library } from "@fortawesome/fontawesome-svg-core"
// library.add()

const props = defineProps<{
    qty_waiting_crm?: number
    fractionData?: number[]
}>()
</script>

<template>
    <div class="w-fit flex gap-x-2">
        <div v-tooltip="ctrans('Quantity of items waiting for CRM')" class="border-l-2 border-purple-400 relative bg-purple-500/20 py-1 pr-2 pl-1 text-purple-700 whitespace-nowrap w-fit">
            <FontAwesomeIcon icon="fal fa-hourglass-start" class="mr-1 opacity-70" fixed-width aria-hidden="true" />
            <span v-if="props.fractionData" class="inline-flex items-center gap-x-1">
                <FractionDisplay :fractionData="props.fractionData" />
                {{ ctrans("items are waiting for CRM") }}
            </span>
            <span v-else>{{ ctrans(":quantityWaitingCRM items are waiting for CRM", { quantityWaitingCRM: Number(props.qty_waiting_crm || 0) }) }}</span>
            <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
            <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px]" fixed-width aria-hidden="true" />
        </div>
    </div>
</template>