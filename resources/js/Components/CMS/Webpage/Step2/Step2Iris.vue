<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"
import { inject } from "vue";
import { faBaby, faCactus, faObjectGroup, faUser, faHouse, faTruck, faTag, faPhone, faInfoCircle } from "@fal";
import { faGalaxy, faTimesCircle } from "@fas";

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
	faTruckLoading, faTruckMoving, faTruckContainer, faUserRegular, faWarehouse, faWarehouseAlt, faShippingFast, faInventory, faInfoCircle, faCheck,
	faDollyFlatbedAlt, faBoxes, faShoppingCart, faBadgePercent, faChevronRight, faCaretRight, faPhoneAlt, faGlobe, faPercent, faPoundSign, faClock
);


const props = defineProps<{
	fieldValue: {}
	theme?: any
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout: any = inject("layout", {})

const emits = defineEmits<{
	(e: "update:fieldValue", value: any): void
	(e: "autoSave"): void
}>()

</script>

<template>
	<div id="step-2">
		<div :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType),
			width: 'auto'
		}">
			<div class="relative py-8">
				<!-- Vertical line -->
				<div :style="getStyles(fieldValue?.timeline?.line.properties, screenType)"
					class="hidden md:block absolute left-1/2 top-0 transform -translate-x-1/2 h-full w-1 bg-gray-200 rounded-full z-0">
				</div>


				<div v-for="(step, idx) in fieldValue.timeline.timeline_data" :key="idx" class="relative mb-16 grid grid-cols-1 md:grid-cols-9 md:items-center">
					<!-- Left Text -->
					<div class="order-2 md:order-1 md:col-span-4 md:pr-8 px-4 text-left md:text-right">
						<!-- <Editor v-model="step.text_left" @update:fieldValue="() => emits('autoSave')" /> -->
						<div v-html="step.text_left"></div>
					</div>

					<!-- Bullet -->
					<div class="order-1 md:order-2 md:col-span-1 flex justify-center mb-6 md:mb-0 relative z-10">
						<div :style="getStyles(fieldValue.timeline.bullet.properties, screenType)"
							class="w-14 h-14 flex items-center justify-center rounded-full bg-blue-600 text-white font-bold">
							<span v-if="fieldValue.timeline.bullet.type === 'number'">
								{{ idx + 1 }}
							</span>

							<FontAwesomeIcon v-if="fieldValue.timeline.bullet.type === 'icon'" :icon="step.icon" />
							<span v-if="fieldValue.timeline.bullet.type === 'text'"  >
								<!-- <Editor v-model="step.text_bullet" @update:fieldValue="() => emits('autoSave')" /> -->
									<div  v-html="step.text_bullet"></div>
							</span>
						</div>
					</div>

					<!-- Right Text -->
					<div class="order-3 md:order-3 md:col-span-4 md:pl-8 px-4 text-left">
						<!-- <Editor v-model="step.text_right" @update:fieldValue="() => emits('autoSave')" /> -->
						<div v-html="step.text_right"></div>
					</div>
				</div>

			</div>
		</div>

	</div>

</template>
