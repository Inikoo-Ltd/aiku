<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import type { BlockProperties } from "@/types/Announcement"

library.add(faTimes)

defineProps<{
    announcementData?: {
        fields: {
            text_1: {
                text: string
                block_properties?: BlockProperties
            }
            text_2: {
                text: string
                block_properties?: BlockProperties
            }
        }
        container_properties: BlockProperties
    }
}>()
</script>

<template>
    <div
        class="relative isolate flex flex-wrap justify-center md:justify-between items-center gap-x-6 px-6 sm:px-3.5 transition-all"
        :style="getStyles(announcementData?.container_properties)"
    >
        <div
            v-html="announcementData?.fields.text_2.text"
            :style="getStyles(announcementData?.fields.text_2.block_properties)"
        >
        </div>

        <div
            v-html="announcementData?.fields.text_1.text"
            :style="getStyles(announcementData?.fields.text_1.block_properties)"
        >
        </div>
    </div>
</template>
