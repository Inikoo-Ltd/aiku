<script setup lang="ts">
import { faCube, faLink, faImage, faVideo } from "@fortawesome/free-solid-svg-icons";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { getStyles } from "@/Composables/styles";
import { sendMessageToParent } from "@/Composables/Workshop";
import Blueprint from "@/Components/CMS/Webpage/Cta1/Blueprint";
import { onMounted, watch, inject } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";


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
							v-if="fieldValue?.video?.video_setup?.by_url && fieldValue?.video?.video_setup?.source">
							<video class="w-full h-auto" controls :src="fieldValue?.video?.video_setup?.source"
								v-bind="fieldValue.video.video_setup.attributes"
								:style="getStyles(fieldValue.video.video_setup?.properties, screenType)"></video>
						</template>

						<template
							v-else-if="!fieldValue?.video?.video_setup?.by_url && fieldValue?.video?.video_setup?.embed_code">
							<div class="w-full h-auto" v-html="fieldValue?.video?.video_setup?.embed_code"
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

					<div class="flex justify-center">
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
