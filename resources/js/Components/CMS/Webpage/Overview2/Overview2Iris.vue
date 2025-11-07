<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { inject } from "vue"

library.add(faCube, faLink)

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()
const layout: any = inject("layout", {})

</script>

<template>
	<div id="overview-2">
			<div class="flex flex-col md:flex-row w-full rounded-lg overflow-hidden" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType)
		}">
			<!-- Section 1: Image (fixed ratio) -->
			<div class="w-full md:w-1/3 lg:w-1/2 relative cursor-pointer overflow-hidden bg-center bg-cover bg-no-repeat"
				:style="{ aspectRatio: '16/9' }">
				<Image :src="fieldValue?.image?.source" :imageCover
					:alt="fieldValue?.image?.alt" :imgAttributes="fieldValue?.image?.attributes"
					:style="getStyles(fieldValue?.image?.properties)" class="w-full h-full object-cover" />
			</div>

			<!-- Section 2: Content -->
			<div 
				class="flex items-center justify-center w-full md:w-2/3 lg:w-1/2 bg-white/90 backdrop-blur px-6 py-12 sm:px-12 lg:px-20">
				<div v-html='fieldValue.text' />
			</div>
		</div>
	</div>
</template>
