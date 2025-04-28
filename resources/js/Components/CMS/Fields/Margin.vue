<script setup lang="ts">
import { onMounted } from 'vue'
import PaddingMarginProperty from '@/Components/Workshop/Properties/PaddingMarginProperty.vue'
import { trans } from 'laravel-vue-i18n'
import { cloneDeep, isPlainObject } from 'lodash-es'

const emit = defineEmits<{
  (e: 'update:modelValue', value: PaddingMarginModel): void
}>()

interface SideValue {
  value: number | null
}

interface PaddingMarginModel {
  unit?: string
  top?: SideValue
  left?: SideValue
  right?: SideValue
  bottom?: SideValue
}

const model = defineModel<PaddingMarginModel>()

const defaultModel: Required<PaddingMarginModel> = {
  unit: 'px',
  top: { value: null },
  left: { value: null },
  right: { value: null },
  bottom: { value: null },
}

onMounted(() => {
  if (!isPlainObject(model.value)) return

  for (const key in defaultModel) {
    const k = key as keyof PaddingMarginModel
    if (!(k in model.value!)) {
      model.value![k] = cloneDeep(defaultModel[k])
    }
  }
})
</script>

<template>
  <div class="pb-3">
    <PaddingMarginProperty
      :modelValue="model || defaultModel"
      :scope="trans('margin')"
      @update:modelValue="val => emit('update:modelValue', val)"
    />
  </div>
</template>
