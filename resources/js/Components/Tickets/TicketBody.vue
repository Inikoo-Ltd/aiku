<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import Image from "@/Common/Components/Image.vue"

const props = defineProps<{
    text: string | null
    images?: Record<string, string>[]
}>()

const URL_PATTERN = /(https?:\/\/[^\s<>"']+)/g

const parts = computed(() => (props.text ?? "").split(URL_PATTERN).map((chunk, index) => ({ chunk, isLink: index % 2 === 1 })))
</script>

<template>
    <div>
        <p v-if="text" class="text-sm whitespace-pre-wrap break-words">
            <template v-for="(part, index) in parts" :key="index">
                <a v-if="part.isLink" :href="part.chunk" target="_blank" rel="noopener" class="text-indigo-600 hover:underline break-all">{{ part.chunk }}</a>
                <template v-else>{{ part.chunk }}</template>
            </template>
        </p>
        <div v-if="images?.length" class="mt-2 flex flex-wrap gap-2">
            <a v-for="(image, index) in images" :key="index" :href="image.original" target="_blank" rel="noopener" class="block">
                <Image :src="image" alt="" image-cover class="h-32 w-32 rounded border border-gray-200 hover:opacity-90" />
            </a>
        </div>
    </div>
</template>
