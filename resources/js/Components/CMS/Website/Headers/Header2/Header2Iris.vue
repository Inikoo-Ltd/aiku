<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { checkVisible } from "@/Composables/Workshop"
import { inject } from "vue"
import Image from "@/Components/Image.vue"
import MobileHeader from "../MobileHeader.vue";
import { resolveMigrationLink } from "@/Composables/SetUrl"
import { layoutStructure } from "@/Composables/useLayoutStructure"
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
const migration_redirect = layout?.iris?.migration_redirect
console.log('la', layout)
</script>

<template>
	<div id="header_2_iris" class="relative shadow-sm" :style="getStyles(fieldValue.container.properties)">
		<div class="flex flex-col justify-between items-start py-4 px-6">
			<div class="w-full grid grid-cols-3 items-start gap-6">
				<!-- Logo -->
				<div>
					<component
						v-if="fieldValue?.logo?.image?.source"
						:is="fieldValue?.logo?.image?.source ? 'a' : 'div'"
						:href="resolveMigrationLink(props.fieldValue?.logo?.link?.href,migration_redirect)"
						:target="fieldValue?.logo?.link?.target || '_self'"
						rel="noopener noreferrer"
						class="block w-fit h-auto">
						<Image
							:style="getStyles(fieldValue.logo.properties, screenType)"
							:alt="fieldValue?.logo?.image?.alt || fieldValue?.logo?.alt"
							:imageCover="true"
							:src="fieldValue?.logo?.image?.source">
						</Image>
					</component>
				</div>

				<!-- Search Bar -->
				<div class="relative justify-self-center w-full max-w-80">
					<LuigiSearch v-if="layout.iris?.luigisbox_tracker_id"></LuigiSearch>
                </div>

				<div class="xcol-span-2 relative w-full h-auto">
					<div v-html="fieldValue?.text?.text" />
				</div>
			</div>
		</div>
		<MobileHeader :header-data="fieldValue" :menu-data="{}" :screenType="screenType" />
	</div>
</template>

<style scoped></style>
