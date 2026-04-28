<script setup lang="ts">
import { Component, onMounted, watch } from "vue"
import { cloneDeep, isPlainObject, get, set } from 'lodash-es'
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"
import Toggle from '@/Components/Pure/Toggle.vue'
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        fields: object
        initial_value: object | object[]
    }
}>()

const normalizeFieldValue = () => {
    const current = props.form?.[props.fieldName]

    // already initialised → do nothing
    if (current !== undefined && current !== null) return

    const initial = props.fieldData?.initial_value
    if (!initial) return

    props.form[props.fieldName] = cloneDeep(initial)
}

const componentsList = {
    number: PureInputNumber,
    toggle: Toggle,
} satisfies Record<string, Component>

type ComponentKey = keyof typeof componentsList

const getComponent = (componentName: ComponentKey) => {
    return componentsList[componentName]
}

onMounted(normalizeFieldValue)

</script>

<template>
    <div class="flex flex-wrap items-start gap-6 mb-5">
    <div
        v-for="(field, findex) in fieldData.fields"
        :key="findex"
        class="flex flex-col"
        :style="field.column
            ? { width: `calc(${field.column} - 12px)` }
            : undefined"
    >
        <label v-if="field.label" class="mb-1 text-sm font-medium text-gray-700">
            {{ field.label }}
        </label>

        <component
            :is="getComponent(field.type)"
            :model-value="get(form[fieldName], field.key)"
            @update:model-value="val => set(form[fieldName], field.key, val)"
            v-bind="field"
            class="p-0"
        />
    </div>
</div>

    <p v-if="get(form, ['errors', fieldName])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>

<style scoped>
:deep(input[type=number]) {
    padding-top: 0.5rem !important;
    padding-right: 0.5rem !important;
    padding-bottom: 0.5rem !important;
    padding-left: 0.5rem !important;
}
</style>
