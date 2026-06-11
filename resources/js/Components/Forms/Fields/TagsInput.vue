<script setup lang="ts">
import { ref, watch } from 'vue'
import InputChips from 'primevue/inputchips'

const props = defineProps<{
    modelValue?: string[]
    form?: any
    fieldName?: string
    options?: any
    fieldData?: {
        placeholder?: string
    }
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string[]): void
    (e: 'update:form', form: any, value: string[]): void
}>()

const getValue = (): string[] => {
    if (props.modelValue !== undefined) return props.modelValue
    if (props.form && props.fieldName) {
        const raw = props.form[props.fieldName]
        return Array.isArray(raw) ? raw : []
    }
    return []
}

const value = ref<string[]>(getValue())

watch(() => props.modelValue, (v) => {
    if (v !== undefined) value.value = v
})

watch(value, (newValue) => {
    if (props.modelValue !== undefined) {
        emits('update:modelValue', newValue)
        return
    }
    if (props.form && props.fieldName) {
        props.form[props.fieldName] = newValue
        props.form.errors[props.fieldName] = ''
        emits('update:form', props.form, newValue)
    }
})
</script>

<template>
    <div>
        <InputChips
            v-model="value"
            :placeholder="fieldData?.placeholder ?? 'Type and press Enter'"
            :allow-duplicate="false"
            class="w-full"
            pt:root:class="w-full flex flex-wrap gap-1.5 px-1 py-1 min-h-[38px] bg-transparent"
            pt:input:class="flex-1 min-w-[120px] outline-none text-sm bg-transparent"
            pt:chip:class="inline-flex items-center gap-1 bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5 text-xs font-medium"
            pt:chipicon:class="cursor-pointer hover:text-indigo-900 text-xs ml-0.5"
        />
    </div>
</template>
