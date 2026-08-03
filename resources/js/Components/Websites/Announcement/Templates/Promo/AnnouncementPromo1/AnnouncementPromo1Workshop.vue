<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref, inject } from "vue"
import type { BlockProperties, LinkProperties } from "@/types/Announcement"
import { blueprint, defaultData } from "@/Components/Websites/Announcement/Templates/Promo/AnnouncementPromo1/Blueprint"

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
    _parentComponent?: Element
    isEditable?: boolean
    isToSelectOnly?: boolean
}>()

const emits = defineEmits<{
    (e: 'templateClicked', template: typeof defaultData): void
}>()

const _text_1 = ref(null)

const onClickClose = () => {
    window.parent.postMessage('close_button_click', '*');
}

const openFieldWorkshop = inject('openFieldWorkshop', ref<number | null>(null))
const onClickOpenFieldWorkshop = (index?: number) => {
    if (openFieldWorkshop && index) {
        openFieldWorkshop.value = index
    }
}

defineExpose({
    fieldSideEditor: blueprint
})
</script>

<template>
    <div
        v-if="!isToSelectOnly"
        :style="getStyles(announcementData?.container_properties)"
    >
        <div class="flex flex-wrap gap-x-4 items-center justify-center w-full">
            <div
                v-if="announcementData?.fields?.text_1"
                ref="_text_1"
                @click="() => (onClickOpenFieldWorkshop(1))"
                class="text-sm announcement-component-editable text-center md:text-left"
                v-html="announcementData?.fields.text_1.text"
                :style="getStyles(announcementData?.fields.text_1.block_properties, null, { toRemove: ['position', 'top', 'left'] })"
            >

            </div>

            <div class="relative">
                <div v-if="isEditable" @click="() => (onClickOpenFieldWorkshop(2))" class="absolute inset-0 announcement-component-editable " />
                <a
                    v-if="announcementData?.fields?.button_1?.text"
                    @click="() => (onClickClose())"
                    :href="announcementData?.fields.button_1.link.href || '#'"
                    :target="announcementData?.fields.button_1.link.target"
                    v-html="announcementData?.fields.button_1.text"
                    class="mt-3 md:mt-0 inline-flex items-center announcement-component-editable"
                    :style="getStyles(announcementData?.fields.button_1?.container?.properties)"
                >
                </a>
            </div>
        </div>
    </div>

    <div
        v-else @click="() => emits('templateClicked', defaultData)"
        class="inset-0 absolute"
    >
    </div>

</template>
