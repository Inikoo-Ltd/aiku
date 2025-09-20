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

// keep internal state
const localValue = ref(props.modelValue ? { ...props.modelValue } : createDefaultValue())

// update localValue if parent changes (but only if it's different)
watch(
  () => props.modelValue,
  (val) => {
    if (val && JSON.stringify(val) !== JSON.stringify(localValue.value)) {
      localValue.value = { ...val }
    }
  },
  { deep: true }
)

// emit when localValue changes
watch(
  localValue,
  (val) => {
    console.log(val)
    emits("update:modelValue", { ...val })
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
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="W" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="H" />
      </template>

      <!-- Sheet -->
      <template v-else-if="localValue.type === 'sheet'">
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="W" />
      </template>

      <!-- Cylinder -->
      <template v-else-if="localValue.type === 'cylinder'">
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="H" />
        <span class="text-gray-500">×</span>
        <NumberInput v-model="localValue.w" inputClass="w-20 text-center" placeholder="D" />
      </template>

      <!-- Sphere -->
      <template v-else-if="localValue.type === 'sphere'">
        <NumberInput v-model="localValue.h" inputClass="w-20 text-center" placeholder="D" />
      </template>

      <!-- String -->
      <template v-else-if="localValue.type === 'string'">
        <NumberInput v-model="localValue.l" inputClass="w-20 text-center" placeholder="L" />
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
