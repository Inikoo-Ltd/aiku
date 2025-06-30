<script setup lang="ts">
import { faCube, faLink, faImage, faVideo } from "@fortawesome/free-solid-svg-icons";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue";
import { getStyles } from "@/Composables/styles";
import { sendMessageToParent } from "@/Composables/Workshop";
import Blueprint from "@/Components/CMS/Webpage/CtaVideo1/Blueprint";
import { onMounted, watch } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue"
import { inject } from "vue"

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
	indexBlock?: Number
	webpageData?: any;
	blockData?: Object;
	screenType: "mobile" | "tablet" | "desktop";
}>();

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void;
	(e: "autoSave"): void;
}>();


const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []

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

					<template v-if="modelValue?.video?.video_setup?.by_url && modelValue?.video?.video_setup?.source">
						<video class="w-full h-auto" controls :src="modelValue?.video?.video_setup?.source"
							v-bind="modelValue.video.video_setup.attributes"
							:style="getStyles(modelValue.video.video_setup?.properties, screenType)"></video>
					</template>

					<template
						v-else-if="!modelValue?.video?.video_setup?.by_url && modelValue?.video?.video_setup?.embed_code">
						<div class="w-full h-auto" v-html="modelValue?.video?.video_setup?.embed_code"
							:style="getStyles(modelValue.video.video_setup?.properties, screenType)"></div>
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

					<div class="flex justify-center">
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
