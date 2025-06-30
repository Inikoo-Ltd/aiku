<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed, inject } from "vue";
import { faPaperclip } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { getStyles } from "@/Composables/styles.js";
import { v4 as uuidv4 } from "uuid";
import Blueprint from "./Blueprint";
import { sendMessageToParent } from "@/Composables/Workshop";

library.add(faPaperclip);

const props = defineProps<{
	modelValue: any;
	webpageData?: any;
	blockData?: Object;
	indexBlock: number;
	screenType: "mobile" | "tablet" | "desktop";
}>();

const screenWidth = ref(window.innerWidth);

const updateScreenWidth = () => {
	screenWidth.value = window.innerWidth;
};

onMounted(() => {
	window.addEventListener("resize", updateScreenWidth);
});

onUnmounted(() => {
	window.removeEventListener("resize", updateScreenWidth);
});

const layout: any = inject("layout", {});
const iframeStyles = computed(() => {
	return {
		...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
		...getStyles(props.modelValue.container?.properties, props.screenType),
		width : 'auto'
	};
});

const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || [];
</script>

<template>
	<div id="iframe" :style="iframeStyles">
		<!-- Placeholder if no link -->
		<div
			v-if="!props.modelValue.link || props.modelValue?.link === ''"
			class="relative block w-full p-12 text-center hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
			role="button"
			@click="() => sendMessageToParent('activeBlock', props.indexBlock)"
		>
			<font-awesome-icon :icon="['fal', 'paperclip']" class="mx-auto h-12 w-12 text-gray-400" />
			<span class="mt-2 block text-sm font-semibold text-gray-900">I Frame</span>
		</div>

		<iframe
			v-else
			@click="() => sendMessageToParent('activeBlock', props.indexBlock)"
			:title="props.modelValue?.title || `iframe-${uuidv4()}`"
			:src="props.modelValue?.link"
			:style="getStyles(props.modelValue.container?.properties, props.screenType)"
			class="w-full max-w-full h-auto block border-0"
			allowfullscreen
			loading="lazy"
		/>
	</div>
</template>