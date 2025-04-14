<script setup lang="ts">
import { onMounted, inject } from 'vue'
import PaddingMarginProperty from '@/Components/Workshop/Properties/PaddingMarginProperty.vue'
import { trans } from 'laravel-vue-i18n'
import { cloneDeep, defaultsDeep } from 'lodash-es'
import { isPlainObject } from 'lodash-es'

const emit = defineEmits(['update:modelValue'])

const model = defineModel<{
  unit?: string
  top?: { value: number | null }
  left?: { value: number | null }
  right?: { value: number | null }
  bottom?: { value: number | null }
}>()


// Default values
const localModel = {
  unit: "px",
  top: { value: null },
  left: { value: null },
  right: { value: null },
  bottom: { value: null }
}

onMounted(() => {
  if (!isPlainObject(model.value)) return

  for (const key in localModel) {
    if (!(key in model.value)) {
      // @ts-ignore
      model.value[key] = cloneDeep(localModel[key])
    }
  }
})
</script>


<template>
  <PaddingMarginProperty :modelValue="model" :scope="trans('Margin')"
    @update:modelValue="val => emit('update:modelValue', val)" />
</template>

<style scoped></style>
