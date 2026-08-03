<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, ref } from "vue"
import type { BlockProperties } from "@/types/Announcement"
import { blueprint, defaultData } from "@/Components/Websites/Announcement/Templates/Information/AnnouncementInformation1/Blueprint"

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
    _parentComponent?: Element
    isEditable?: boolean
    isToSelectOnly?: boolean
}>()

const emits = defineEmits<{
    (e: 'templateClicked', value: typeof defaultData): void
}>()

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
        class="relative isolate flex flex-wrap justify-center md:justify-between items-center gap-x-6 px-6 sm:px-3.5 transition-all"
        :style="getStyles(announcementData?.container_properties)"
    >
        <div
            @click="() => onClickOpenFieldWorkshop(2)"
            class="announcement-component-editable"
            v-html="announcementData?.fields.text_2.text"
            :style="getStyles(announcementData?.fields.text_2.block_properties)"
        >
        </div>

        <div
            @click="() => onClickOpenFieldWorkshop(1)"
            class="announcement-component-editable"
            v-html="announcementData?.fields.text_1.text"
            :style="getStyles(announcementData?.fields.text_1.block_properties)"
        >
        </div>
    </div>

    <div
        v-else @click="() => emits('templateClicked', defaultData)"
        class="inset-0 absolute"
    >
    </div>
</template>
