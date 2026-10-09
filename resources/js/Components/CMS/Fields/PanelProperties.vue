<script setup lang="ts">
import PaddingMarginProperty from '@/Components/Workshop/Properties/PaddingMarginProperty.vue'
import BackgroundProperty from '@/Components/Workshop/Properties/BackgroundProperty.vue'
import BorderProperty from '@/Components/Workshop/Properties/BorderProperty.vue'
import TextProperty from '@/Components/Workshop/Properties/TextProperty.vue'
import DimensionProperty from '@/Components/Workshop/Properties/DimensionProperty.vue'
import { ctrans } from '@/Composables/useTrans'

const model = defineModel()

// const compModel = computed(() => {
//     // To check does the data if changed
//     return JSON.stringify(model.value)
// })

// const emit = defineEmits();
// watch(compModel, () => {
//     console.log('on change compModel')
//     emit('update:modelValue', model.value)
// })



</script>

<template>
    <div>
        <!-- <pre>{{ model }}</pre> -->
    </div>
    <!-- <div v-if="model?.buttons" class="border-t border-gray-300 bg-gray-100 pb-3">
        <div class="w-full text-center py-1 font-semibold select-none">{{ ctrans('Buttons') }}</div>
        <ButtonsProperty v-model="model.buttons" />
    </div>-->

    <div v-if="model?.dimension" class="border-t border-gray-300 bg-gray-100 pb-3">
        <div class="w-full text-center py-1 font-semibold select-none">{{ ctrans('Dimension') }}</div>
        <DimensionProperty v-model="model.dimension" />
    </div>

    <div v-if="model?.background" class="border-t border-gray-300 bg-gray-100 pb-3">
        <div class="w-full text-center py-1 font-semibold select-none">{{ ctrans('Background') }}</div>
        <BackgroundProperty v-model="model.background" />
    </div>

    <div v-if="model?.text" class="border-t border-gray-300 bg-gray-100 pb-3">
        <div class="w-full text-center py-1 font-semibold select-none">{{ ctrans('Text') }}</div>

        <TextProperty v-model="model.text" />
    </div>

    <div v-if="model?.border" class="border-t border-gray-300 bg-gray-100">
        <div class="w-full text-center py-1 font-semibold select-none">{{ ctrans('Border') }}</div>

        <BorderProperty v-model="model.border" />
    </div>

    <div v-if="model?.padding" class="border-t border-gray-300 bg-gray-100">
        <div class="w-full text-center py-1 font-semibold select-none">{{ ctrans('Padding') }}</div>

        <PaddingMarginProperty v-model="model.padding" />
    </div>

    <div v-if="model?.margin" class="border-t border-gray-300 bg-gray-100">
        <div class="w-full text-center py-1 font-semibold select-none">{{ ctrans('Margin') }}</div>

        <PaddingMarginProperty v-model="model.margin" />
    </div>

</template>

<style scoped></style>
