<script setup lang="ts">
import { ref, watch } from "vue"
import NumberInput from "primevue/inputnumber"
import Dropdown from "primevue/dropdown"

const props = defineProps<{
  modelValue: {
    h: number | null
    l: number | null
    w: number | null
    type: string
    units: string
  } | null
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: any): void
}>()

const createDefaultValue = () => ({
  h: 0,
  l: 0,
  w: 0,
  type: "rectangular",
  units: "mm"
})

// Get factor based on unit
const getFactor = (unit: string) => {
  if (unit === 'cm') return 100
  if (unit === 'mm') return 1000
  if (unit === 'inch') return 39.3701 // 1 meter = 39.3701 inch
  return 1 // default: meter
}

// Convert value to display
const toDisplay = (val: any) => {
  if (!val) return createDefaultValue()
  const factor = getFactor(val.units)
  return {
    ...val,
    h: val.h != null ? Number((val.h * factor).toFixed(2)) : null,
    l: val.l != null ? Number((val.l * factor).toFixed(2)) : null,
    w: val.w != null ? Number((val.w * factor).toFixed(2)) : null,
  }
}

// Convert UI value to database (Meter)
const toDatabase = (val: any) => {
  if (!val) return null
  const factor = getFactor(val.units)
  return {
    ...val,
    h: val.h != null ? Number((val.h / factor).toFixed(4)) : null,
    l: val.l != null ? Number((val.l / factor).toFixed(4)) : null,
    w: val.w != null ? Number((val.w / factor).toFixed(4)) : null,
  }
}

// Keep internal state - initially convert from modelValue to display format

const localValue = ref(toDisplay(props.modelValue))

// Update localValue if parent changes (e.g. when loading existing data), but only if the new value is actually different from the current localValue to avoid overwriting user input
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      const convertedLocal = toDatabase(localValue.value)
      
      // Check if the new value from parent is different from the current localValue (after converting localValue back to database format for accurate comparison)
      if (
        newVal.h !== convertedLocal?.h ||
        newVal.l !== convertedLocal?.l ||
        newVal.w !== convertedLocal?.w ||
        newVal.type !== convertedLocal?.type ||
        newVal.units !== convertedLocal?.units
      ) {
        localValue.value = toDisplay(newVal)
      }
    }
  },
  { deep: true }
)

// Emit when localValue changes
watch(
  localValue,
  (val) => {
    // Convert localValue back to database format before emitting
    emits("update:modelValue", toDatabase(val))
  },
  { deep: true }
)

const typeOptions = [
  { label: "Rectangular (L×W×H)", value: "rectangular" },
  { label: "Sheet (L×W)", value: "sheet" },
  { label: "Cylinder (H×D)", value: "cylinder" },
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
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" :minFractionDigits="0" :maxFractionDigits="3" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="W" :minFractionDigits="0" :maxFractionDigits="3" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="H" :minFractionDigits="0" :maxFractionDigits="3" />
      </template>

      <!-- Sheet -->
      <template v-else-if="localValue.type === 'sheet'">
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" :minFractionDigits="0" :maxFractionDigits="3" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="W" :minFractionDigits="0" :maxFractionDigits="3" />
      </template>

      <!-- Cylinder -->
      <template v-else-if="localValue.type === 'cylinder'">
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="H" :minFractionDigits="0" :maxFractionDigits="3" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="D" :minFractionDigits="0" :maxFractionDigits="3" />
      </template>

      <!-- Sphere -->
      <template v-else-if="localValue.type === 'sphere'">
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="D" :minFractionDigits="0" :maxFractionDigits="3" />
      </template>

      <!-- String -->
      <template v-else-if="localValue.type === 'string'">
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" :minFractionDigits="0" :maxFractionDigits="3" />
      </template>

      <!-- Units -->
      <Dropdown
        v-model="localValue.units"
        :options="unitOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="Units"
      />
    </div>
  </div>
</template>
