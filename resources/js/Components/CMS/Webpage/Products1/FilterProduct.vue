<script setup lang="ts">
import { ref, onMounted, inject, computed } from 'vue'
import { debounce, get, set, cloneDeep } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { getFilterComponent } from '@/Composables/SideEditorHelperFilter'
import { blueprint } from './BlueprintFilter'

const props = defineProps<{
  modelValue: Record<string, any>
  productCategory: number
}>()

const emit = defineEmits(['update:modelValue'])
const layout = inject('layout', retinaLayoutStructure)

// ✅ Ensure modelValue.data exists safely
const modelData = computed(() => props.modelValue?.data ?? {})

// ✅ Debounced model update (wraps inside root modelValue)
const debouncedUpdate = debounce((val: Record<string, any>) => {
  const updated = cloneDeep(props.modelValue)
  updated.data = val
  emit('update:modelValue', updated)
}, 400)

// ✅ Handler for property change — modifies `data` only
const onPropertyUpdate = (newVal: any, path: string[]) => {
  const updatedData = cloneDeep(modelData.value)
  set(updatedData, path, newVal)
  debouncedUpdate(updatedData)
}

// ✅ Blueprint setup and visibility logic
const blueprintCopy = ref([...blueprint(props.productCategory).blueprint])

onMounted(() => {
  const hidden_list: string[] = []

  if (layout?.iris?.shop?.number_current_brands < 1) hidden_list.push('brands_filter')
  if (layout?.iris?.shop?.number_current_tags < 1) hidden_list.push('tags_filter')

  blueprintCopy.value.forEach((item) => {
    if (hidden_list.includes(item.id as string)) {
      item.type = 'hidden'
    }
  })
})
</script>

<template>
  <aside class="w-full lg:w-64">
    <h3 class="font-medium mb-3">{{ trans("Filters") }}</h3>

    <div v-for="item in blueprintCopy" :key="item.id" class="my-4">
      <div v-if="item?.type !== 'hidden'">
        <div class="flex items-center font-semibold text-start my-2 border-b">
          {{ trans(item.label) }}
        </div>

        <component
          :is="getFilterComponent(item.type)"
          v-bind="item.props_data"
          :modelValue="get(modelData, item.key)"
          @update:modelValue="(val) => onPropertyUpdate(val, item.key)"
        />
      </div>
    </div>
  </aside>
</template>

<style scoped>
:deep(.p-accordionheader) {
  padding: 0.65rem;
}

:deep(.p-accordioncontent-content) {
  padding: 0.5rem;
}

:deep(.multiselect-options) {
  margin-left: 0rem !important;
}
</style>
