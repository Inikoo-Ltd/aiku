<!--  
 Author Louis Perez 
 Created on 24-06-2026-09h-43m 
 GitHub: https://github.com/louis-perez 
 Copyright 2026 
-->
<script setup lang="ts">
import { TradeUnit } from "@/types/trade-unit";
import { faCheck, faPlus, faTimes } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import JsBarcode from "jsbarcode";
import ImagePrime from "primevue/image"
import { onMounted } from "vue";
import { ref, computed } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Button from '@/Components/Elements/Buttons/Button.vue';

const props = defineProps<{
	handleTabUpdate : Function
	data: {    
        number:         string
        slug:           string
        type:           string
        status:         string
        status_icon:    {}
        note:           string
        trade_unit?:    TradeUnit
	}
}>()

onMounted(() => {
    JsBarcode('#barcodeNumber', props.data.number, {
        lineColor: "rgb(41 37 36)",
        width: 2,
        height: 70,
        displayValue: true
    });
})

function tradeUnitRoute(tradeUnit: TradeUnit) {
    return route("grp.trade_units.units.show", [tradeUnit.slug])
}
</script>
<template>
	<div class="w-full px-4 py-3 mb-3 shadow-sm">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
            <div class="grid">
                <div class="border border-solid rounded-md mt-1 px-3 py-2 w-fit h-fit">
                    <span class="font-semibold">
                        {{ ctrans('Status') }}: {{ data?.status }}
                    </span>
                    <div v-if="data?.trade_unit" class="grid border border-solid rounded-md mt-1 px-3 py-2 w-fit">
                        <span class="font-semibold">
                            <Link class="primaryLink" :href="tradeUnitRoute(data?.trade_unit)">
                                {{ data?.trade_unit.name }} 
                            </Link>
                            <FontAwesomeIcon
                                v-tooltip="data?.trade_unit.status"
                                :icon="data?.trade_unit.status == 'active' ? faCheck : faTimes" 
                                :class="data?.trade_unit.status == 'active' ? 'text-green-500' : 'text-red-500'"
                                class="ml-1"
                            />
                        </span>
					    <ImagePrime 
                            :src="data?.trade_unit.image_thumbnail.webp" 
                            :alt="data?.trade_unit.name" 
                            :imageClass="'h-full mt-2'"
                            preview 
                        />
                    </div>
                    <div v-else class="mt-2">
                        <Button
                            @click="() => {}" 
                            :label="ctrans('Select a trade unit')" 
                            :icon="faPlus"
                            :style="'white-w-outline'"
                        />
                    </div>
                </div>
            </div>
            <div class="grid">
            </div>
            <div class="grid">
                <span class="text-xl font-semibold text-gray-800 whitespace-pre-wrap align-middle">
                    <div class="flex justify-start">
                        <svg id="barcodeNumber" class="bg-gray-100"></svg>
                    </div>
                </span>
            </div>
        </div>
	</div>
</template>
