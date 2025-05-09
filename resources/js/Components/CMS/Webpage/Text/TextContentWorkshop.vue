<script setup lang="ts">
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from '@/Composables/styles'

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object,
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
    (e: 'autoSave'): void
}>()


</script>

<template>
    <div id="blockTextContent" :style="getStyles(modelValue?.container.properties,screenType)">
        <EditorV2 
            v-model="modelValue.value" 
            @update:modelValue="() => emits('autoSave')" 
            :uploadImageRoute="{ 
                name: webpageData.images_upload_route.name, 
                parameters: { modelHasWebBlocks: blockData.id } 
            }" 
        />
    </div>
</template>