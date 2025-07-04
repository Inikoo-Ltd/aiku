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

console.log(props)

const emits = defineEmits<{
	(e: "update:modelValue", value: string | number): void
	(e: "setPanelActive", value: string | number): void
	(e: "autoSave"): void
}>()

const _menu = ref()
const _textRef = ref<HTMLElement | null>(null) // Correct reference type
const _parentComponent = ref<HTMLElement | null>(null)

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

let dragOffset = { x: 0, y: 0 }

function onMouseDown(e) {
	const elementRect = e.target.getBoundingClientRect()
	// Set the offset to half the element's dimensions.
	dragOffset.x = elementRect.width / 2
	dragOffset.y = elementRect.height / 2
}

// Dragging function for Moveable
function onDragText(e) {
	e.target.style.transform = e.transform

	if (_parentComponent.value) {
		const parentRect = _parentComponent.value.getBoundingClientRect()

		const relativeLeft = e.clientX - parentRect.left - dragOffset.x
		const relativeTop = e.clientY - parentRect.top - dragOffset.y

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
const layout = inject('layout', {})
</script>

<template>
	<div
		class="relative shadow-sm"
		:style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            margin : 0, padding : 0,
			...getStyles(modelValue.container?.properties, screenType)
            
		}">
		<div class="flex flex-col justify-between items-center py-4 px-6 ">
			<div class="w-full grid grid-cols-3 items-start gap-6">
				<!-- Logo -->
				<div>
					<component
						v-if="modelValue?.logo?.image?.source"
						:is="modelValue?.logo?.image?.source ? 'a' : 'div'"
						target="_blank"
						rel="noopener noreferrer"
						class="block w-fit h-auto"
						@click="() => emits('setPanelActive', 'logo')">
						<Image
							:style="getStyles(modelValue.logo.properties, screenType)"
							:alt="modelValue?.logo?.image?.alt || modelValue?.logo?.alt"
							:imageCover="true"
							:src="modelValue?.logo?.image?.source"
							:imgAttributes="modelValue?.logo.image?.attributes" />
					</component>
					<div
						v-else
						@click="() => emits('setPanelActive', 'logo')"
						class="flex items-center justify-center w-[100px] h-[100px] bg-gray-200 rounded-lg aspect-square transition-all duration-300 hover:bg-gray-300 hover:shadow-lg hover:scale-105 cursor-pointer">
						<font-awesome-icon
							:icon="['fas', 'image']"
							class="text-gray-500 text-4xl transition-colors duration-300 group-hover:text-gray-700" />
					</div>
				</div>
				
				<!-- Search Bar -->
                <div class="relative justify-self-center w-full max-w-md">
                    <!-- <input type="text" placeholder="Search Products"
                        class="border border-gray-300 py-2 px-4 rounded-md text-sm w-full shadow-inner focus:outline-none focus:border-gray-500"> -->
                    <!--
                    <FontAwesomeIcon icon="fas fa-search"
                        class="absolute top-1/2 -translate-y-1/2 right-4 text-gray-500" fixed-width /> -->
                </div>

				<div
					ref="_textRef"
					class="xcol-span-2 relative w-full h-full">
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

			<!-- 	<Moveable
					class="moveable"
					:target="_textRef"
					:draggable="false"
					:resizable="false"
					:scalable="false"
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
					}" /> -->
			</div>
		</div>
	</div>

	<!-- <pre>{{ getStyles(modelValue.logo.properties,screenType) }}</pre> -->
</template>

<style scoped></style>
