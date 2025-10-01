<script setup lang="ts">

import { RadioGroup, RadioGroupLabel, RadioGroupOption, RadioGroupDescription } from '@headlessui/vue'
import { get } from 'lodash-es'

const props = defineProps(['form', 'fieldName', 'fieldData'])

const compareObjects = (objA, objB) => {
    // Get the keys of objA and objB
    const keysA = Object.keys(objA);
    const keysB = Object.keys(objB);

    // Check if the number of keys is the same
    if (keysA.length !== keysB.length) {
        return false;
    }

    // Check if the values for each key are equal
    for (let key of keysA) {
        if (objA[key] !== objB[key]) {
            return false;
        }
    }

    return true;
}

</script>

<template>
    <div>
        <!-- <label class="text-base font-semibold text-gray-800 capitalize">{{ fieldName }}</label> -->
        <!-- <p class="text-xs text-gray-500 capitalize italic">{{ form[fieldName] }}</p> -->
        <fieldset class="select-none">
            <legend class="sr-only"></legend>
            <div class="flex items-center gap-x-8 gap-y-1 flex-wrap ">
                <!-- Mode Radio: Compact -->
                <div v-if="fieldData.mode === 'compact'" :class="get(form, ['errors', fieldName]) ? 'errorShake' : ''">
                    <RadioGroup v-model="form[fieldName]">
                        <RadioGroupLabel class="sr-only">Choose the radio</RadioGroupLabel>
                        <div class="flex gap-x-1.5 gap-y-1 flex-wrap">
                            <RadioGroupOption as="template" v-for="(option, index) in fieldData.options" :key="option.value"
                                :value="fieldData.valueProp == 'object' || !fieldData.valueProp ? option : option[fieldData.valueProp] "
                                v-slot="{ active, checked }">
                                <div
                                    :class="[
                                        'cursor-pointer focus:outline-none flex items-center justify-center rounded-md py-3 px-3 text-sm font-medium capitalize',
                                        active ? 'ring-2 ring-indigo-600 ring-offset-2' : '',
                                        checked ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'ring-1 ring-inset ring-gray-300 bg-white text-gray-700 hover:bg-gray-50',
                                    ]">
                                    <RadioGroupLabel as="span">{{ option.value }}</RadioGroupLabel>
                                </div>
                            </RadioGroupOption>
                        </div>
                    </RadioGroup>
                </div>

                <!-- Radio: Card -->
                <div v-else-if="fieldData.mode === 'card'">
                <!-- <pre>{{ form[fieldName] }}</pre> -->
                    <RadioGroup v-model="form[fieldName]">
                        <RadioGroupLabel class="text-base font-semibold leading-6 text-gray-700 sr-only">Select the radio</RadioGroupLabel>
                        <div class="flex gap-x-4 justify-around">
                        <RadioGroupOption as="template" v-for="(option, index) in fieldData.options" :key="option.value" :value="option[fieldData.valueProp] || option" v-slot="{ active, checked }">
                            <div :class="[
                                'relative flex cursor-pointer rounded-lg border py-2 px-3 shadow-sm focus:outline-none',
                                active ? 'border-indigo-600 ring-2 ring-indigo-600' : '',
                                checked ? 'bg-indigo-100 border-indigo-300' : 'hover:bg-gray-50'
                            ]">
                            <!-- {{ compareObjects(form[fieldName], option) }} -->
                                <span class="flex flex-1">
                                    <span class="flex flex-col">
                                    <RadioGroupLabel as="span" class="block text-sm font-medium text-gray-700 capitalize">{{ option.title }}</RadioGroupLabel>
                                    <RadioGroupDescription v-if="option.description" as="span" class="mt-1 flex items-center text-xs text-gray-400">{{ option.description }}</RadioGroupDescription>
                                    <RadioGroupDescription v-if="option.label" as="span" class="mt-6 text-xs font-medium text-gray-600">{{ option.label }}</RadioGroupDescription>
                                    </span>
                                </span>
                                <!-- <FontAwesomeIcon icon='far fa-check' :class="[!checked ? 'invisible' : '', 'h-4 w-4 text-indigo-600']" aria-hidden="true" /> -->
                                <span :class="[active ? 'border' : 'border-2', compareObjects(form[fieldName], option) ? 'border-indigo-600' : 'border-transparent', 'pointer-events-none absolute -inset-px rounded-lg']" aria-hidden="true" />
                            </div>
                        </RadioGroupOption>
                        </div>
                    </RadioGroup>
                </div>


                <div v-else-if="fieldData.mode === 'tabs'" :class="get(form, ['errors', fieldName]) ? 'errorShake' : ''">
                    <RadioGroup v-model="form[fieldName]" class="flex space-x-1 rounded-xl bg-white border  flex-wrap gap-y-1">
                            <RadioGroupOption
                                v-for="(option, index) in fieldData.options"
                                :key="option.value"
                                :value="fieldData.valueProp === 'object' || !fieldData.valueProp ? option : option[fieldData.valueProp]"
                                v-slot="{ active, checked }">
                                <div
                                    :class="[ 
                                        'cursor-pointer flex items-center justify-center rounded-xl text-sm font-medium capitalize px-2.5 py-3',
                                        'w-full focus:outline-none',
                                        checked ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'text-gray-700 hover:bg-gray-50'
                                    ]">
                                    <RadioGroupLabel as="span">{{ option.value }}</RadioGroupLabel>
                                </div>
                            </RadioGroupOption>
                    </RadioGroup>
                </div>




                <!-- Radio: Default -->
                <div v-else v-for="(option, index) in fieldData.options"
                    :key="option.label + index" class="inline-flex gap-x-2.5 items-center">
                    <input v-model="form[fieldName]" :id="option.label + index" :key="option.label + index"
                        :name="option.value" type="radio" :value="option.value" :checked="option.value == form[fieldName]"
                        class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-0 focus:outline-none focus:ring-transparent cursor-pointer" />
                    <label :for="option.label + index" class="flex items-center gap-x-1.5 cursor-pointer">
                        <p class="text-sm font-medium leading-6 text-gray-700 capitalize">
                            {{ option.label ?  option.label : option.value }}
                        </p>
                       <!--  <span v-if="option.label" class="font-light text-sm text-gray-400 capitalize">
                            {{ option.label }}
                        </span> -->
                    </label>
                </div>
            </div>
        </fieldset>

        <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
