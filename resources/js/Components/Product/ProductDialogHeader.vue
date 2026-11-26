<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-26
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import Image from "@common/Components/Image.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage } from "@fal"

defineProps<{
	code: string | null
	name?: string | null
	image?: object | null
}>()
</script>

<template>
	<div class="flex items-center gap-3">
		<div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-50">
			<Image v-if="image" :src="image" imageCover class="h-20 w-20" />
			<FontAwesomeIcon v-else :icon="faImage" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
		</div>
		<div class="min-w-0">
			<div class="whitespace-nowrap font-semibold text-gray-900">{{ code }}</div>
			<div v-if="name" class="line-clamp-2 text-xs text-gray-600">{{ name }}</div>
		</div>
	</div>
</template>
