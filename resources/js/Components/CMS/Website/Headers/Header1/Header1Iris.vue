<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { checkVisible } from "@/Composables/Workshop"
import { inject } from "vue"
import Image from "@/Components/Image.vue"
import { resolveMigrationLink } from "@/Composables/SetUrl"

import { faPresentation, faCube, faText, faPaperclip } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faChevronRight,
	faSignOutAlt,
	faShoppingCart,
	faSearch,
	faChevronDown,
	faTimes,
	faPlusCircle,
	faBars,
	faUserCircle,
	faImage,
	faSignInAlt,
	faFileAlt,
} from "@fas"
import { faHeart } from "@far"
import LuigiSearch from "@/Components/CMS/LuigiSearch.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

library.add(
	faPresentation,
	faCube,
	faText,
	faImage,
	faPaperclip,
	faChevronRight,
	faSignOutAlt,
	faShoppingCart,
	faHeart,
	faSearch,
	faChevronDown,
	faTimes,
	faPlusCircle,
	faBars,
	faUserCircle,
	faSignInAlt,
	faFileAlt
)

const props = defineProps<{
	fieldValue: {
		headerText: string
		logo: {
			alt: string,
			image: {
				source: object
			},
		}
		container: {
			properties: Object
		}
		button_1: {
			visible: boolean
			text: string
			container: {
				properties: Object
			}
		}
	}
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout = inject('layout', layoutStructure)
const migration_redirect = layout?.iris?.migration_redirect
const isLoggedIn = inject("isPreviewLoggedIn", false)
 

console.log(layout)
</script>

<template>
	<div class="shadow-sm" :style="getStyles(fieldValue.container.properties)">
		<div class="flex flex-col justify-between items-center py-4 px-6">
			<div class="w-full grid grid-cols-3 items-center gap-6">
				<!-- Logo -->
				<div>
					<component v-if="fieldValue?.logo?.image?.source" :is="fieldValue?.logo?.image?.source ? 'a' : 'div'"
						:href="resolveMigrationLink(props.fieldValue?.logo?.link?.href,migration_redirect)" :target="fieldValue?.logo?.link?.target || '_self'"
						rel="noopener noreferrer" class="block w-full h-full">
						<Image :style="getStyles(fieldValue.logo.properties)"
							:alt="fieldValue?.logo?.image?.alt || fieldValue?.logo?.alt" :imageCover="true"
							:src="fieldValue?.logo?.image?.source" :imgAttributes="fieldValue?.logo.image?.attributes">
						</Image>
					</component>
				</div>

				<!-- Search Bar -->
				<div class="relative justify-self-center w-full max-w-80">
					<LuigiSearch v-if="layout?.app?.environment === 'local'"></LuigiSearch>
                    <!--
                    <FontAwesomeIcon icon="fas fa-search"
                        class="absolute top-1/2 -translate-y-1/2 right-4 text-gray-500" fixed-width /> -->
                </div>

				<!-- Gold Member Button -->
				<div class="justify-self-end w-fit">
					<a :href="resolveMigrationLink(fieldValue?.button_1?.link?.href,migration_redirect)" :target="fieldValue?.button_1?.link?.target">
						<div v-if="checkVisible(fieldValue.button_1.visible, isLoggedIn)"
							class="space-x-1.5 cursor-pointer whitespace-nowrap"
							:style="getStyles(fieldValue.button_1.container.properties)">
							<span v-html="fieldValue.button_1.text" />
						</div>
					</a>

				</div>
			</div>
		</div>
	</div>
</template>

<style scoped></style>
