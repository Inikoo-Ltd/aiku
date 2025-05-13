<script setup lang="ts">
import { ref, watch, provide } from 'vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { routeType } from '@/types/route'

const modelValue = defineModel<any>() // enables v-model
const props = defineProps<{
  blueprint: any
  uploadRoutes: routeType
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: PaddingMarginModel): void
}>()

const onSaveWorkshopFromId = (blockId: number, from?: string) => {
  emit("update:modelValue", modelValue.value);
};

provide("onSaveWorkshopFromId", onSaveWorkshopFromId);

</script>

<template>
    <SideEditor
      :blueprint="blueprint"
      v-model="modelValue"
      :uploadImageRoute="uploadRoutes"
    />
</template>
