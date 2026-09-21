<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLanguage } from "@far"

defineProps<{
    translation?: { text: string; language: string | null }
    isTranslating?: boolean
}>()

defineEmits<{ (e: "translate"): void }>()
</script>

<template>
    <div v-if="translation" class="mt-2 border-l-2 border-gray-300 pl-2 text-xs text-gray-600">
        <div class="whitespace-pre-line">{{ translation.text }}</div>
        <div v-if="translation.language" class="mt-0.5 flex items-center gap-1 opacity-70">
            <FontAwesomeIcon :icon="faLanguage" />
            <span>{{ translation.language }}</span>
        </div>
    </div>
    <button v-else type="button" :disabled="isTranslating"
        class="mt-1 flex items-center gap-1 text-xs text-gray-500 underline hover:text-gray-800 disabled:opacity-50"
        @click="$emit('translate')">
        <FontAwesomeIcon :icon="faLanguage" />
        {{ isTranslating ? ctrans("Translating…") : ctrans("Translate") }}
    </button>
</template>
