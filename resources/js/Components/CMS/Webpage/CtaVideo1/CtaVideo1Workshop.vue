<script setup lang="ts">
import { computed, inject, watch } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCube, faLink, faImage, faVideo } from "@fortawesome/free-solid-svg-icons"

import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "@/Components/CMS/Webpage/CtaVideo1/Blueprint"

library.add(faCube, faLink, faImage, faVideo)


type ScreenType = "mobile" | "tablet" | "desktop"

type ResponsiveVideoConfig = {
	by_url?: boolean
	source?: string | null
	embed_code?: string | null
	properties?: any
	container?: any
	attributes?: any
}

type VideoSetup = ResponsiveVideoConfig & {
	mobile?: ResponsiveVideoConfig
	tablet?: ResponsiveVideoConfig
	desktop?: ResponsiveVideoConfig
}

const props = defineProps<{
	modelValue: {
		container?: any
		text?: string
		text_block?: any
		button?: {
			text: string
			show?: boolean
			container?: any
		}
		video: {
			video_setup: VideoSetup
		}
	}
	indexBlock?: number
	webpageData?: any
	blockData?: any
	screenType: ScreenType
}>()

const emits = defineEmits<{
	(e: "autoSave"): void
}>()


/* -------------------------------------------------------------------------- */

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []

/* -------------------------------------------------------------------------- */
/*                            Helpers / Computed                              */
/* -------------------------------------------------------------------------- */

const videoSetup = computed(() => props.modelValue?.video?.video_setup)

const resolvedVideoConfig = computed<ResponsiveVideoConfig | null>(() => {
	const setup = videoSetup.value
	if (!setup) return null

	return (
		setup[props.screenType] ??
		setup.desktop ??
		setup
	)
})

const isVideoByUrl = computed(() => {
	return resolvedVideoConfig.value?.by_url === true
})

const videoSource = computed(() => {
	return resolvedVideoConfig.value?.source || null
})

const videoEmbedCode = computed(() => {
	return resolvedVideoConfig.value?.embed_code || null
})



watch(
	() => videoEmbedCode.value,
	(code) => {
		if (
			code &&
			!document.querySelector('script[src*="player.vimeo.com/api/player.js"]')
		) {
			const script = document.createElement("script")
			script.src = "https://player.vimeo.com/api/player.js"
			script.async = true
			document.body.appendChild(script)
		}
	},
	{ immediate: true }
)


</script>


<template>
	<div id="cta-video-1">
		<div class="grid grid-cols-1 md:grid-cols-2" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(modelValue.container?.properties, screenType)
		}">

			<div @click="() => {
				sendMessageToParent('activeBlock', indexBlock)
				sendMessageToParent('activeChildBlock', bKeys[0])
			}">
				<div class="w-full flex justify-center items-center min-h-[200px]"
					:style="getStyles(modelValue?.video?.video_setup?.container?.properties, screenType)">

					<template v-if="isVideoByUrl && videoSource">
						<!-- <video 
							class="w-full h-auto" 
							controls 
							:src="modelValue?.video?.video_setup?.source"
							v-bind="modelValue.video.video_setup.attributes"
							:style="getStyles(modelValue.video.video_setup?.properties, screenType)">
						</video> -->

						<iframe class="w-full aspect-video" :src="videoSource" frameborder="0" allowfullscreen />
					</template>

					<template
						v-else-if="!isVideoByUrl && videoEmbedCode">
						<div 
							class="w-full h-auto" 
							v-html="videoEmbedCode"
							:style="getStyles(modelValue.video.video_setup?.properties, screenType)"
						>
					    </div>
					</template>

					<template v-else>
						<FontAwesomeIcon :icon="['fas', 'video']" class="text-gray-400 text-6xl" />
					</template>
				</div>
			</div>

			<!-- 📝 Text & Button Block -->
			<div class="flex flex-col justify-center"
				:style="getStyles(modelValue?.text_block?.properties, screenType)">
				<div class="max-w-xl mx-auto w-full" @click="() => {
					sendMessageToParent('activeBlock', indexBlock)
				}">
					<Editor v-if="modelValue?.text" v-model="modelValue.text"  @update:modelValue="() => emits('autoSave')" class="mb-6" :uploadImageRoute="{
						name: webpageData.images_upload_route.name,
						parameters: {
							...webpageData.images_upload_route.parameters,
							modelHasWebBlocks: blockData?.id,
						}
					}" />

					<div v-if="modelValue?.button?.show !== false" class="flex justify-center">
						<Button :injectStyle="getStyles(modelValue?.button?.container?.properties, screenType)"
							:label="modelValue?.button?.text" @click.stop="() => {
								sendMessageToParent('activeBlock', indexBlock)
								sendMessageToParent('activeChildBlock', bKeys[1])
							}" />
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
