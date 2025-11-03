<script setup lang="ts">
import SelectButton from 'primevue/selectbutton'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faEye, faEyeSlash } from '@fal'

const model = defineModel<string>({ default: 'visible' })

const options = [
  { icon: faEye, value: 'visible', label: 'Show' },
  { icon: faEyeSlash, value: 'hidden', label: 'Hide' }
]
</script>

<template>
  <div class="space-y-3">
    <SelectButton
      v-model="model"
      :options="options"
      optionLabel="label"
      optionValue="value"
      dataKey="value"
      class="w-full flex border rounded-lg overflow-hidden"
      :pt="{
        root: { class: 'flex w-full' },
        button: ({ context }) => ({
          class: [
            'flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-medium transition-all duration-200',
            context.active
              ? 'bg-blue-600 text-white shadow-sm'
              : 'bg-white text-gray-700 hover:bg-gray-50'
          ]
        })
      }"
    >
      <template #option="slotProps">
        <div class="flex items-center gap-2">
          <FontAwesomeIcon :icon="slotProps.option.icon" class="text-base" />
          <span>{{ slotProps.option.label }}</span>
        </div>
      </template>
    </SelectButton>
  </div>
</template>

<style scoped>
:deep(.p-selectbutton) {
  border: none;
}
</style>
