<script setup lang="ts">
import { onMounted, computed } from 'vue'
import DimensionProperty from '@/Components/Workshop/Properties/DimensionProperty.vue'

// Default model jika tidak ada nilai yang diberikan
const defaultModel = {
    height: { value: null, unit: 'px' },
    width: { value: null, unit: '%' },
}

// Menggunakan defineModel untuk dua arah binding
const model = defineModel<typeof defaultModel>({
    default: () => ({ height: { value: null, unit: 'px' }, width: { value: null, unit: '%' } })
})

// Pastikan model tidak null atau undefined
const normalizedModel = computed(() => model.value ?? defaultModel)

onMounted(() => {
    if (!model.value) {
        model.value = { ...defaultModel }
    } else {
        if (!model.value.height) {
            model.value = { ...model.value, height: { value: null, unit: 'px' } }
        }
        if (!model.value.width) {
            model.value = { ...model.value, width: { value: null, unit: '%' } }
        }
    }
})
</script>

<template>
    <div>
        <DimensionProperty v-model="model" />
    </div>
</template>

<style scoped></style>
