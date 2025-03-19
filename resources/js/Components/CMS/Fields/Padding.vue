<script setup lang="ts">
import { onBeforeMount } from 'vue'
import PaddingMarginProperty from '@/Components/Workshop/Properties/PaddingMarginProperty.vue'
import { trans } from 'laravel-vue-i18n'
import { set, get } from 'lodash-es'
import { onMounted } from 'vue'
import { inject } from 'vue'


const model = defineModel<typeof localModel>()
const emit = defineEmits(['update:modelValue'])

const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') })  // Get the block id that use this property


// Create a local copy of the model for internal use
const localModel = {
    unit: "px",
    top: {
        value: null
    },
    left: {
        value: null
    },
    right: {
        value: null
    },
    bottom: {
        value: null
    }
}

onMounted(() => {
    if (!model.value?.unit && model.value?.unit !== localModel.unit ) {
        set(model, 'value.unit', localModel.unit)
    }
    if (!model.value?.top?.value && model.value?.top?.value !== localModel.top?.value ) {
        set(model, 'value.top.value', localModel.top.value)
    }
    if (!model.value?.left?.value && model.value?.left?.value !== localModel.left?.value ) {
        set(model, 'value.left.value', localModel.left.value)
    }
    if (!model.value?.right?.value && model.value?.right?.value !== localModel.right?.value ) {
        set(model, 'value.right.value', localModel.right.value)
    }
    if (!model.value?.bottom?.value && model.value?.bottom?.value !== localModel.bottom?.value ) {
        set(model, 'value.bottom.value', localModel.bottom.value)
    }

})

</script>

<template>
    <div class="pb-3">
       <!--  <div class="w-full text-center py-1 font-semibold select-none">{{ trans('Padding') }}</div> -->
        <PaddingMarginProperty :modelValue="model || localModel" :scope="trans('Padding')" />
    </div>
</template>

<style scoped></style>
