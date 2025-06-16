<script setup lang="ts">
import { faCube, faLink, faImage, faVideo } from "@fortawesome/free-solid-svg-icons";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue";
import { getStyles } from "@/Composables/styles";
import { sendMessageToParent } from "@/Composables/Workshop";
import Blueprint from "@/Components/CMS/Webpage/CTA/Blueprint";
import { onMounted, watch } from "vue";

library.add(faCube, faLink, faImage, faVideo);

const props = defineProps<{
	fieldValue: {
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
	(e: "update:fieldValue", value: string): void;
	(e: "autoSave"): void;
}>();

// ✅ Load embed script dynamically when embed_code is present
onMounted(() => {
	watch(
		() => props.fieldValue.video?.video_setup?.embed_code,
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
	<div class="grid grid-cols-1 md:grid-cols-2 relative"
		:style="getStyles(fieldValue.container?.properties, screenType)">
		
		<!-- 🎥 Video Column -->
		<div @click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[0]?.key?.join('-'))">
			<div class="w-full flex justify-center items-center min-h-[200px]"
				:style="getStyles(fieldValue?.video?.video_setup?.container?.properties, screenType)">
				
				<!-- 🎬 Direct Video URL -->
				<template v-if="fieldValue?.video?.video_setup?.by_url && fieldValue?.video?.video_setup?.source">
					<video
						class="w-full h-auto"
						controls
						:src="fieldValue?.video?.video_setup?.source"
						v-bind="fieldValue.video.video_setup.attributes"
						:style="getStyles(fieldValue.video.video_setup?.properties, screenType)"
					></video>
				</template>

				<!-- 🌐 Embed HTML -->
				<template v-else-if="!fieldValue?.video?.video_setup?.by_url && fieldValue?.video?.video_setup?.embed_code">
					<div
						class="w-full h-auto"
						v-html="fieldValue?.video?.video_setup?.embed_code"
						:style="getStyles(fieldValue.video.video_setup?.properties, screenType)"
					></div>
				</template>

				<!-- ❌ Fallback Icon if empty -->
				<template v-else>
					<FontAwesomeIcon :icon="['fas', 'video']" class="text-gray-400 text-6xl" />
				</template>
			</div>
		</div>

		<!-- 📝 Text & Button Column -->
		<div class="flex flex-col justify-center"
			:style="getStyles(fieldValue?.text_block?.properties, screenType)">
			<div class="max-w-xl mx-auto w-full">
				<!-- Rich Text Editor -->
				 <div v-html="fieldValue.text"></div>
				<!-- CTA Button -->
				<div class="flex justify-center">
					<a :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.taget"
					typeof="button" :style="getStyles(fieldValue?.button?.container?.properties, screenType)"
						class="mt-10 flex items-center justify-center w-64 gap-x-6">
						{{ fieldValue?.button?.text }}
					</a>
				</div>
			</div>
		</div>
	</div>
</template>
