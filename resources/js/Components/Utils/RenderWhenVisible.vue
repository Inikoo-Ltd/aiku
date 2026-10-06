<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 6 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { useIntersectionObserver } from "@vueuse/core"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

withDefaults(defineProps<{ minHeight?: string }>(), { minHeight: "3rem" })

const placeholder = ref<HTMLElement | null>(null)
const isVisible = ref(false)

const { stop } = useIntersectionObserver(
	placeholder,
	([entry]) => {
		if (entry?.isIntersecting) {
			isVisible.value = true
			stop()
		}
	},
	{ rootMargin: "400px 0px" }
)
</script>

<template>
	<slot v-if="isVisible" />
	<div
		v-else
		ref="placeholder"
		class="flex items-center justify-center text-gray-300"
		:style="{ minHeight }">
		<LoadingIcon />
	</div>
</template>
