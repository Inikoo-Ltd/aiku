<script setup lang="ts">
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import { library, icon } from "@fortawesome/fontawesome-svg-core";
import { faGalaxy, faTimesCircle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBaby, faCactus, faCircle, faObjectGroup, faUser, faHouse, faTruck, faTag, faPhone } from "@fal";
import {
	faBackpack,
	faTruckLoading,
	faTruckMoving,
	faTruckContainer,
	faUser as faUserRegular,
	faWarehouse,
	faWarehouseAlt,
	faShippingFast,
	faInventory,
	faDollyFlatbedAlt,
	faBoxes,
	faShoppingCart,
	faBadgePercent,
	faChevronRight,
	faCaretRight,
	faPhoneAlt,
	faGlobe,
	faPercent,
	faPoundSign,
	faClock
} from "@far";
import { faLambda } from "@fad";

library.add(
	faTimesCircle, faUser, faCactus, faBaby, faObjectGroup, faGalaxy, faLambda, faBackpack, faHouse, faTruck, faTag, faPhone,
	faTruckLoading, faTruckMoving, faTruckContainer, faUserRegular, faWarehouse, faWarehouseAlt, faShippingFast, faInventory,
	faDollyFlatbedAlt, faBoxes, faShoppingCart, faBadgePercent, faChevronRight, faCaretRight, faPhoneAlt, faGlobe, faPercent, faPoundSign, faClock
);


const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()


</script>

<template>
	<div class="container mx-auto max-w-7xl px-6 lg:px-8"
		:style="getStyles(modelValue?.line?.properties, screenType)">
		<div class="relative py-8">
			<div :style="getStyles(modelValue?.timeline?.line.properties, screenType)" class="absolute left-1/2 top-0 transform -translate-x-1/2 h-full w-1 bg-gray-200 rounded-full z-0">
			</div>

			<div v-for="(step, idx) in modelValue.timeline.timeline_data" :key="idx"
				class="mb-16 md:grid md:grid-cols-9 md:items-center relative">
				<div v-if="idx % 2 === 0" class="md:col-span-4 md:pr-8 text-right px-4">
					<Editor v-model="step.text" @update:modelValue="() => emits('autoSave')" />
				</div>
				<div v-else class="md:col-span-4"></div>
				<div class="md:col-span-1 flex justify-center relative z-10">
					<div :style="getStyles(modelValue?.timeline?.bullet.properties, screenType)"
						class="bg-blue-600 text-white font-bold rounded-full w-14 h-14 flex items-center justify-center">
						<span v-if="modelValue.timeline.bullet.type == 'number'"> {{ idx + 1 }}</span>
						<FontAwesomeIcon v-if="modelValue.timeline.bullet.type == 'icon'" :icon="step.icon" />
					</div>
				</div>

				<!-- right content… -->
				<div v-if="idx % 2 === 1" class="md:col-span-4 md:pl-8 text-left px-4">
					<Editor v-model="step.text" @update:modelValue="() => emits('autoSave')" />
				</div>
				<div v-else class="md:col-span-4"></div>
			</div>
		</div>
	</div>
</template>

<style scoped>
/* 100% Tailwind — no extra CSS needed */
</style>
