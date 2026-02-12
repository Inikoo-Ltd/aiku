<script setup lang="ts">
import Multiselect from "@vueform/multiselect"
import { ref, watch } from "vue"
import {useFontFamilyList} from "@/Composables/useFont"

const props = defineProps<{
  modelValue: any
  fieldData: {
    placeholder?: string
    searchable?: boolean
    options?: string[]
    mode?: string
    required?: boolean
  }
}>()

const emit = defineEmits(['update:modelValue'])


function combineUniqueArrays(arr1, arr2) {
  const combined = [...arr1]

  arr2.forEach(item => {
    if (!combined.some(existingItem => existingItem.slug === item.slug)) {  // Only push if font didn't exist yet
      combined.push(item)
    }
  })

  return combined;
}
const combinedFonts = combineUniqueArrays(props.fieldData?.options || [], useFontFamilyList);

const value = ref(props.modelValue)

watch(() => props.modelValue, (v) => {
  if (v !== value.value) value.value = v
})

watch(value, (v) => {
  emit('update:modelValue', v)
})
</script>

<template>
<div>
  <div class="relative">
    <Multiselect
      v-model="value"
      :options="combinedFonts"
      :placeholder="props.fieldData?.placeholder ?? 'Select option'"
      :canClear="false"
      :closeOnSelect="props.fieldData?.mode === 'multiple' ? false : true"
      :canDeselect="!props.fieldData?.required"
      :hideSelected="false"
      :searchable="!!props.fieldData?.searchable"
      valueProp="value"
      labelProp="label"
    >
      <template #singlelabel="{ value }">
        <div class="multiselect-single-label text-gray-600">
          <span :style="`font-family:${value.value}`">
            {{ value.label }}
          </span>
        </div>
      </template>

      <template #option="{ option }">
        <span :style="`font-family:${option.value}`">
          {{ option.label }}
        </span>
      </template>
    </Multiselect>
  </div>
</div>
</template>


