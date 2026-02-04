<script setup lang="ts">
import { faCube, faLink, faImage, faVideo } from "@fortawesome/free-solid-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import { computed, inject, onMounted, onBeforeUnmount, watch } from "vue"

import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "@/Components/CMS/Webpage/Cta1/Blueprint"
import Button from "@/Components/Elements/Buttons/Button.vue"

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
  fieldValue: {
    container?: any
    text?: string
    text_block?: any
    button?: {
      text: string
      container?: any
      link?: {
        href?: string
        taget?: string
      }
      show?: boolean
    }
    video: {
      video_setup: VideoSetup
    }
  }
  webpageData?: any
  blockData?: Object
  screenType: ScreenType
}>()

const videoSetup = computed(() => props.fieldValue?.video?.video_setup)

const resolvedVideoConfig = computed<ResponsiveVideoConfig | null>(() => {
  const setup = videoSetup.value
  if (!setup) return null

  return (
    (setup as any)[props.screenType] ??
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

let stopWatcher: (() => void) | null = null

onMounted(() => {
  stopWatcher = watch(
    () => videoEmbedCode.value,
    (code) => {
      if (!code) return

      if (!document.getElementById("vimeo-player-api")) {
        const script = document.createElement("script")
        script.id = "vimeo-player-api"
        script.src = "https://player.vimeo.com/api/player.js"
        script.async = true
        document.body.appendChild(script)
      }
    },
    { immediate: true }
  )
})

onBeforeUnmount(() => {
  stopWatcher?.()
})


const layout: any = inject("layout", {})
</script>


<template>

	<div id="cta-video-1">
		<div class="grid grid-cols-1 md:grid-cols-2" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType)
		}">
			<!-- 🖼️ Image Block -->
			<div>
				<div @click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[0]?.key?.join('-'))">
					<div class="w-full flex justify-center items-center min-h-[200px]"
						:style="getStyles(fieldValue?.video?.video_setup?.container?.properties, screenType)">

						<template
							v-if="isVideoByUrl && videoSource">
							<!-- <video class="w-full h-auto" controls :src="videoSource"
								v-bind="fieldValue.video.video_setup.attributes"
								:style="getStyles(fieldValue.video.video_setup?.properties, screenType)"></video> -->
								<iframe class="w-full aspect-video" :src="videoSource" frameborder="0" allowfullscreen />
						</template>

						<template
							v-else-if="!isVideoByUrl && videoEmbedCode">
							<div class="w-full h-auto" v-html="videoEmbedCode"
								:style="getStyles(fieldValue.video.video_setup?.properties, screenType)"></div>
						</template>

						<template v-else>
							<FontAwesomeIcon :icon="['fas', 'video']" class="text-gray-400 text-6xl" />
						</template>
					</div>
				</div>
			</div>

			<!-- 📝 Text & Button Block -->
			<div class="flex flex-col justify-center"
				:style="getStyles(fieldValue?.text_block?.properties, screenType)">
				<div class="max-w-xl mx-auto w-full">
					<div v-html="fieldValue.text" class="mb-6"></div>

					<div v-if="fieldValue?.button?.show !== false" class="flex justify-center">
						<a :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.taget"
							typeof="button">
							<Button :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
								:label="fieldValue?.button?.text" />
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
