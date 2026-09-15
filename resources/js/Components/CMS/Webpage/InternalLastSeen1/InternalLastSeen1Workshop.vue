<script setup lang="ts">
import { inject } from "vue"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: {}
    indexBlock?: number
    screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "autoSave"): void
}>()

const layout = inject('layout', retinaLayoutStructure)
</script>

<template>
    <div :id="modelValue?.id ? modelValue?.id : 'internal-last-seen-1-workshop' + indexBlock" class="w-full pb-6" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType),
        width: 'auto'
    }">
        <!-- Title -->
        <div class="px-3 py-6 pb-2">
            <div class="text-3xl font-semibold">
                <!-- <div v-html="fieldValue.title"></div> -->
                <div>
                    <p style="text-align: center">{{ ctrans("Last seen") }}<span v-if="layout.app.environment === 'local'" class="ml-2 bg-red-500">(Internal)</span></p>
                </div>
            </div>
        </div>

        <div class="py-4">
            <div class="h-48 flex text-lg font-semibold flex-col items-center justify-center w-full bg-gray-200 border border-gray-300">
                <div>{{ ctrans("No recommendations to preview") }}</div>
                <div class="text-sm italic text-gray-500 font-normal">{{ ctrans("Last Seen is very specific to user behavior, will show real recommendations on live website.") }}</div>
            </div>
        </div>
    </div>
</template>
