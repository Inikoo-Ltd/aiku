<script setup lang="ts">
import { computed } from "vue"
import NumberInput from "primevue/inputnumber"
import Dropdown from "primevue/dropdown"

const props = defineProps<{
  modelValue: {
    h: number
    l: number
    w: number
    type: string
    units: string
  }
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: any): void
}>()

// Use computed with getter/setter instead of watch
const localValue = computed({
  get: () => props.modelValue,
  set: (val) => emits("update:modelValue", val)
})

// Options
const typeOptions = [
  { label: "Rectangular (L×W×H)", value: "rectangular" },
  { label: "Sheet (L×W)", value: "sheet" },
  { label: "Cylinder (H×D)", value: "cilinder" }, // sesuai backend
  { label: "Sphere (D)", value: "sphere" },
  { label: "String (L)", value: "string" }
]

const unitOptions = [
  { label: "Millimeter (mm)", value: "mm" },
  { label: "Centimeter (cm)", value: "cm" },
  { label: "Meter (m)", value: "m" },
  { label: "Inch (inch)", value: "inch" }
]
</script>

<template>
  <div class="space-y-3">
    <Dropdown
      v-model="localValue.type"
      :options="typeOptions"
      optionLabel="label"
      optionValue="value"
      placeholder="Select Type"
    />

    <div class="flex flex-wrap items-center gap-3">
      <!-- Rectangular -->
      <template v-if="localValue.type === 'rectangular'">
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" :suffix="localValue.units" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="W" :suffix="localValue.units" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="H" :suffix="localValue.units" />
      </template>

      <!-- Sheet -->
      <template v-else-if="localValue.type === 'sheet'">
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" :suffix="localValue.units" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="W" :suffix="localValue.units" />
      </template>

      <!-- Cylinder -->
      <template v-else-if="localValue.type === 'cilinder'">
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="H" :suffix="localValue.units" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="D" :suffix="localValue.units" />
      </template>

      <!-- Sphere -->
      <template v-else-if="localValue.type === 'sphere'">
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="D" :suffix="localValue.units" />
      </template>

      <!-- String -->
      <template v-else-if="localValue.type === 'string'">
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" :suffix="localValue.units" />
      </template>

      <!-- Units selector -->
      <Dropdown
        v-model="localValue.units"
        :options="unitOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="Units"
        class="w-40"
      />
    </div>
  </div>
</template>
