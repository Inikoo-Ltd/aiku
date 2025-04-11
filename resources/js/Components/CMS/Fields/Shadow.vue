<script setup lang="ts">
import { onMounted, inject } from 'vue'
import PaddingMarginProperty from '@/Components/Workshop/Properties/PaddingMarginProperty.vue'
import { trans } from 'laravel-vue-i18n'
import { cloneDeep, defaultsDeep } from 'lodash-es'

// Define model structure
const model = defineModel<{
  unit: string
  top: { value: number | null }
  left: { value: number | null }
  right: { value: number | null }
  bottom: { value: number | null }
}>({ required: true })

const emit = defineEmits(['update:modelValue'])


// Default values
const localModel = {
  unit: "px",
  top: { value: null },
  left: { value: null },
  right: { value: null },
  bottom: { value: null }
}

onMounted(() => {
  if (!model.value || Object.keys(model.value).length === 0) {
    model.value = cloneDeep(localModel)
  } else {
    model.value = defaultsDeep(cloneDeep(model.value), cloneDeep(localModel))
  }
})
</script>


<template>
  <div class="pb-3">
    <PaddingMarginProperty :modelValue="model" :scope="trans('Shadow')" @update:modelValue="(e)=>emit('update:modelValue',e)" />
  </div>
</template>
