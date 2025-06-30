<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed, inject } from "vue";
import { faPresentation, faLink, faPaperclip } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { getStyles } from "@/Composables/styles.js";
import { v4 as uuidv4 } from "uuid";

library.add(faPresentation, faLink, faPaperclip);

const props = defineProps<{
	fieldValue: any;
	webpageData?: any;
	blockData?: Object;
	screenType: "mobile" | "tablet" | "desktop"
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

const layout: any = inject("layout", {})
const iframeStyles = computed(() => {
	const baseStyles = {
		...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
		...getStyles(props.fieldValue.container?.properties, props.screenType),
		width : 'auto'
	};
	return baseStyles;
});

</script>

<template>
	<div id="iframe" :style="iframeStyles">
		<iframe
			:style="getStyles(props.fieldValue.container?.properties, props.screenType)"
			:title="props.fieldValue?.title || `iframe-${uuidv4()}`"
			:src="props.fieldValue?.link"
			class="w-full max-w-full h-auto block border-0"
			allowfullscreen
			loading="lazy"
		/>
	</div>
</template>
