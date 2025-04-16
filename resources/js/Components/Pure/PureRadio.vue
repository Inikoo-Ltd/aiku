<script setup lang="ts">
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { RadioGroup, RadioGroupLabel, RadioGroupOption, RadioGroupDescription } from '@headlessui/vue'
import { inject } from 'vue'
// import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
// import { faCheck } from '@far'
// import { library } from '@fortawesome/fontawesome-svg-core'
// library.add(faCheck)
const props = defineProps<{
    // modelValue: any
    mode?: string
    options: any
    by?: string
    label?: string  // 'label'
}>()

const model = defineModel()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void
}>()

const layout = inject('layout', layoutStructure)
</script>

<template>
    <div>
        <!-- <label class="text-base font-semibold text-gray-800 capitalize">{{ fieldName }}</label> -->
        <!-- <p class="text-xs text-gray-500 capitalize italic">{{ form[fieldName] }}</p> -->
        <fieldset class="select-none">
            <legend class="sr-only"></legend>
            <div class="flex items-center gap-x-8 gap-y-1 flex-wrap ">
                <!-- Mode Radio: Normal -->
                <div v-if="mode === 'compact'">
                    <RadioGroup class="mt-2"
                        v-model="model"
                        :by="by ?? 'name'"
                    >
                        <RadioGroupLabel class="sr-only">Choose the radio</RadioGroupLabel>
                        <div class="flex gap-x-1.5 gap-y-1 flex-wrap">
                            <RadioGroupOption as="template" v-for="(option, index) in options" :key="option.value"
                                :value="option" v-slot="{ active, checked }">
                                <div
                                    :class="[
                                        'cursor-pointer focus:outline-none flex items-center justify-center rounded-md py-3 px-3 text-sm font-medium capitalize',
                                        active ? 'ring-2 ring-gray-600 ring-offset-2' : '',
                                        checked ? 'bg-gray-600 text-white hover:bg-gray-500' : 'ring-1 ring-inset ring-gray-300 bg-white text-gray-700 hover:bg-gray-50',
                                    ]">
                                    <RadioGroupLabel as="span">{{ props.label ? option[props.label] : option.name }}</RadioGroupLabel>
                                </div>
                            </RadioGroupOption>
                        </div>
                    </RadioGroup>
                </div>

                <!-- Radio: Card -->
                <div v-else-if="mode === 'card'">
                <!-- <pre>{{ form[fieldName] }}</pre> -->
                    <RadioGroup
                        v-model="model"
                        :by="by ?? 'name'"
                    >
                        <RadioGroupLabel class="text-base font-semibold leading-6 text-gray-700 sr-only">Select the radio</RadioGroupLabel>
                        <div class="flex gap-x-4 justify-around">
                            <RadioGroupOption as="template" v-for="(option, index) in options" :key="option.value" :value="option" v-slot="{ active, checked }">
                                <div :class="[
                                    'relative flex cursor-pointer rounded-lg border bg-white py-2 px-3 shadow-sm focus:outline-none',
                                    checked ? 'ring-2 ring-gray-600' : 'border-gray-300'
                                ]">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                        <RadioGroupLabel v-if="option.title" as="span" class="block text-sm font-medium text-gray-700 capitalize">{{ option.title }}</RadioGroupLabel>
                                        <RadioGroupDescription v-if="option.description" as="span" class="mt-1 flex items-center text-xs text-gray-400">{{ option.description }}</RadioGroupDescription>
                                        <RadioGroupDescription v-if="option.label" as="span" class="mt-6 text-xs font-medium text-gray-600">{{ option.label }}</RadioGroupDescription>
                                        </span>
                                    </span>
                                    <!-- <FontAwesomeIcon icon='far fa-check' :class="[!checked ? 'invisible' : '', 'h-4 w-4 text-gray-600']" aria-hidden="true" /> -->
                                    <!-- <span :class="[active ? 'border' : 'border-2', compareObjects(form[fieldName], option) ? 'border-gray-600' : 'border-transparent', 'pointer-events-none absolute -inset-px rounded-lg']" aria-hidden="true" /> -->
                                </div>
                            </RadioGroupOption>
                        </div>
                    </RadioGroup>
                </div>

               <!--  buttonGroup -->
               <div v-else-if="mode === 'buttonGroup'">
                    <RadioGroup class="mt-2"
                        v-model="model"
                        :by="by ?? 'name'"
                    >
                        <div class="flex gap-y-1 flex-wrap">
                            <RadioGroupOption as="template" v-for="(option, index) in options" :key="option.value"
                                :value="option" v-slot="{ active, checked }">
                                <div
                                    :class="[
                                        'cursor-pointer focus:outline-none flex items-center justify-center py-3 px-3 text-sm font-medium capitalize',
                                        active ? 'ring-2 ring-gray-600 ring-offset-2' : '',
                                        checked ? 'bg-gray-600 text-white hover:bg-gray-500' : 'ring-1 ring-inset ring-gray-300 bg-white text-gray-700 hover:bg-gray-50',
                                    ]">
                                    <RadioGroupLabel as="span">{{ option.name }}</RadioGroupLabel>
                                </div>
                            </RadioGroupOption>
                        </div>
                    </RadioGroup>
                </div>

                <!-- Radio: Default -->
                <div v-else v-for="(option, index) in options"
                    :key="`${option.label}${index}`" class="inline-flex gap-x-2.5 items-center">
                    <input
                        v-model="model"
                        :value="by ? option[by] : option"
                        :id="`${option.label}${index}`"
                        type="radio"
                        class="h-4 w-4 border-gray-300 focus:ring-0 focus:outline-none focus:ring-transparent cursor-pointer"
                        :style="{
                            color: layout?.app?.theme?.[0] || '#4F46E5'
                        }"
                    />
                    <label v-if="option.value || option.label" :for="`${option.label}${index}`" class="flex items-center gap-x-1.5 cursor-pointer">
                        <p class="text-sm font-medium leading-6 text-gray-700 capitalize">
                            {{ option.value }}
                        </p>
                        <span v-if="option.label" class="font-light text-sm text-gray-400 capitalize">
                            {{ option.label }}
                            <!-- d -->
                        </span>
                    </label>
                </div>
            </div>
        </fieldset>
    </div>
</template>
