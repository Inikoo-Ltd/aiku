<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import type { BlockProperties, LinkProperties } from "@/types/Announcement"

library.add(faTimes)

defineProps<{
    announcementData?: {
        fields: {
            text_1: {
                text: string
                block_properties: BlockProperties
            }
            button_1: {
                link: LinkProperties
                text: string
                container: {
                    properties: BlockProperties
                }
            }
            countdown: {
                date: string
                expired_text?: string
            }
        }
        container_properties: BlockProperties
    }
}>()

const onClickClose = () => {
    window.parent.postMessage('close_button_click', '*');
}
</script>

<template>
    <div :style="getStyles(announcementData?.container_properties)">
        <div class="flex flex-wrap gap-x-4 items-center justify-center w-full">
            <div
                v-if="announcementData?.fields?.text_1"
                class="text-sm text-center md:text-left"
                v-html="announcementData?.fields.text_1.text"
                :style="getStyles(announcementData?.fields.text_1.block_properties, null, { toRemove: ['position', 'top', 'left'] })"
            >

            </div>

            <div class="relative">
                <a
                    v-if="announcementData?.fields?.button_1?.text"
                    @click="() => (onClickClose())"
                    :href="announcementData?.fields.button_1.link.href || '#'"
                    :target="announcementData?.fields.button_1.link.target"
                    v-html="announcementData?.fields.button_1.text"
                    class="mt-3 md:mt-0 inline-flex items-center"
                    :style="getStyles(announcementData?.fields.button_1?.container?.properties)"
                >
                </a>
            </div>
        </div>
    </div>
</template>
