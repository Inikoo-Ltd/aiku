<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from "vue";
import { faPaperclip } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { getStyles } from "@/Composables/styles.js";
import { v4 as uuidv4 } from "uuid";

library.add(faPaperclip);

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
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
	const baseStyles = getStyles(props.modelValue?.container?.properties,props.screenType) || {};
	return baseStyles;
});
</script>


<template>
	<div v-if="!modelValue.link || modelValue?.link == ''" type="button"
		class="relative block w-full p-12 text-center hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
		<font-awesome-icon :icon="['fal', 'paperclip']" class="mx-auto h-12 w-12 text-gray-400" />
		<span class="mt-2 block text-sm font-semibold text-gray-900">I Frame</span>
	</div>

	
	<section v-else>
		<div v-if="!props.modelValue?.link?.includes('wowsbar')" class="relative">
			<iframe :title="modelValue?.title || `iframe-${uuidv4()}`" :src="modelValue?.link" :style="iframeStyles" allowfullscreen loading="lazy" />
		</div>

		<div v-else :style="iframeStyles">
			<iframe :title="modelValue?.title || `iframe-${uuidv4()}`" :src="modelValue?.link" loading="lazy" class="w-full h-full overflow-hidden" allowfullscreen/>
		</div>
	</section>
</template>
