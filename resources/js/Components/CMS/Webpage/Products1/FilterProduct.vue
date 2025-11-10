<script setup lang="ts">
import { defineProps, defineEmits, ref, onMounted, inject } from 'vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { blueprint } from './BlueprintFilter'
import { debounce } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

const props = defineProps<{
  modelValue: Record<string, any>
  productCategory: number
}>()

const layout = inject('layout', retinaLayoutStructure)

const emit = defineEmits(['update:modelValue'])

const debouncedUpdate = debounce((val: Record<string, any>) => {
  props.modelValue.data = val
  emit('update:modelValue', props.modelValue)
}, 400)

const updateValue = (val: Record<string, any>) => {
  debouncedUpdate(val)
}

const blueprintCopy = ref([...blueprint(props.productCategory).blueprint])

onMounted(() => {
    let hidden_list = []
    if (layout.iris?.shop?.number_current_brands < 1) {
        hidden_list.push('brands_filter')
    }
    if (layout.iris?.shop?.number_current_tags < 1) {
        hidden_list.push('tags_filter')
    }
    blueprintCopy.value.map((item) => {
        if (hidden_list.includes(item.id as string)) {
            item.type = 'hidden'
        }
    })
})

</script>

<template>
  <aside class="w-full lg:w-64">
    <h3 class="font-medium mb-3">{{ trans("Filters") }}</h3>
    <SideEditor
        :blueprint="blueprintCopy"
        :modelValue="modelValue.data"
        @update:modelValue="updateValue"
        modelType="filter"
    />
  </aside>
</template>

<style scoped>
::v-deep(.p-accordionheader) {
  padding: 0.65rem;
}

::v-deep(.p-accordioncontent-content) {
  padding: 0.5rem;
}

::v-deep(.multiselect-options) {
  margin-left: 0rem !important;
}
</style>
