<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PaddingMarginProperty from '@/Components/Workshop/Properties/PaddingMarginProperty.vue'
import { trans } from 'laravel-vue-i18n'
import { cloneDeep, defaultsDeep } from 'lodash-es'

// Props and emits
const props = defineProps<{
  modelValue?: {
    unit: string
    top: { value: number | null }
    left: { value: number | null }
    right: { value: number | null }
    bottom: { value: number | null }
  }
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: typeof props.modelValue): void
}>()

// Local default
const localModel = {
  unit: "px",
  top: { value: null },
  left: { value: null },
  right: { value: null },
  bottom: { value: null },
}

// Local reactive state
const localValue = ref(cloneDeep(localModel))

// Initialize once
onMounted(() => {
  localValue.value = defaultsDeep(cloneDeep(props.modelValue ?? {}), cloneDeep(localModel))
})


</script>

<template>
  <div class="pb-3">
    <PaddingMarginProperty :modelValue="localValue" :scope="trans('Shadow')" @update:model-value="(value) => {
      emit('update:modelValue', value)
    }" />
  </div>
</template>
