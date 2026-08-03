<script setup lang="ts">
import { ref, computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faMinus } from "@fortawesome/free-solid-svg-icons"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
	faqs: any
}>()

const openIndex = ref<number | null>(0)

const toggle = (index: number) => {
	openIndex.value = openIndex.value === index ? null : index
}

const containerStyle = computed(() => getStyles(props.fieldValue?.faq?.container?.properties))
</script>

<template>
	<div class="w-full">
		<details v-for="(faq, index) in faqs" :key="index" class="group border-b border-[#A7ADB4]">
			<summary
				class="flex cursor-pointer list-none items-center justify-between py-5 text-xl font-semibold text-[#0F1E2E]">
				<span>{{ faq.question }}</span>

				<span
					class="text-4xl font-normal leading-none transition-transform group-open:rotate-45">
					+
				</span>
			</summary>

			<div class="pb-6 pr-12 text-base leading-7 text-gray-600" v-html="faq.answer">
			</div>
		</details>
	</div>
</template>
