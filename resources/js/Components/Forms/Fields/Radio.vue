<script setup lang="ts">
import { RadioGroup, RadioGroupLabel, RadioGroupOption, RadioGroupDescription } from '@headlessui/vue'
import { get } from 'lodash-es'
import { computed } from 'vue'

const props = defineProps({
    form: Object,
    fieldName: String,
    fieldData: Object
})

// Compare objects for 'card' mode
const compareObjects = (objA: any, objB: any) => {
    const keysA = Object.keys(objA)
    const keysB = Object.keys(objB)
    if (keysA.length !== keysB.length) return false
    for (let key of keysA) {
        if (objA[key] !== objB[key]) return false
    }
    return true
}

// Reactive grid class based on columns
const gridClass = computed(() => {
    const cols = props.fieldData?.columns ?? 3
    switch (cols) {
        case 1: return 'grid-cols-1'
        case 2: return 'grid-cols-2'
        case 3: return 'grid-cols-3'
        case 4: return 'grid-cols-4'
        case 5: return 'grid-cols-5'
        default: return 'grid-cols-3'
    }
})
</script>

<template>
<div class="w-full">
    <fieldset class="select-none">
        <legend class="sr-only"></legend>
        <div class="flex flex-wrap gap-4">

            <!-- Mode: Compact -->
            <div v-if="fieldData.mode === 'compact'" :class="get(form, ['errors', fieldName]) ? 'errorShake' : ''">
                <RadioGroup v-model="form[fieldName]">
                    <RadioGroupLabel class="sr-only">Choose the radio</RadioGroupLabel>
                    <div :class="['grid gap-1', gridClass]">
                        <RadioGroupOption as="template" v-for="option in fieldData.options" :key="option.value"
                            :value="fieldData.valueProp === 'object' || !fieldData.valueProp ? option : option[fieldData.valueProp]"
                            v-slot="{ active, checked }">
                            <div :class="[
                                'cursor-pointer flex items-center justify-center rounded-lg py-3 px-4 text-sm font-medium capitalize transition-all',
                                active ? 'ring-2 ring-indigo-500 ring-offset-2' : '',
                                checked ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-white ring-1 ring-inset ring-gray-300 text-gray-700 hover:bg-gray-50'
                            ]">
                                <RadioGroupLabel as="span">{{ option.value }}</RadioGroupLabel>
                            </div>
                        </RadioGroupOption>
                    </div>
                </RadioGroup>
            </div>

            <!-- Mode: Card -->
            <div v-else-if="fieldData.mode === 'card'">
                <RadioGroup v-model="form[fieldName]">
                    <RadioGroupLabel class="sr-only">Select the radio</RadioGroupLabel>
                    <div :class="['grid gap-1', gridClass]">
                        <RadioGroupOption as="template" v-for="option in fieldData.options" :key="option.value"
                            :value="option[fieldData.valueProp] || option" v-slot="{ active, checked }">
                            <div :class="[
                                'relative flex cursor-pointer rounded-lg border p-3 shadow-sm focus:outline-none transition-all',
                                active ? 'border-indigo-500 ring-2 ring-indigo-500' : 'border-gray-300',
                                checked ? 'bg-indigo-100 border-indigo-400' : 'hover:bg-gray-50'
                            ]">
                                <span class="flex flex-1 flex-col">
                                    <RadioGroupLabel as="span" class="block text-sm font-medium text-gray-700 capitalize">
                                        {{ option.title }}
                                    </RadioGroupLabel>
                                    <RadioGroupDescription v-if="option.description" as="span" class="mt-1 text-xs text-gray-500">
                                        {{ option.description }}
                                    </RadioGroupDescription>
                                    <RadioGroupDescription v-if="option.label" as="span" class="mt-1 text-xs font-medium text-gray-600">
                                        {{ option.label }}
                                    </RadioGroupDescription>
                                </span>
                                <span :class="[
                                    compareObjects(form[fieldName], option) ? 'border-indigo-500' : 'border-transparent',
                                    'pointer-events-none absolute -inset-px rounded-lg'
                                ]" aria-hidden="true" />
                            </div>
                        </RadioGroupOption>
                    </div>
                </RadioGroup>
            </div>

            <!-- Default Mode -->
            <div v-else :class="['grid', gridClass]">
                <div v-for="(option, index) in fieldData.options" :key="option.label + index"
                    class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-all cursor-pointer">
                    <input v-model="form[fieldName]" :id="option.label + index" :name="option.value" type="radio"
                        :value="option.value"
                        class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-0 focus:outline-none cursor-pointer" />
                    <label :for="option.label + index" class="flex items-center gap-x-2 cursor-pointer">
                        <p class="text-sm font-medium text-gray-700 capitalize">{{ option.label ?? option.value }}</p>
                    </label>
                </div>
            </div>

        </div>
    </fieldset>

    <p v-if="get(form, ['errors', fieldName])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</div>
</template>
