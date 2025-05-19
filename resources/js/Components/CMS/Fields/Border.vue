<script setup lang="ts">
import { ref, onMounted } from 'vue'
import BorderProperty from '@/Components/Workshop/Properties/BorderProperty.vue'
import { cloneDeep, defaultsDeep } from 'lodash-es'

const props = defineProps<{
  modelValue?: any
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: any): void
}>()

const localModelTemplate = {
  top: { value: null },
  left: { value: null },
  unit: 'px',
  color: null,
  right: { value: null },
  bottom: { value: null },
  rounded: {
    unit: 'px',
    topleft: { value: null },
    topright: { value: null },
    bottomleft: { value: null },
    bottomright: { value: null },
  },
}

// local state used for editing
const localValue = ref<any>(cloneDeep(localModelTemplate))

// initialize once on mount
onMounted(() => {
  const base = props.modelValue ?? {}
  localValue.value = defaultsDeep(cloneDeep(base), cloneDeep(localModelTemplate))
})



</script>


<template>
    <div >
        <BorderProperty
            :modelValue="localValue" @update:model-value="(value) => {
                emits('update:modelValue', value)
            }"
        />
    </div>
</template>

<style scoped></style>
