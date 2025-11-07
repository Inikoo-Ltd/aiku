<script setup lang="ts">
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { inject } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue";


const props = defineProps<{
	fieldValue: any
	webpageData?: any
	blockData?: Object
	screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", {})

</script>

<template>
	<div id="cta3">
		<div class="relative grid rounded-lg overflow-hidden shadow-lg" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType)
		}">
			<!-- Image section -->
			<div class="absolute inset-0 z-0 cursor-pointer ratio-[16/9] transition-transform duration-300 hover:scale-105"
				:style="{
					...getStyles(fieldValue.image.properties, screenType),
				}">
				<Image :src="fieldValue?.image?.source
					? fieldValue.image.source
					: { original: 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png' }"
					:alt="fieldValue?.image?.alt ?? 'CTA Image'" imageCover
					class="rounded-lg object-cover w-full h-full" />
			</div>

			<!-- Text + button overlay -->
			<div :style="getStyles(fieldValue.container.properties?.block, screenType)"
				class="relative z-10 w-full bg-white/80 p-6 backdrop-blur-sm sm:flex sm:flex-col sm:items-start lg:w-96 rounded-lg shadow-md">
				<div class="text-center lg:text-left text-gray-700 pr-3 mb-4 w-full">
					<div v-html="fieldValue.text" />
				</div>

				<LinkIris typeof="button" :href="fieldValue?.button?.link?.href"
					:canonical_url="fieldValue?.button?.link?.canonical_url" :target="fieldValue?.button?.link?.target"
					:type="fieldValue?.button?.link?.type">
					<template #default>
						<Button :injectStyle="{
							...getStyles(fieldValue?.button?.container?.properties, screenType),
							width: 'fit-content !important'
						}" :label="fieldValue?.button?.text" />
					</template>
				</LinkIris>
			</div>
		</div>
	</div>

</template>
