<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from "vue";
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


const iframeStyles = computed(() => {
	const baseStyles = getStyles(props.fieldValue?.container?.properties,props.screenType) || {};
	return baseStyles;
});
</script>

<template>
 <section>
		<div v-if="!props.fieldValue?.link?.includes('wowsbar')" class="relative">
			<iframe :title="fieldValue?.title || `iframe-${uuidv4()}`" :src="fieldValue?.link" :style="iframeStyles" allowfullscreen loading="lazy" />
		</div>

		<div v-else :style="iframeStyles">
			<iframe :title="fieldValue?.title || `iframe-${uuidv4()}`" :src="fieldValue?.link" loading="lazy" class="w-full h-full overflow-hidden" allowfullscreen/>
		</div>
	</section>
</template>
