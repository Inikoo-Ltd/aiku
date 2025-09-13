<script setup lang="ts">
import { ref, watch } from "vue"
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

const localValue = ref({ ...props.modelValue })

// sync with parent
watch(
  () => props.modelValue,
  (newVal) => {
    localValue.value = { ...newVal }
  }
)

watch(
  localValue,
  (newVal) => {
    emits("update:modelValue", newVal)
  },
  { deep: true }
)

// Options
const typeOptions = [
  { label: "Sphere", value: "sphere" },
  { label: "Cube", value: "cube" },
  { label: "Cylinder", value: "cylinder" },
  { label: "Cone", value: "cone" }
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
    <!-- Type dropdown on top -->
    <Dropdown
      v-model="localValue.type"
      :options="typeOptions"
      optionLabel="label"
      optionValue="value"
      placeholder="Select Type"
      class="w-40"
    />

    <!-- Dimensions + Units in one row -->
    <div class="flex flex-wrap items-center gap-3">
      <NumberInput
        v-model="localValue.h"
        inputClass="w-20 text-center"
        placeholder="h"
        :suffix="localValue.units"
      />
      <span class="text-gray-500">×</span>
      <NumberInput
        v-model="localValue.l"
        inputClass="w-20 text-center"
        placeholder="l"
        :suffix="localValue.units"
      />
      <span class="text-gray-500">×</span>
      <NumberInput
        v-model="localValue.w"
        inputClass="w-20 text-center"
        placeholder="w"
        :suffix="localValue.units"
      />

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
