<script setup lang="ts">
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { inject } from "vue"
import { resolveMigrationLink, resolveMigrationHrefInHTML } from "@/Composables/SetUrl"
import Button from "@/Components/Elements/Buttons/Button.vue"


const props = defineProps<{
	fieldValue: any
	webpageData?: any
	blockData?: Object
	screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", {})
const migration_redirect = layout?.iris?.migration_redirect
</script>

<template>
	<div id="cta3">
		<div class="relative grid rounded-lg" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType)
		}">
			<div class="absolute inset-0 overflow-hidden" :style="getStyles(fieldValue.image.properties, screenType)">
				<Image :src="fieldValue?.image?.source
					? fieldValue.image.source
					: {
						original: 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png'
					}
					" :alt="fieldValue?.image?.alt ?? 'CTA Image'" imageCover />
			</div>


			<div :style="getStyles(fieldValue.container.properties?.block, screenType)"
				class="relative z-10 w-full bg-white bg-opacity-75 p-6 backdrop-blur backdrop-filter sm:flex sm:flex-col sm:items-start lg:w-96">
				<div class="text-center lg:text-left text-gray-600 pr-3 mb-4 w-full">
					<div v-html="resolveMigrationHrefInHTML(fieldValue.text, migration_redirect)" />
				</div>

				<a typeof="button" :style="getStyles(fieldValue.button.container.properties, screenType)"
					:href="resolveMigrationLink(fieldValue?.button?.link?.href, migration_redirect)"
					:target="fieldValue?.button?.link?.target">
					<Button :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
						:label="fieldValue?.button?.text" />
				</a>
			</div>
		</div>
	</div>

</template>
