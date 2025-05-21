<script setup lang="ts">
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"


const props = defineProps<{
	fieldValue: {}
	theme?: any
	blockData?: Object
}>()
</script>

<template>
	<div
		class="relative  transition-all hidden md:block"
		:style="{...getStyles(fieldValue?.container?.properties), width : '100%', height : 'auto'}">
		<!-- Render text elements -->
		<div v-for="(text, index) in fieldValue?.texts?.values" :key="index">
			<div
				:class="`text-${index}`"
				ref="el => textRefs[index] = el"
				:style="{
				zIndex: 10,
					width:
						theme?.layout === 'fullscreen'
							? 'auto'
							: text?.properties?.width
							? `${text.properties.width}`
							: 'auto',
					height:
						theme?.layout === 'fullscreen'
							? 'auto'
							: text?.properties?.height
							? `${text.properties.height}`
							: 'auto',
					top:
						theme?.layout === 'fullscreen'
							? '10%'
							: text?.properties?.position?.top
							? `${text.properties.position.top}`
							: 'auto',
					bottom: theme?.layout === 'fullscreen' ? '10%' : 'auto',
					left:
						theme?.layout === 'fullscreen'
							? '10%'
							: text?.properties?.position?.left
							? `${text.properties.position.left}`
							: 'auto',
					right: theme?.layout === 'fullscreen' ? '10%' : 'auto',
				}">
				<div v-html="text.text" />
			</div>
		</div>

		<!-- Render image elements -->
		<div>
			<div v-for="(image, index) in fieldValue?.images" :key="index">
				<div
					class="absolute"
					:class="`image-${index}`"
					ref="el => imageRefs[index] = el"
					:style="{
						width: image?.properties?.width ? `${image?.properties?.width}` : 'auto',
						height: image?.properties?.height ? `${image?.properties?.height}` : 'auto',
						top: image?.properties?.position?.top
							? `${image?.properties?.position?.top}`
							: 'auto',
						left: image?.properties?.position?.left
							? `${image?.properties?.position?.left}`
							: 'auto',
					}">
					<Image :src="image.sources" />
				</div>
			</div>
		</div>
	</div>
	<div class="block md:hidden p-6">
		<div v-for="(image, index) in fieldValue?.images" :key="index">
			<Image :src="image.sources" />
		</div>
		<div v-for="(text, index) in fieldValue?.texts.values" :key="index">
			<div v-html="text.text" />
		</div>
	</div>
</template>
