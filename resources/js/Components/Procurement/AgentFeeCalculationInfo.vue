<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-13 11:49
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronRight, faInfoCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faChevronDown, faChevronRight, faInfoCircle)

defineProps<{
	items: string[]
}>()

const isOpen = ref(false)
</script>

<template>
	<div v-if="items.length" class="text-xs">
		<button type="button" class="inline-flex items-center gap-1 text-[var(--app-accent)] hover:underline" :aria-expanded="isOpen" @click="isOpen = !isOpen">
			<FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
			{{ ctrans("How is the agent fee calculated?") }}
			<FontAwesomeIcon :icon="isOpen ? 'fal fa-chevron-down' : 'fal fa-chevron-right'" class="text-[10px]" fixed-width aria-hidden="true" />
		</button>
		<ul v-if="isOpen" class="mt-2 list-disc space-y-1 rounded-md border border-gray-200 bg-gray-50 py-2 pl-7 pr-3 text-gray-600">
			<li v-for="(item, index) in items" :key="index">{{ item }}</li>
		</ul>
	</div>
</template>
