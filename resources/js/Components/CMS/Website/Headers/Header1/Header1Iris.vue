<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { checkVisible } from "@/Composables/Workshop"
import { inject, ref } from "vue"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";

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
import LinkIris from "@/Components/Iris/LinkIris.vue"

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

const isLoggedIn = inject("isPreviewLoggedIn", false)
const loadingRedirect = ref(false)

</script>

<template>
	<div id="header_1_iris" class="shadow-sm" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            margin : 0, padding : 0,
			...getStyles(fieldValue.container?.properties, screenType)
            
		}">
		<div class="flex flex-col justify-between items-center py-4 px-6">
			<div class="w-full grid grid-cols-3 items-center gap-6">
				<!-- Logo -->
				<div class="relative w-[200px] md:w-[200px] aspect-[4/2]">

						<!-- Spinner Overlay -->
						<div v-if="loadingRedirect"
							class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 rounded">
							<LoadingIcon class="w-12 h-12 text-gray-500" />
						</div>

						<component v-if="fieldValue?.logo?.image?.source"
							:is="fieldValue?.logo?.image?.source ? LinkIris : 'div'" @start="loadingRedirect = true"
							@finish="loadingRedirect = false"
							:canonical_url="props.fieldValue?.logo?.link?.canonical_url"
							:href="props.fieldValue?.logo?.link?.href" :type="props.fieldValue?.logo?.link?.type"
							:target="fieldValue?.logo?.link?.target || '_self'" rel="noopener noreferrer"
							class="block w-fit h-auto">
							<template #default>
								<Image :alt="fieldValue?.logo?.image?.alt || fieldValue?.logo?.alt" :imageCover="true"
									class="object-contain w-full h-full" :src="fieldValue?.logo?.image?.source" />
							</template>
						</component>

					</div>

				<!-- Search Bar -->
				<div class="relative justify-self-center w-full max-w-80 flex items-center h-full">
					<LuigiSearch
						v-if="layout.iris?.luigisbox_tracker_id"
						id="luigi_header_1"
						:fieldValueSearch="fieldValue?.search"
					/>
				</div>

				<!-- Gold Member Button -->
				<div class="justify-self-end w-fit">
					<LinkIris :href="fieldValue?.button_1?.link?.href" :target="fieldValue?.button_1?.link?.target"
						:canonical_url="fieldValue?.button_1?.link?.canonical_url"
						:type="fieldValue?.button_1?.link?.type">
						<template #default>
							<div v-if="checkVisible(fieldValue.button_1.visible, isLoggedIn)"
								class="space-x-1.5 cursor-pointer whitespace-nowrap"
								:style="getStyles(fieldValue.button_1.container.properties)">
								<span v-html="fieldValue.button_1.text" />
							</div>
						</template>
					</LinkIris>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped></style>
