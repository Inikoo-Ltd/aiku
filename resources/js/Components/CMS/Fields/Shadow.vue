<script setup lang="ts">
import { onMounted, inject } from 'vue'
import PaddingMarginProperty from '@/Components/Workshop/Properties/PaddingMarginProperty.vue'
import { trans } from 'laravel-vue-i18n'

// Define model structure
const model = defineModel<{
  unit: string
  top: { value: number | null }
  left: { value: number | null }
  right: { value: number | null }
  bottom: { value: number | null }
}>({ required: true })

const emit = defineEmits(['update:modelValue'])

// Injects
const onSaveWorkshopFromId = inject('onSaveWorkshopFromId', (e?: number) => {
  console.log('onSaveWorkshopFromId not provided')
})
const side_editor_block_id = inject('side_editor_block_id', () => {
  console.log('side_editor_block_id not provided')
})

// Default value initializer
onMounted(() => {
  if (!model.value) {
    model.value = {
      unit: 'px',
      top: { value: null },
      left: { value: null },
      right: { value: null },
      bottom: { value: null }
    }
  } else {
    if (!model.value.unit) model.value.unit = 'px'
    if (!model.value.top) model.value.top = { value: null }
    if (!model.value.left) model.value.left = { value: null }
    if (!model.value.right) model.value.right = { value: null }
    if (!model.value.bottom) model.value.bottom = { value: null }
  }
})
</script>


<template>
  <div class="pb-3">
    <PaddingMarginProperty :modelValue="model || localModel" :scope="trans('Shadow')" />
  </div>
</template>
