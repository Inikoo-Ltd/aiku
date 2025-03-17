<script setup lang="ts">
import { ref, watch, onMounted, onBeforeMount } from 'vue'
import BorderProperty from '@/Components/Workshop/Properties/BorderProperty.vue'
import { trans } from 'laravel-vue-i18n'
import { set, get } from 'lodash-es'


const model = defineModel<typeof localModel>({
    required: true,
})

// Create a local copy of the model for internal use
const localModel = {
    top: { value: 0 },
    left: { value: 0 },
    unit: 'px',
    color: '',
    right: { value: 0 },
    bottom: { value: 0 },
    rounded: {
        unit: 'px',
        topleft: { value: 0 },
        topright: { value: 0 },
        bottomleft: { value: 0 },
        bottomright: { value: 0 },
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
            v-model="model"
        />
    </div>
</template>

<style scoped></style>
