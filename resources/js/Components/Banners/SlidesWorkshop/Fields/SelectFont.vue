<script setup lang="ts">
import Multiselect from "@vueform/multiselect"
import { lowerCase, snakeCase } from "lodash-es"
import { ref, watch, computed } from "vue"
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

const options = useFontFamilyList

const compOptions = computed(() => {
  return props.fieldData?.options
    ? options.filter((opt:any) => props.fieldData.options?.includes(opt))
    : options
})

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
      :options="compOptions"
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
          <span :style="`font-family:${snakeCase(lowerCase(value.value))}`">
            {{ value.value }}
          </span>
        </div>
      </template>

      <template #option="{ option }">
        <span :style="`font-family:${snakeCase(lowerCase(option.value))}`">
          {{ option.label }}
        </span>
      </template>
    </Multiselect>
  </div>
</div>
</template>


