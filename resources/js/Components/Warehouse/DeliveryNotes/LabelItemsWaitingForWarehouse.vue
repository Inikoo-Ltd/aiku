<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
// import {  } from "@fal"
// import { library } from "@fortawesome/fontawesome-svg-core"
// library.add()

const props = defineProps<{
    qty_waiting_warehouse?: number
}>()
</script>

<template>
    <div class="w-fit flex gap-x-2">
        <div v-tooltip="ctrans('Quantity of items waiting for warehouse')" class="border-l-2 border-yellow-400 relative bg-yellow-500/20 py-1 pr-2 pl-1 text-yellow-700 whitespace-nowrap w-fit">
            <FontAwesomeIcon icon="fal fa-hourglass-start" class="mr-1 opacity-70" fixed-width aria-hidden="true" />
            <span>{{ ctrans(":quantityWaitingWarehouse items are waiting for warehouse", { quantityWaitingWarehouse: Number(props.qty_waiting_warehouse || 0) }) }}</span>
            <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
            <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px]" fixed-width aria-hidden="true" />
        </div>
    </div>
</template>