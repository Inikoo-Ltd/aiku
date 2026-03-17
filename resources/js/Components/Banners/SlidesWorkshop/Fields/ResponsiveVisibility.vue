<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faEyeSlash, faEye } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faEyeSlash, faEye)

const props = defineProps({
  modelValue: Object,
  fieldData: Object
})

const emit = defineEmits(["update:modelValue"])

const defaultState = {
  desktop: true,
  tablet: true,
  mobile: true
}

const value = computed({
  get() {
    return {
      ...defaultState,
      ...(props.modelValue || {})
    }
  },
  set(v) {
    emit("update:modelValue", v)
  }
})

const toggle = (view: string) => {
  const current = {
    ...defaultState,
    ...(props.modelValue || {})
  }

  emit("update:modelValue", {
    ...current,
    [view]: !current[view]
  })
}
</script>

<template>
  <div class="flex gap-2">
    <div
      v-for="view in (fieldData.useIn || ['desktop','tablet','mobile'])"
      :key="view"
      class="px-3 py-1 rounded cursor-pointer text-xs border flex items-center gap-1"
      :class="value?.[view]
        ? 'bg-green-500 text-white border-green-500'
        : 'bg-gray-200 text-gray-500 border-gray-300'"
      @click="toggle(view)"
    >
      {{ view }}
      <FontAwesomeIcon
        :icon="value?.[view] ? 'far fa-eye' : 'far fa-eye-slash'"
      />
    </div>
  </div>
</template>