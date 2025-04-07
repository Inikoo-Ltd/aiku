<script setup lang="ts">
import { onMounted } from 'vue'
import InputNumberCssProperty from '@/Components/Workshop/Properties/InputNumberCssProperty.vue'

// Default model jika tidak ada nilai yang diberikan
const defaultModel =  { value: null, unit: 'px' }

// Menggunakan defineModel untuk dua arah binding
const model = defineModel<typeof defaultModel>({
    default: () => ({ value: null, unit: 'px' })
})


onMounted(() => {
    if (!model.value) {
        model.value = { ...defaultModel }
    }
})
</script>

<template>
    <div>
        <InputNumberCssProperty v-model="model" />
    </div>
</template>

<style scoped></style>
