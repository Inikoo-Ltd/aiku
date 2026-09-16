<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faInfoCircle)

defineProps<{
    text: string
}>()
</script>

<template>
    <button
        type="button"
        v-tooltip.top="text"
        :aria-label="text"
        class="ml-1 inline-flex rounded align-middle text-gray-400 transition hover:text-gray-600 focus:outline-none focus-visible:text-gray-600 focus-visible:ring-2 focus-visible:ring-indigo-500">
        <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
    </button>
</template>
