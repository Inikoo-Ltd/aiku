<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useCopyText } from '@/Composables/useCopyText'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy } from '@fal'
import { faEye, faEyeSlash } from '@far'
import { faTimesCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue'
import PureMultiSelect from './PureMultiselect.vue'

library.add(faCopy, faEye, faEyeSlash, faTimesCircle, faSpinnerThird)

const props = withDefaults(defineProps<{
    modelValue: {
        value : number,
        unit : String | Object
    }
    placeholder?: string
    readonly?: boolean
    autofocus?: boolean
    required?: boolean
    minValue?: string | number
    maxValue?: string | number
    suffix?: boolean
    step?: string
    prefix?: boolean
    option:Array
    MultiSelectProps : {
        label: String,
        valueProp: String,
        placeholder?: String,
        classes?: Object
    }
}>(), {
    step: "any" // Default step to allow any decimal value
})


const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
    (e: 'blur', value: string | number): void
    (e: 'onEnter', value: string | number): void
    (e: 'input', value: string | number): void
}>()

const value = ref(props.modelValue)

const onChange = (event: any) => {
    if (props.maxValue != null && value.value > props.maxValue) {
        value.value = props.maxValue
    }
    emits('update:modelValue', value.value)
    emits('input', value.value)
}

const _inputRef = ref<HTMLInputElement | null>(null)

watch(() => props.modelValue, (newValue) => {
    value.value = newValue
})

onMounted(() => {
    if (props.autofocus) {
        _inputRef.value?.focus()
    }
})

console.log(props)

defineExpose({
    _inputRef
})
</script>

<template>
    <div class="bg-white w-full flex group relative ring-1 ring-gray-300 focus-within:ring-2 focus-within:ring-gray-500 rounded-md p-[2px]">
        <div :class="{ 'relative w-full': true, 'flex': prefix }">
            <slot v-if="prefix" name="prefix">
                <div
                    class="flex justify-center items-center px-2 gap-x-1 cursor-pointer opacity-20 hover:opacity-75 active:opacity-100">
                    prefix
                </div>
            </slot>

            <input 
                ref="_inputRef" 
                v-model="value.value" 
                :readonly="readonly" 
                type="number"
                @blur="(event) => emits('blur', event.target.value)"
                @input="onChange"
                @change="onChange"
                @keyup.enter="(event) => emits('onEnter', event.target.value)"
                :placeholder="placeholder" 
                :autofocus="autofocus" 
                :min="minValue" 
                :max="maxValue" 
                :required="required"
                :step="step"
                class="remove-arrows-input bg-transparent block  text-gray-600 sm:text-sm placeholder:text-gray-400 border-transparent focus:ring-0 focus:ring-gray-500 focus:outline-0 focus:border-transparent read-only:bg-gray-100 read-only:ring-0 read-only:ring-transparent read-only:focus:border-transparent read-only:focus:border-gray-300 read-only:text-gray-500" 
                :class="['[appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none']" />

            <slot name="suffix">
                <div class="flex justify-center items-center absolute inset-y-0 right-0 gap-x-1 cursor-pointer w-1/4 opacity-20 hover:opacity-75 active:opacity-100 z-50">
                    <PureMultiSelect 
                        v-model="value.unit"
                        :placeholder="MultiSelectProps.placeholder"
                        :classes="{
                            container : 'border-l-2 border-gray-500 focus-within:border-gray-500 w-full',
                            clearIcon : 'hidden',
                            ...MultiSelectProps.classes
                        }"
                        :options="option"
                        :required="false"
                        :label="MultiSelectProps.label"
                        :valueProp="MultiSelectProps.valueProp"
                        :mode="'single'"
                    />
                </div>
            </slot>
        </div>
    </div>
</template>

<style scoped>
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type=number] {
    -moz-appearance: textfield;
}

.z-50 {
    z-index: 50;
}
</style>
