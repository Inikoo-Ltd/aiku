<script setup lang="ts">
import { computed } from 'vue'
import BackgroundProperty from '@/Components/Workshop/Properties/BackgroundProperty.vue'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'

const props = defineProps<{
    uploadRoutes?: routeType
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

const defaultModel = {
    type: 'color',
    color: 'rgba(255, 255, 255, 1)',
    image: { original: null }
}

const model = defineModel<typeof defaultModel>({ required: true })

const localModel = computed({
    get: () => model.value ?? defaultModel,
    set: (newValue) => {
        model.value = newValue
    }
})
</script>

<template>
    <div>
        <BackgroundProperty :modelValue="localModel" :uploadImageRoute="uploadRoutes" @update:modelValue="(val)=>emits('update:modelValue',val)" />
    </div>
</template>

<style scoped></style>
