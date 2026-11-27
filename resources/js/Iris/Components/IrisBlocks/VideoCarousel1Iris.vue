<script setup lang="ts">
import { inject } from "vue"
import IrisButton from "@/Iris/Components/IrisButton.vue"
import VideoCarousel1 from "@/Components/CMS/Webpage/VideoCarousel1/VideoCarousel1.vue"
import { getStyles } from "@/Composables/styles"
import type { VideoCarouselFieldValue } from "@/Components/CMS/Webpage/VideoCarousel1/types"

defineProps<{
	fieldValue: VideoCarouselFieldValue
	screenType: "mobile" | "tablet" | "desktop"
	indexBlock?: number
}>()

const layout: any = inject("layout", {})
</script>

<template>
	<div :id="fieldValue?.id ? fieldValue.id : 'video-carousel-1' + indexBlock" component="video-carousel-1"
		:style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue?.container?.properties, screenType),
		}">
		<VideoCarousel1 :fieldValue="fieldValue" :screenType="screenType">
			<template #heading>
				<div v-if="fieldValue?.text" class="mb-6" v-html="fieldValue.text" />
			</template>
			<template #button>
				<div v-if="fieldValue?.button?.show !== false && fieldValue?.button?.text" class="mt-6 flex justify-center">
					<a :href="fieldValue.button.link?.href" :target="fieldValue.button.link?.target">
						<IrisButton :injectStyle="getStyles(fieldValue.button.container?.properties, screenType)"
							:label="fieldValue.button.text" />
					</a>
				</div>
			</template>
		</VideoCarousel1>
	</div>
</template>
