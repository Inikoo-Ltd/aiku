<script setup lang="ts">
import { ref, watch, computed } from "vue"
import { get, isEqual } from "lodash-es"

const props = defineProps<{
  modelValue?: any
  fieldData?: any
  radioValue?: any
}>()

const emit = defineEmits(["update:modelValue", "onChange"])

// resolve default
const initial = computed(() => {
  if (props.modelValue !== undefined) return props.modelValue
  if (props.radioValue !== undefined) return props.radioValue
  return get(props, ["fieldData", "defaultValue"], null)
})

const value = ref(initial.value)

// sync from parent
watch(
  () => props.modelValue,
  v => {
    if (!isEqual(v, value.value)) {
      value.value = v
    }
  }
)

// emit to parent
watch(value, newValue => {
  emit("update:modelValue", newValue)
  emit("onChange", newValue)
})

</script>

<template>
  <div>
    <fieldset class="select-none">
      <legend class="sr-only"></legend>

      <div class="flex items-center gap-x-5 gap-y-1 flex-wrap">
        <label
          v-for="(option, index) in fieldData?.options"
          :key="option.label + index"
          :for="option.label + index"
          class="inline-flex items-center gap-x-1.5 cursor-pointer py-1"
        >
          <input
            v-model="value"
            type="radio"
            :id="option.label + index"
            :value="option.value"
            :checked="isEqual(value, option.value)"
            class="h-4 w-4 border-gray-300 text-gray-600 focus:ring-0 focus:outline-none cursor-pointer"
          />

          <span v-if="option.label" class="font-light text-sm text-gray-400">
            {{ option.label }}
          </span>
        </label>
      </div>
    </fieldset>
  </div>
</template>
