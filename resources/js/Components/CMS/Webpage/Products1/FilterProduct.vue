<script setup lang="ts">
import { defineProps, defineEmits, ref, onMounted, inject } from 'vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { blueprint } from './BlueprintFilter'
import { debounce } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

// Props
const props = defineProps<{
  modelValue: Record<string, any>
  productCategory: number
}>()

const layout = inject('layout', retinaLayoutStructure)

// Emits
const emit = defineEmits(['update:modelValue'])

// Debounced update function to avoid unnecessary emit bursts
const debouncedUpdate = debounce((val: Record<string, any>) => {
  // Only update the nested `data`, keep same reference to avoid rerender
  props.modelValue.data = val
  emit('update:modelValue', props.modelValue)
}, 400)

// Handler when SideEditor emits changes
const updateValue = (val: Record<string, any>) => {
  debouncedUpdate(val)
}

const blueprintCopy = ref([...blueprint(props.productCategory).blueprint])
// const isLoadingFetching = ref(false)
onMounted(() => {


    let hidden_list = []
    if (layout.iris.shop.number_current_brands < 1) {
        hidden_list.push('brands_filter')
    }
    if (layout.iris.shop.number_current_tags < 1) {
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
    />

    <!-- <div v-if="isLoadingFetching" class="flex flex-col gap-y-4">
        <div class="skeleton w-full h-28" />
        <div class="skeleton w-full h-28" />
    </div> -->
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
