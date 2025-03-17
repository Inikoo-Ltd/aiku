<script setup lang="ts">
import { ref, nextTick, onMounted } from "vue"
import MobileMenu from "@/Components/MobileMenu.vue"
import Menu from "primevue/menu"
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
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Moveable from "vue3-moveable"

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
			container : {
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
	loginMode: boolean
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string | number): void
	(e: "setPanelActive", value: string | number): void
	(e: "autoSave"): void
}>()

const _menu = ref()
const _textRef = ref<HTMLElement | null>(null) // Correct reference type
const _parentComponent = ref<HTMLElement | null>(null);

// Ensure Moveable gets the correct target
onMounted(() => {
	nextTick(() => {
		if (_textRef.value) {
			_textRef.value = _textRef.value
		}
	})
})

// Save function to emit autoSave
function onSave() {
	emits("update:modelValue", props.modelValue)
}

let dragOffset = { x: 0, y: 0 };

function onMouseDown(e) {
  const elementRect = e.target.getBoundingClientRect();
  // Set the offset to half the element's dimensions.
  dragOffset.x = elementRect.width / 2;
  dragOffset.y = elementRect.height / 2;
}

// Dragging function for Moveable
function onDragText(e) {
	e.target.style.transform = e.transform

	if (_parentComponent.value) {
		const parentRect = _parentComponent.value.getBoundingClientRect()

		const relativeLeft = e.clientX - parentRect.left - dragOffset.x;
   		const relativeTop = e.clientY - parentRect.top - dragOffset.y;

		const leftPercent = (relativeLeft / parentRect.width) * 100
		const topPercent = (relativeTop / parentRect.height) * 100
		
		e.target.style.left = `${leftPercent}%`
		e.target.style.top = `${topPercent}%`
		e.target.style.transform = ""

		props.modelValue.text.container.properties.position.left = `${leftPercent}%`
		props.modelValue.text.container.properties.position.top = `${topPercent}%`

		onSave()
	}

}



function onResizeText(e) {
	const { target, width, height } = e
	target.style.width = `${width}px`
	target.style.height = `${height}px`
	props.modelValue.text.container.properties.width = `${width}px`
	props.modelValue.text.container.properties.height = `${height}px`
	onSave()
}

function onTextScale({ offsetHeight = 100, offsetWidth = 100, transform }) {
	if (!transform) return

	const scaleMatch = transform.match(/scale\(([^)]+)\)/)
	if (!scaleMatch) return

	const scaleValues = scaleMatch[1].split(", ").map(Number)
	props.modelValue.text.container.properties.width = `${offsetWidth * scaleValues[0]}px`
	props.modelValue.text.container.properties.height = `${offsetHeight * scaleValues[1]}px`
	onSave()
}

// Toggle menu
const toggle = (event) => {
	_menu.value.toggle(event)
}

// Make editor editable
const editable = ref(true)
</script>

<template>
	<div ref="_parentComponent" class="relative shadow-sm" :style="getStyles(modelValue.container.properties)">
		<div class="flex flex-col justify-between items-center py-4 px-6 hidden lg:block">
			<div class="w-full grid grid-cols-3 items-center gap-6">
				<!-- Logo -->
				
				<Image 
                        :style="getStyles(modelValue.logo.properties)"
                        :alt="modelValue?.logo?.alt" 
                         :imageCover="true"
                        :src="modelValue?.logo?.image?.source" 
                        :imgAttributes="modelValue?.logo.image?.attributes"
                        class="hover-dashed"
						@click="() => emits('setPanelActive', 'logo')"
						
                    </Image>
		

				<!-- Search Bar -->
				<div class="relative justify-self-center w-full max-w-md"></div>

				<!-- Text (Movable & Resizable) -->
				<div ref="_textRef" @mousedown="onMouseDown" class="absolute" :style="{
					width: modelValue.text?.container?.properties?.width || 'auto',
						height: modelValue.text?.container?.properties?.height || 'auto',
						top: modelValue.text?.container?.properties?.position?.top || 'auto',
						left: modelValue.text?.container?.properties?.position?.left || 'auto',
					}">
					<Editor
						v-model="modelValue.text.text"
						:editable="editable"
						@update:model-value="
							(e) => {
								modelValue.text.text = e
								emits('update:modelValue', modelValue)
							}
						" />
				</div>

				<Moveable
					class="moveable"
					:target="_textRef"
					:draggable="true"
					:resizable="true"
					:scalable="true"
					:keepRatio="false"
	
					@drag="onDragText"
					@resize="onResizeText"
					@scale="onTextScale"
					:snapDirections="{ top: true, left: true, bottom: true, right: true }"
					:elementSnapDirections="{
						top: true,
						left: true,
						bottom: true,
						right: true,
						center: true,
						middle: true,
					}" />
			</div>
		</div>

		<!-- Mobile view (hidden on desktop) -->
		<div class="block md:hidden p-3">
			<div class="flex justify-between items-center">
				<MobileMenu :header="modelValue" :menu="modelValue" />

				<!-- Logo for Mobile -->
				<Image v-if="modelValue?.logo?.source?.original" :src="modelValue?.logo?.source" class="h-10 mx-2"></Image>
				<img
					v-else-if="modelValue.logo"
					src="https://d19ayerf5ehaab.cloudfront.net/assets/store-18687/18687-logo-1642004490.png"
					alt="Ancient Wisdom Logo"
					class="h-10 mx-2" />


				<!-- Profile Icon with Dropdown Menu -->
				<div @click="toggle" class="flex items-center cursor-pointer text-white">
					<FontAwesomeIcon icon="fas fa-user-circle" class="text-2xl" />
					<Menu ref="_menu" id="overlay_menu" :model="items" :popup="true">
						<template #itemicon="{ item }">
							<FontAwesomeIcon :icon="item.icon" />
						</template>
					</Menu>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>


.resizable-box {
	display: inline-block;
	background: #f0f0f0;
	padding: 5px;
	border: 1px solid #ccc;
}
</style>
