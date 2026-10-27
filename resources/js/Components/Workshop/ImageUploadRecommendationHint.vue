<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle } from "@fal"
import {
	generalImageUploadRecommendation,
	type ImageUploadRecommendation,
	recommendedImageSize,
} from "@/Composables/imageUploadRecommendations"

library.add(faInfoCircle)

const props = defineProps<{
	recommendation?: ImageUploadRecommendation | null
	aspectRatio?: number | null
}>()

const activeRecommendation = computed(() => props.recommendation ?? generalImageUploadRecommendation)

const recommendedSize = computed(() => recommendedImageSize(activeRecommendation.value, props.aspectRatio))
</script>

<template>
	<div class="mt-2 flex gap-2 rounded border border-[--app-accent-muted] bg-[--app-accent-soft] px-3 py-2 text-xs text-gray-700">
		<FontAwesomeIcon icon="fal fa-info-circle" class="mt-0.5 text-[--app-accent]" fixed-width aria-hidden="true" />
		<div class="space-y-0.5">
			<div>
				{{ ctrans("Recommended size") }}:
				<span class="font-semibold whitespace-nowrap">{{ recommendedSize }}</span>
			</div>
			<div v-if="activeRecommendation.focus">{{ ctrans(activeRecommendation.focus) }}</div>
			<div v-if="activeRecommendation.note" class="text-gray-500">{{ ctrans(activeRecommendation.note) }}</div>
		</div>
	</div>
</template>
