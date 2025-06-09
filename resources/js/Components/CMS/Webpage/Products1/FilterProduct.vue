<script setup lang="ts">
import { defineProps, defineEmits } from 'vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import BlueprintFilter from './BlueprintFilter'

const props = defineProps<{
  modelValue: any
}>()

const emit = defineEmits(['update:modelValue'])

const updateValue = (val: any) => {
  emit('update:modelValue', { ...props.modelValue, data: val })
}
</script>

<template>
  <aside class="w-full lg:w-64">
    <h3 class="font-medium mb-3">Filters</h3>
    <SideEditor
      :blueprint="BlueprintFilter.blueprint"
      :modelValue="modelValue.data"
      @update:modelValue="updateValue"
    />
  </aside>
</template>

<style scoped>

::v-deep(.p-accordionheader){
    padding: 0.65rem;
}

::v-deep(.p-accordioncontent-content){
    padding: 0.5rem;
}
</style>
