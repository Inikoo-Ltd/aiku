<script setup lang="ts">
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from '@/Composables/styles'

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
}>()

const emits = defineEmits<{
    (e: 'autoSave'): void
}>()



</script>

<template>
    <div id="blockTextContent" :style="getStyles(modelValue?.container.properties)">
        <EditorV2 
            v-model="modelValue.value"
            @update:modelValue="(e) => (emits('autoSave'))"
        />
    </div>
</template>