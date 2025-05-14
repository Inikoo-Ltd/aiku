<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { checkVisible } from "@/Composables/Workshop"
import { inject } from "vue"
import Image from "@/Components/Image.vue"

const props = defineProps<{
	fieldValue: {
		headerText: string
		chip_text: string
		container: {
			properties: Record<string, string>
		}
		logo: {
			properties: Record<string, string>
			alt: string
			image: {
				source: string
			}
		}
		text: {
			text: string
			visible: boolean | null
			container: {
				properties: Record<string, string>
			}
		}
	}
	loginMode: boolean
	screenType: "mobile" | "tablet" | "desktop"
}>()

const isLoggedIn = inject("isPreviewLoggedIn", false)
</script>

<template>
	<div class="relative" :style="getStyles(fieldValue.container.properties)">
		<div class="flex flex-col justify-between items-center py-4 px-6">
			<div class="w-full grid grid-cols-3 items-center gap-6">
				<!-- Logo -->
				<component
					v-if="fieldValue?.logo?.image?.source"
					:is="fieldValue?.logo?.image?.source ? 'a' : 'div'"
					:href="fieldValue?.logo?.link?.href || '#'"
					:target="fieldValue?.logo?.link?.target || '_self'"
					rel="noopener noreferrer"
					class="block w-fit h-full">
					<Image
						:style="getStyles(fieldValue.logo.properties, screenType)"
						:alt="fieldValue?.logo?.image?.alt || fieldValue?.logo?.alt"
						:imageCover="true"
						:src="fieldValue?.logo?.image?.source">
					</Image>
				</component>

				<div class="relative justify-self-center w-full max-w-md"></div>
				<div
					class="relative"
					:style="getStyles(fieldValue.container.properties, screenType)">
					<div v-html="fieldValue?.text?.text" />
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped></style>
