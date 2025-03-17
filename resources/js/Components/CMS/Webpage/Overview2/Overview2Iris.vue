<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink)

const props = defineProps<{
	fieldValue: any
}>()

const isModalGallery = ref(false)

</script>

<template>
	<div class="container flex flex-wrap justify-between" :style="getStyles(fieldValue.container.properties)">
		<!-- Image Section -->
		<div class="imgBx relative w-1/2 transition-all duration-300">
			<div class="absolute inset-0">
				<img v-if="!fieldValue?.image?.source"
					src="https://flowbite.s3.amazonaws.com/docs/gallery/square/image.jpg" :alt="fieldValue?.image?.alt"
					class="h-full w-full object-cover" />
				<Image :src="fieldValue?.image?.source" :imageCover=true :alt="fieldValue?.image?.alt"
					:imgAttributes="fieldValue?.image?.attributes" :style="getStyles(fieldValue?.image?.properties)" />
			</div>
		</div>

		<!-- Details Section -->
		<div class="details flex flex-col justify-center w-1/2 p-10">
			<div v-html="fieldValue.text" />
		</div>
	</div>
</template>
