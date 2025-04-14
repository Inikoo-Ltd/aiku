<script setup lang="ts">
import { onMounted } from 'vue'
import BorderProperty from '@/Components/Workshop/Properties/BorderProperty.vue'
import { set } from 'lodash-es'


const model = defineModel<typeof localModel>({
    required: true,
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()


// Create a local copy of the model for internal use
const localModel = {
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

// const convertToObject = (value: any) => {
//     return value === null || value === undefined || Array.isArray(value) ? {} : value
// }


onMounted(async () => {
    if (model.value === null || model.value === undefined || Array.isArray(model.value)) {
        model.value = { ...localModel } // Initialize model.value as a copy of localModel
    }

    if (!model.value?.unit) {
        set(model.value, 'unit', localModel.unit)
    }
    if (!model.value?.top?.value) {
        set(model.value, 'top.value', localModel.top.value)
    }
    if (!model.value?.left?.value) {
        set(model.value, 'left.value', localModel.left.value)
    }
    if (!model.value?.right?.value) {
        set(model.value, 'right.value', localModel.right.value)
    }
    if (!model.value?.bottom?.value) {
        set(model.value, 'bottom.value', localModel.bottom.value)
    }
    if (!model.value?.color) {
        set(model.value, 'color', localModel.color)
    }
    if (!model.value?.rounded?.unit) {
        set(model.value, 'rounded.unit', localModel.rounded.unit)
    }
    if (!model.value?.rounded?.topleft?.value) {
        set(model.value, 'rounded.topleft.value', localModel.rounded.topleft.value)
    }
    if (!model.value?.rounded?.topright?.value) {
        set(model.value, 'rounded.topright.value', localModel.rounded.topright.value)
    }
    if (!model.value?.rounded?.bottomleft?.value) {
        set(model.value, 'rounded.bottomleft.value', localModel.rounded.bottomleft.value)
    }
    if (!model.value?.rounded?.bottomright?.value) {
        set(model.value, 'rounded.bottomright.value', localModel.rounded.bottomright.value)
    }
    
})

</script>

<template>
    <div >
        <BorderProperty
            :modelValue="model" @update:model-value="(value) => {
                emits('update:modelValue', value)
            }"
        />
    </div>
</template>

<style scoped></style>
