<script setup lang="ts">
import { faCube, faLink, faImage, faVideo } from "@fortawesome/free-solid-svg-icons";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue";
import { getStyles } from "@/Composables/styles";
import { sendMessageToParent } from "@/Composables/Workshop";
import Blueprint from "@/Components/CMS/Webpage/Cta1/Blueprint";
import { onMounted, watch } from "vue";

library.add(faCube, faLink, faImage, faVideo);

const props = defineProps<{
	modelValue: {
		container?: any;
		text?: string;
		text_block?: any;
		button?: {
			text: string;
			container?: any;
		};
		video: {
			video_setup: {
				by_url: boolean;
				source: string | null;
				embed_code: string | null;
				properties?: any;
				container?: any;
				attributes?: any;
			};
		};
	};
	webpageData?: any;
	blockData?: Object;
	screenType: "mobile" | "tablet" | "desktop";
}>();

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void;
	(e: "autoSave"): void;
}>();

// ✅ Load embed script dynamically when embed_code is present
onMounted(() => {
	watch(
		() => props.modelValue.video?.video_setup?.embed_code,
		(code) => {
			if (
				code &&
				!document.querySelector('script[src*="player.vimeo.com/api/player.js"]')
			) {
				const script = document.createElement("script");
				script.src = "https://player.vimeo.com/api/player.js";
				script.async = true;
				document.body.appendChild(script);
			}
		},
		{ immediate: true }
	);
});
</script>

<template>
		<div class="grid grid-cols-1 md:grid-cols-1 relative"
		:style="getStyles(modelValue.container?.properties, screenType)">
		
		<!-- 🎥 Video Column -->
		<div @click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[0]?.key?.join('-'))">
			<div class="w-full flex justify-center items-center min-h-[200px]"
				:style="getStyles(modelValue?.video?.video_setup?.container?.properties, screenType)">
				
				<!-- 🎬 Direct Video URL -->
				<template v-if="modelValue?.video?.video_setup?.by_url && modelValue?.video?.video_setup?.source">
					<video
						class="w-full h-auto"
						controls
						:src="modelValue?.video?.video_setup?.source"
						v-bind="modelValue.video.video_setup.attributes"
						:style="getStyles(modelValue.video.video_setup?.properties, screenType)"
					></video>
				</template>

				<!-- 🌐 Embed HTML -->
				<template v-else-if="!modelValue?.video?.video_setup?.by_url && modelValue?.video?.video_setup?.embed_code">
					<div
						class="w-full h-auto"
						v-html="modelValue?.video?.video_setup?.embed_code"
						:style="getStyles(modelValue.video.video_setup?.properties, screenType)"
					></div>
				</template>

				<!-- ❌ Fallback Icon if empty -->
				<template v-else>
					<FontAwesomeIcon :icon="['fas', 'video']" class="text-gray-400 text-6xl" />
				</template>
			</div>
		</div>
	</div>
</template>
