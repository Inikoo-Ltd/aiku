<script setup lang="ts">
import { ref, nextTick, onMounted, inject } from "vue"
import { getStyles } from "@/Composables/styles"
import { faPresentation, faCube, faText, faPaperclip } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
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
import Image from "@/Components/Image.vue"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"

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

// Define Props & Events
const props = defineProps<{
	modelValue: {
		headerText: string
		chip_text: string
		text: {
			container: {
				properties: {
					position: {
						top: string
						left: string
					}
					width: string
					height: string
				}
				text: string
			}
		}
	}
	screenType: "mobile" | "tablet" | "desktop"
	loginMode: boolean
}>()


const emits = defineEmits<{
	(e: "update:modelValue", value: string | number): void
	(e: "setPanelActive", value: string | number): void
	(e: "autoSave"): void
}>()

const inputValue = ref('')
const _textRef = ref<HTMLElement | null>(null)

// Ensure Moveable gets the correct target
onMounted(() => {
	nextTick(() => {
		if (_textRef.value) {
			_textRef.value = _textRef.value
		}
	})
})


// Make editor editable
const editable = ref(true)
const layout = inject('layout', {})
</script>

<template>
	<div id="header_2" class="relative shadow-sm" :style="{
		...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
		margin: 0, padding: 0,
		...getStyles(modelValue.container?.properties, screenType)

	}">
		<div class="flex flex-col justify-between items-start py-4 px-6">
			<div class="w-full grid grid-cols-3 items-start gap-y-6 gap-x-10">
				<!-- Logo -->
				<div class="col-span-2 flex items-center gap-x-10 justify-between h-full"
					:class="modelValue?.search?.is_box_full_width ? 'flex' : 'grid grid-cols-2'"
				>
					<div class="relative w-[200px] md:w-[200px] aspect-[4/2]">
						<component v-if="modelValue?.logo?.image?.source" :is="'div'"
							class="absolute inset-0 w-full h-full">
							<template #default>
								<Image 
									:alt="modelValue?.logo?.image?.alt || modelValue?.logo?.alt" :imageCover="true"
									class="object-contain w-full h-full" :src="modelValue?.logo?.image?.source" />
							</template>
						</component>
					</div>
					
					<!-- Search Bar -->
					<div class="relative justify-self-center w-full max-w-80 flex items-center h-full transition-all"
						:class="modelValue?.search?.is_box_full_width ? 'max-w-[1100px] ' : 'max-w-sm'"
					>
						<div class="w-full relative group">
							<input :value="inputValue"
								class="h-12 min-w-28 focus:border-transparent focus:ring-2 focus:ring-gray-700 w-full md:min-w-0 md:w-full rounded-full border border-[#d1d5db] disabled:bg-gray-200 disabled:cursor-not-allowed pl-10"
								:placeholder="modelValue?.search?.placeholder ?? 'Search..'"
							/>
							<FontAwesomeIcon icon="far fa-search"
								class="group-focus-within:text-gray-700 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2"
								fixed-width aria-hidden="true" />
						</div>
					</div>
				</div>


				<div class="relative w-full h-auto">
					<Editor v-model="modelValue.text.text" :editable="editable" @update:model-value="
						(e) => {
							modelValue.text.text = e
							emits('update:modelValue', modelValue)
						}
					" />
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped></style>
