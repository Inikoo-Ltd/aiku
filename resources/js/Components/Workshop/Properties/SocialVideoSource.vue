<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faUpload, faVideo } from "@fal"
import { faInstagram, faTiktok } from "@fortawesome/free-brands-svg-icons"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import type { VideoCarouselSource } from "@/Components/CMS/Webpage/VideoCarousel1/types"

const props = defineProps<{
	modelValue: VideoCarouselSource | null
	uploadRoutes: routeType
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: VideoCarouselSource | null): void
}>()

const link = ref("")
const isLoading = ref(false)
const error = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const platformIcon = computed(() => {
	if (props.modelValue?.platform === "instagram") return faInstagram
	if (props.modelValue?.platform === "tiktok") return faTiktok
	return faVideo
})

const platformLabel = computed(() => {
	if (props.modelValue?.platform === "instagram") return ctrans("Instagram reel")
	if (props.modelValue?.platform === "tiktok") return ctrans("TikTok video")
	return ctrans("Uploaded video")
})

const errorFrom = (exception: any): string =>
	exception?.response?.data?.errors?.url?.[0]
	?? exception?.response?.data?.errors?.video?.[0]
	?? exception?.response?.data?.message
	?? ctrans("Something went wrong, please try again")

const send = async (routeName: string, payload: FormData | Record<string, string>) => {
	isLoading.value = true
	error.value = null
	try {
		const response = await axios.post(route(routeName, props.uploadRoutes.parameters), payload)
		emits("update:modelValue", response.data)
		link.value = ""
	} catch (exception) {
		error.value = errorFrom(exception)
	} finally {
		isLoading.value = false
	}
}

const addLink = () => {
	if (link.value.trim()) {
		send("grp.models.model_has_web_block.social_videos.store", { url: link.value.trim() })
	}
}

const onFileChosen = (event: Event) => {
	const file = (event.target as HTMLInputElement).files?.[0]
	if (!file) return
	const formData = new FormData()
	formData.append("video", file, file.name)
	send("grp.models.model_has_web_block.videos.store", formData)
	;(event.target as HTMLInputElement).value = ""
}
</script>

<template>
	<div class="space-y-3">
		<div v-if="modelValue" class="flex items-start gap-3 rounded border border-gray-200 p-2">
			<div class="relative aspect-[9/16] w-20 shrink-0 overflow-hidden rounded bg-gray-900">
				<img v-if="modelValue.poster_url" :src="modelValue.poster_url" alt="" class="h-full w-full object-cover" />
				<video v-else-if="modelValue.video_url" :src="modelValue.video_url + '#t=0.1'" muted preload="metadata" class="h-full w-full object-cover" />
			</div>
			<div class="min-w-0 flex-1 text-sm">
				<div class="flex items-center gap-1.5 font-semibold text-gray-700">
					<FontAwesomeIcon :icon="platformIcon" fixed-width />
					{{ platformLabel }}
				</div>
				<a v-if="modelValue.permalink" :href="modelValue.permalink" target="_blank" rel="noopener"
					class="block truncate text-xs text-gray-500 hover:underline">{{ modelValue.permalink }}</a>
				<button type="button" class="mt-2 text-xs text-gray-500 hover:text-red-500 hover:underline"
					@click="emits('update:modelValue', null)">
					{{ ctrans("Remove video") }}
				</button>
			</div>
		</div>

		<div class="flex gap-2">
			<InputText v-model="link" class="min-w-0 flex-1" size="small" :disabled="isLoading"
				:placeholder="ctrans('Instagram or TikTok link')" @keydown.enter.prevent="addLink" />
			<Button type="tertiary" :label="ctrans('Add')" :disabled="isLoading || !link.trim()" @click="addLink">
				<template #icon>
					<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width />
				</template>
			</Button>
		</div>

		<div class="flex items-center gap-2">
			<Button type="tertiary" :label="ctrans('Upload video file')" :disabled="isLoading" @click="fileInput?.click()">
				<template #icon>
					<FontAwesomeIcon :icon="faUpload" fixed-width />
				</template>
			</Button>
			<span class="text-xs text-gray-400">{{ ctrans("MP4 or WebM, up to 50 MB") }}</span>
			<input ref="fileInput" type="file" accept="video/mp4,video/webm" class="hidden" @change="onFileChosen" />
		</div>

		<div v-if="isLoading" class="flex items-center gap-2 text-xs text-gray-500">
			<LoadingIcon />
			{{ ctrans("Copying the video, this can take a minute") }}
		</div>

		<p v-if="error" class="flex items-start gap-1 text-xs text-red-600">
			<FontAwesomeIcon :icon="faTimes" class="mt-0.5" fixed-width />
			{{ error }}
		</p>
	</div>
</template>
