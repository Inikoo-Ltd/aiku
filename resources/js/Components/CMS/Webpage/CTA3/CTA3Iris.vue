<script setup lang="ts">
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: any
	webpageData?: any
	blockData?: Object
	screenType: "mobile" | "tablet" | "desktop"
}>()


</script>

<template>
	<!-- <div class="relative overflow-hidden rounded-lg lg:h-96" :style="getStyles(fieldValue.container.properties)">
		<div class="absolute inset-0">
			<template v-if="fieldValue?.image?.source">
				<Image :src="fieldValue.image.source" :imageCover="true" :alt="fieldValue.image.alt"
					:imgAttributes="fieldValue.image.attributes" :style="getStyles(fieldValue.image.properties)" />
			</template>
<template v-else>
				<img src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
					:alt="fieldValue?.image?.alt" class="h-full w-full object-cover" />
			</template>
</div>

<div aria-hidden="true" class="relative h-96 w-full lg:hidden" />
<div aria-hidden="true" class="relative h-32 w-full lg:hidden" />

<div
	class="absolute inset-x-0 bottom-0 rounded-bl-lg rounded-br-lg bg-white bg-opacity-75 p-6 backdrop-blur backdrop-filter sm:flex sm:items-center sm:justify-between lg:inset-x-auto lg:inset-y-0 lg:w-96 lg:flex-col lg:items-start lg:rounded-br-none lg:rounded-tl-lg">
	<div class="text-center lg:text-left text-gray-600 pr-3 overflow-y-auto mb-4">
		<div v-html="fieldValue.text" />
	</div>
	<a typeof="button" :style="getStyles(fieldValue.button.container.properties)" :href="fieldValue?.button?.link?.href"
		:target="fieldValue?.button?.link?.target" class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6">
		{{ fieldValue.button.text }}
	</a>
</div>
</div> -->

	<div class="relative grid rounded-lg" :style="getStyles(fieldValue.container.properties, screenType)">
		<!-- Background Image Layer -->
		<div class="absolute inset-0 bg-cover bg-center bg-no-repeat z-0" :style="{
			backgroundImage: fieldValue?.image?.source
				? `url('${fieldValue.image.source.original}')`
				: `url('https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png')`,
			...getStyles(fieldValue.image.properties, screenType)
		}"></div>


		<div :style="getStyles(fieldValue.container.properties?.block, screenType)" class="relative z-10 w-full bg-white bg-opacity-75 p-6 backdrop-blur backdrop-filter
         sm:flex sm:flex-col sm:items-start
         lg:w-96 ">

			<div class="text-center lg:text-left text-gray-600 pr-3 mb-4 w-full">
				<div v-html="fieldValue.text" />
			</div>

			<a typeof="button" :style="getStyles(fieldValue.button.container.properties)"
				:href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.target"
				class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6">
				{{ fieldValue.button.text }}
			</a>
		</div>
	</div>
</template>
