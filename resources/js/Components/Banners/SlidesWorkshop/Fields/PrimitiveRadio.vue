<script setup lang="ts">
import { computed } from "vue"
import RadioButton from "primevue/radiobutton"

const props = defineProps<{
  modelValue?: any
  fieldData?: any
}>()

const emit = defineEmits(["update:modelValue"])

const value = computed({
  get: () => props.modelValue,
  set: v => {
    emit("update:modelValue", v)
  }
})

const groupName = computed(() => props.fieldData?.name || "radio-group")
</script>


<template>

  <div class="flex flex-wrap gap-4">
    <div
      v-for="(option, index) in fieldData?.options"
      :key="index"
      class="flex items-center gap-2"
    >

      <RadioButton
        v-model="value"
        :value="option.value"
        :name="groupName"
        :inputId="groupName + index"
      />

      <label :for="groupName + '_' + index" class="cursor-pointer text-sm">
        {{ option.label }}
      </label>
    </div>
  </div>
</template>
