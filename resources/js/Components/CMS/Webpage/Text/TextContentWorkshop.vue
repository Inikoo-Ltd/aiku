<script setup lang="ts">
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from '@/Composables/styles'
import { inject } from "vue";
import Blueprint from "./Blueprint";
import { sendMessageToParent } from "@/Composables/Workshop"

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object,
    indexBlock: number
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
    (e: 'autoSave'): void
}>()

const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []
const layout: any = inject("layout", {})
</script>

<template>
    <div id="text">
        <div @click="() => {
            sendMessageToParent('activeBlock', indexBlock)
            sendMessageToParent('activeChildBlock', bKeys[1])
        }" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(modelValue.container?.properties, screenType)
        }">
            <EditorV2 v-model="modelValue.value" @focus="() => {
                sendMessageToParent('activeBlock', indexBlock)
            }" @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
                name: webpageData.images_upload_route.name,
                parameters: { modelHasWebBlocks: blockData.id }
            }" />
        </div>

    </div>

</template>