<script setup lang="ts">
import { computed } from "vue"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const containerStyle = computed(() => getStyles(props.fieldValue?.about?.container?.properties))

const description = computed(() =>
	String(props.fieldValue?.tabs?.description ?? "").replace(/<h1[^>]*>.*?<\/h1>/gis, "")
)
</script>

<template>
	<div class="py-5 md:py-6 lg:py-8" :style="containerStyle">
		<div
			class="w-full text-[13px] md:text-[14px] 2xl:text-[16px] leading-[1.8] text-[#334155]"
			v-html="description" />
	</div>
</template>
