<script setup lang="ts">
import { inject, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import ColorPicker from '@/Components/Utils/ColorPicker.vue'

// Injected props
/* const onSaveWorkshopFromId: Function = inject(
  'onSaveWorkshopFromId',
  (e?: number) => console.warn('onSaveWorkshopFromId not provided')
)

const side_editor_block_id = inject('side_editor_block_id', () => {
  console.warn('side_editor_block_id not provided')
})
 */

 const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()
// Model is a string, e.g., 'rgba(255,255,255,1)'
const model = defineModel<string>({ required: true })

// For local UI binding
const localColor = ref(model.value || 'rgba(0,0,0,1)')

// Handle color change
const handleColorChange = (newColor: any) => {
  const rgba = `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a})`
  localColor.value = rgba
  model.value = rgba
  /* onSaveWorkshopFromId(side_editor_block_id, 'ColorProperty') */
  emits('update:modelValue', rgba)
}
</script>

<template>
  <div class="flex flex-col pt-1 pb-3">
    <div class="px-3 flex justify-between items-center mb-2">
      <div class="text-xs">{{ trans('Color') }}</div>
      <ColorPicker
        :color="localColor"
        @changeColor="handleColorChange"
        closeButton
      >
        <template #button>
          <div
            v-bind="$attrs"
            class="overflow-hidden h-7 w-7 rounded-md border border-gray-300 shadow cursor-pointer flex justify-center items-center"
            :style="{ background: localColor }"
          />
        </template>
      </ColorPicker>
    </div>
  </div>
</template>
