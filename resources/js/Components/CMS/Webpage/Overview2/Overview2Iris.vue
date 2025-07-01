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
			...getStyles(fieldValue.container?.properties, screenType),
			width:'auto'
		}">
			<!-- Section 1: Image -->
			<div class="w-full h-64 sm:h-72 md:h-auto md:w-1/3 lg:w-1/2 bg-cover bg-center bg-no-repeat">
				<img v-if="!fieldValue?.image?.source"
					src="https://flowbite.s3.amazonaws.com/docs/gallery/square/image.jpg" :alt="fieldValue?.image?.alt"
					class="h-full w-full object-cover" />
				<Image v-else :src="fieldValue?.image?.source" :imageCover :alt="fieldValue?.image?.alt"
					:imgAttributes="fieldValue?.image?.attributes" :style="getStyles(fieldValue?.image?.properties)" />
			</div>

			<!-- Section 2: Content -->
			<div
				class="flex items-center justify-center w-full md:w-2/3 lg:w-1/2 bg-white bg-opacity-90 backdrop-blur px-6 py-12 sm:px-12 lg:px-20">
				<div v-html='fieldValue.text' />
			</div>
		</div>

	</div>

</template>
