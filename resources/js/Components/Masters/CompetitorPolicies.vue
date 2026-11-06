<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 19:15:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink } from "@fal"

library.add(faExternalLink)

defineProps<{
    pages: { url: string; title: string; text: string }[]
}>()
</script>

<template>
    <div class="mx-4 mt-4 space-y-4 text-sm">
        <div v-if="!pages.length" class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-gray-500">
            {{ ctrans("No policy pages read yet. They are read with the dossier, on the weekly refresh or when you press Refresh dossier.") }}
        </div>
        <details v-for="page in pages" :key="page.url" class="rounded-lg border border-gray-200 bg-white px-4 py-3" open>
            <summary class="cursor-pointer text-gray-800">
                {{ page.title || page.url }}
                <a :href="page.url" target="_blank" rel="noopener noreferrer" class="ml-2 text-xs font-medium text-gray-500 hover:underline" @click.stop>{{ page.url }} <FontAwesomeIcon icon="fal fa-external-link" /></a>
            </summary>
            <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap font-sans leading-relaxed text-gray-700">{{ page.text }}</pre>
        </details>
    </div>
</template>
