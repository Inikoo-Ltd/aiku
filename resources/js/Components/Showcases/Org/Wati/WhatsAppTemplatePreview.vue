<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import { computed } from "vue"

interface TemplateButton {
    text: string
    type?: string
    url?: string
    phone_number?: string
}

interface TemplateHeader {
    format?: string
    text?: string
}

const props = defineProps<{
    data: {
        header?: TemplateHeader | null
        body_original?: string | null
        footer?: string | null
        buttons?: TemplateButton[] | null
    }
}>()

const bodySegments = computed(() => {
    const raw = props.data.body_original ?? ""
    const parts: Array<{ type: "text" | "variable"; value: string }> = []
    const regex = /(\{\{\d+\}\})/g
    let lastIndex = 0
    let match: RegExpExecArray | null

    while ((match = regex.exec(raw)) !== null) {
        if (match.index > lastIndex) {
            parts.push({ type: "text", value: raw.slice(lastIndex, match.index) })
        }
        parts.push({ type: "variable", value: match[1] })
        lastIndex = match.index + match[0].length
    }
    if (lastIndex < raw.length) {
        parts.push({ type: "text", value: raw.slice(lastIndex) })
    }

    return parts
})

const hasTextHeader = computed(() => props.data.header?.format === "TEXT" && !!props.data.header?.text)
const hasMediaHeader = computed(() => !!props.data.header?.format && props.data.header?.format !== "TEXT")
</script>

<template>
    <div class="flex items-start justify-center py-4">
        <div class="w-72 bg-[#ece5dd] dark:bg-[#0d1117] rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-[#075e54] text-white px-4 py-3 flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[#128c7e] flex items-center justify-center text-xs font-bold shrink-0">W</div>
                <span class="text-sm font-medium">WhatsApp Preview</span>
            </div>

            <div class="px-3 py-4 min-h-40">
                <div class="bg-white dark:bg-[#1f2937] rounded-lg rounded-tl-none shadow-sm max-w-[92%] overflow-hidden">
                    <div v-if="hasTextHeader" class="px-3 pt-3 font-bold text-gray-900 dark:text-gray-100 text-sm">
                        {{ data.header?.text }}
                    </div>

                    <div v-else-if="hasMediaHeader"
                        class="w-full h-24 bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500 text-xs uppercase tracking-wide">
                        {{ data.header?.format }} media
                    </div>

                    <div class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-wrap">
                        <template v-for="(segment, index) in bodySegments" :key="index">
                            <span v-if="segment.type === 'variable'"
                                class="bg-yellow-100 text-yellow-800 font-mono text-xs px-1 py-0.5 rounded border border-yellow-300">
                                {{ segment.value }}
                            </span>
                            <span v-else>{{ segment.value }}</span>
                        </template>
                        <span v-if="!data.body_original" class="text-gray-400 italic">{{ $t('No body text') }}</span>
                    </div>

                    <div v-if="data.footer" class="px-3 pb-1 text-xs text-gray-400">
                        {{ data.footer }}
                    </div>

                    <div class="px-3 pb-2 text-right text-[10px] text-gray-400">12:00</div>
                </div>

                <div v-if="data.buttons && data.buttons.length" class="mt-0.5 max-w-[92%]">
                    <button
                        v-for="(button, idx) in data.buttons"
                        :key="idx"
                        class="w-full bg-white dark:bg-[#1f2937] border-t border-gray-100 dark:border-gray-700 text-[#075e54] text-sm font-medium py-2 px-3 text-center block cursor-default"
                        :class="idx === data.buttons!.length - 1 ? 'rounded-b-lg' : ''"
                        disabled
                    >
                        {{ button.text }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
