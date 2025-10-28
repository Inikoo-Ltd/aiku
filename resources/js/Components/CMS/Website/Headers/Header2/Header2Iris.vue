<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { inject } from "vue"
import Image from "@/Components/Image.vue"
import MobileHeader from "../MobileHeader.vue";
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LinkIris from "@/Components/Iris/LinkIris.vue";
import LuigiSearch from "@/Components/CMS/LuigiSearch.vue"

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
const layout = inject('layout', layoutStructure)


</script>

<template>
	<div id="header_2_iris" class="relative shadow-sm" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            margin : 0, padding : 0,
			...getStyles(fieldValue.container?.properties, screenType)
            
		}">
		<div class="flex flex-col justify-between items-start py-4 px-6">
			<div class="w-full grid grid-cols-3 items-start gap-6">
				<!-- Logo -->

				<div class="flex items-center justify-start h-full">
					<div class="relative w-[200px] md:w-[200px] aspect-[4/2]">
						<component v-if="fieldValue?.logo?.image?.source"
						:is="fieldValue?.logo?.image?.source ? LinkIris : 'div'"
						:canonical_url="props.fieldValue?.logo?.link?.canonical_url"
						:href="props.fieldValue?.logo?.link?.href" :type="props.fieldValue?.logo?.link?.type"
						:target="fieldValue?.logo?.link?.target || '_self'" rel="noopener noreferrer"
						class="block w-fit h-auto">
							<template #default>
								<!-- <Image :style="getStyles(fieldValue.logo.properties, screenType)"
									:alt="fieldValue?.logo?.image?.alt || fieldValue?.logo?.alt" :imageCover="true"
									class="object-contain w-full h-full" :src="fieldValue?.logo?.image?.source" /> -->
									<Image
									:alt="fieldValue?.logo?.image?.alt || fieldValue?.logo?.alt" :imageCover="true"
									class="object-contain w-full h-full" :src="fieldValue?.logo?.image?.source" />
							</template>
						</component>
					</div>
				</div>
				<!-- Search Bar -->
				<div class="relative justify-self-center w-full max-w-80 flex items-center h-full">
					<LuigiSearch v-if="layout.iris?.luigisbox_tracker_id" id="luigi_header_2" />
				</div>

				<div class="relative w-full h-auto">
					<div v-html="fieldValue?.text?.text" />
				</div>
			</div>
		</div>

		<!-- <MobileHeader :header-data="fieldValue" :menu-data="{}" :screenType="screenType" /> -->
	</div>
</template>

<style scoped></style>
