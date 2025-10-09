<script setup lang="ts">
import { ref } from "vue"
import ToggleSwitch from "primevue/toggleswitch"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import { get } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faCheck } from "@fas"

const props = defineProps<{
  form: Record<string, any>
  fieldName: string
  fieldData?: {
    options?: {
      type: { label: string; key_value: string }
      format: { label: string; key_value: string }
      sequence: { label: string; key_value: string }
    }[]
  }
}>()

</script>

<template>
  <div>
    <div v-for="(option, index) in fieldData?.options" :key="index" class="rounded-2xl border shadow-sm mb-4 bg-white"
      :class="form[fieldName][option.type.key_value] ? 'p-4' : 'p-4'">
      <!-- Header -->
      <div class="flex items-center justify-between" id="custom-switch"
        :class="form[fieldName][option.type.key_value] ? 'border-b pb-2 mb-3' : ''">
        <span class="font-medium text-gray-800">
          {{ option.type.label }}
        </span>
        <ToggleSwitch v-model="form[fieldName][option.type.key_value]" :true-value="true" :false-value="false"
          aria-label="Toggle Option">
          <template #handle="{ checked }">
            <FontAwesomeIcon :icon="checked ? faCheck : faTimes" :class="checked ? 'text-green-500' : 'text-red-500'" class="h-3 w-3"/>
          </template>
        </ToggleSwitch>
      </div>

      <!-- Body Grid, show only if toggle is true -->
      <div v-if="form[fieldName][option.type.key_value]" class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <!-- Format -->
        <div class="flex flex-col max-w-sm">
          <label class="block text-sm font-medium text-gray-600 mb-1" :for="`${fieldName}-format-${index}`">
            {{ option.format.label }}
          </label>
          <InputText v-model="form[fieldName][option.format.key_value]" :id="`${fieldName}-format-${index}`"
            input-class="w-full" placeholder="Enter format" />
        </div>

        <!-- Sequence -->
        <div class="flex flex-col max-w-sm">
          <label class="block text-sm font-medium text-gray-600 mb-1" :for="`${fieldName}-sequence-${index}`">
            {{ option.sequence.label }}
          </label>
          <InputNumber v-model="form[fieldName][option.sequence.key_value]" :id="`${fieldName}-sequence-${index}`"
            input-class="w-full" :min="0" placeholder="Enter sequence" showButtons />
        </div>
      </div>
    </div>

    <!-- Error -->
    <p v-if="get(form, ['errors', fieldName])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
      {{ form.errors[fieldName] }}
    </p>
  </div>
</template>

<style scoped>
:deep(#custom-switch) {
  /* Default (false) */
  --p-toggleswitch-background: #ef4444; /* red-500 */
  --p-toggleswitch-hover-background: #dc2626; /* red-600 */

  /* Checked (true) */
  --p-toggleswitch-checked-background: #22c55e; /* green-500 */
  --p-toggleswitch-checked-hover-background: #16a34a; /* green-600 */
}
</style>