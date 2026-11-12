<!--
  - Author: Rifqi Taufiqurrohman
  - Created: Thu, 12 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExternalLink, faInfoCircle } from "@fal"

library.add(faExternalLink, faInfoCircle)

defineProps<{
    items: {
        label: string
        value: string | number | null
        href?: string | null
        hint?: string
        type?: "date"
        code?: boolean
    }[]
}>()
</script>

<template>
    <dl class="divide-y divide-gray-100 rounded-xl bg-white ring-1 ring-gray-200">
        <div v-for="item in items" :key="item.label" class="grid grid-cols-1 gap-1 px-4 py-3 text-sm sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-4">
            <dt class="text-gray-500">{{ item.label }}</dt>
            <dd class="min-w-0 text-gray-900">
                <template v-if="item.value === null || item.value === ''">
                    <span class="text-gray-300">—</span>
                </template>
                <a v-else-if="item.href" :href="item.href" target="_blank" rel="noopener" class="break-all hover:text-[var(--app-accent)] hover:underline">
                    {{ item.value }}
                    <FontAwesomeIcon icon="fal fa-external-link" class="ml-1 text-[10px] text-gray-400" fixed-width aria-hidden="true" />
                </a>
                <span v-else-if="item.type === 'date'">{{ useFormatTime(String(item.value), { formatTime: 'aiku' }) }}</span>
                <span v-else :class="item.code ? 'whitespace-nowrap font-mono' : 'break-words'">{{ item.value }}</span>
                <span v-if="item.hint" v-tooltip="item.hint" class="ml-1.5 cursor-help text-gray-400">
                    <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
                </span>
            </dd>
        </div>
    </dl>
</template>
