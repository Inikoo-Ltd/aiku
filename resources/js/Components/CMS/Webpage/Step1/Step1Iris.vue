<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { library, icon } from "@fortawesome/fontawesome-svg-core";
import { faGalaxy, faTimesCircle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBaby, faCactus, faCircle, faObjectGroup, faUser, faHouse, faTruck, faTag, faPhone, faInfoCircle } from "@fal";
import { inject } from "vue";
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
	faTimesCircle, faUser, faCactus, faBaby, faObjectGroup, faGalaxy, faLambda, faBackpack, faHouse, faTruck, faTag, faPhone, faInfoCircle,
	faTruckLoading, faTruckMoving, faTruckContainer, faUserRegular, faWarehouse, faWarehouseAlt, faShippingFast, faInventory,
	faDollyFlatbedAlt, faBoxes, faShoppingCart, faBadgePercent, faChevronRight, faCaretRight, faPhoneAlt, faGlobe, faPercent, faPoundSign, faClock
);

const props = defineProps<{
	fieldValue: {}
	webpageData?: any
	blockData?: Object
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()



const layout: any = inject("layout", {})
</script>

<template>
	<div id="cta1" :style="{
		...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
		...getStyles(fieldValue.container?.properties, screenType),
		width: 'auto'
	}">
		<div class="container mx-auto max-w-7xl px-6 lg:px-8">
			<div class="relative py-8">
				<!-- Vertical line -->
				<div :style="getStyles(fieldValue?.timeline?.line.properties, screenType)"
					class="hidden md:block absolute left-1/2 top-0 transform -translate-x-1/2 h-full w-1 bg-gray-200 rounded-full z-0">
				</div>


				<div v-for="(step, idx) in fieldValue.timeline.timeline_data" :key="idx"
					class="mb-16 grid grid-cols-1 md:grid-cols-9 md:items-center relative">
					<!-- Text -->
					<div v-if="idx % 2 === 0"
						class="order-2 md:order-1 md:col-span-4 md:pr-8 text-right px-4 text-left md:text-right">
						<div v-html="step.text" />
					</div>
					<div v-else class="order-2 md:order-1 md:col-span-4"></div>

					<!-- Bullet -->
					<div class="order-1 md:order-2 md:col-span-1 flex justify-center mb-6 md:mb-0 relative z-10">
						<div :style="getStyles(fieldValue?.timeline?.bullet.properties, screenType)"
							class="bg-blue-600 text-white font-bold rounded-full w-14 h-14 flex items-center justify-center">
							<span v-if="fieldValue.timeline.bullet.type === 'number'">{{ idx + 1 }}</span>
							<FontAwesomeIcon v-if="fieldValue.timeline.bullet.type === 'icon'" :icon="step.icon" />
						</div>
					</div>

					<!-- Text -->
					<div v-if="idx % 2 === 1" class="order-3 md:order-3 md:col-span-4 md:pl-8 text-left px-4">
						<div v-html="step.text" />
					</div>
					<div v-else class="order-3 md:order-3 md:col-span-4"></div>
				</div>
			</div>
		</div>

	</div>

</template>
