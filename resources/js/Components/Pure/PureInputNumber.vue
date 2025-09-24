<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { faCopy } from '@fal'
import { faEye, faEyeSlash } from '@far'
import { faTimesCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faCopy, faEye, faEyeSlash, faTimesCircle, faSpinnerThird)

const props = withDefaults(defineProps<{
    modelValue: string | number | null
    placeholder?: string
    readonly?: boolean
    autofocus?: boolean
    required?: boolean
    minValue?: string | number
    maxValue?: string | number
    suffix?: string
    step?: string
    prefix?: string
    pattern?: string
    disabled?: boolean
}>(), {
    step: "any",
    pattern : "[0-9]*"
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: number): void
    (e: 'blur', value: string | number): void
    (e: 'onEnter', value: string | number): void
    (e: 'input', value: string | number): void
}>()

const value = ref(props.modelValue)

const onChange = (event: Event) => {
    const inputValue = (event.target as HTMLInputElement).value;

    // Check if the input matches the pattern
    const patternRegex = new RegExp(props.pattern || "[0-9]*");

    if (!patternRegex.test(inputValue)) {
        return; // If not, do not proceed further
    }

    let numericValue = parseFloat(inputValue);

    // Ensure the value is within min and max constraints
    if (props.minValue != null && numericValue < parseFloat(props.minValue.toString())) {
        numericValue = parseFloat(props.minValue.toString());
    }
    if (props.maxValue != null && numericValue > parseFloat(props.maxValue.toString())) {
        numericValue = parseFloat(props.maxValue.toString());
    }

    value.value = numericValue;
    emits('update:modelValue', numericValue);
    emits('input', numericValue);
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

defineExpose({
    _inputRef
})

</script>

<template>
    <div class="bg-white w-full flex group relative ring-1 ring-gray-300 focus-within:ring-2 focus-within:ring-gray-500 rounded-md overflow-hidden">
        <div :class="{ 'relative w-full': true, 'flex': prefix, 'pr-6': suffix }">
            <slot v-if="prefix" name="prefix">
                <div
                    class="flex justify-center items-center pl-2 gap-x-1 opacity-50">
                    {{ prefix }}
                </div>
            </slot>

            <input 
                ref="_inputRef" 
                v-model="value" 
                :readonly="readonly" 
                type="number"
                @blur="(event) => emits('blur', event.target.value)"
                @input="onChange"
                @change="onChange"
                @keyup.enter="(event) => emits('onEnter', event.target.value)"
                :placeholder="placeholder || '0'" 
                :autofocus="autofocus" 
                :min="minValue" 
                :max="maxValue" 
                :required="required"
                :disabled
                :step="step"
                class="disabled:bg-gray-100 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none remove-arrows-input block w-full text-gray-600 sm:text-sm placeholder:text-gray-400 border-transparent focus:ring-0 focus:ring-gray-500 focus:outline-0 focus:border-transparent disabled:ring-0 disabled:ring-transparent disabled:focus:border-transparent disabled:focus:border-gray-300 disabled:text-gray-500" 
            />

            <slot v-if="suffix" name="suffix">
                <Transition name="spin-to-down">
                    <div :key="suffix" class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 opacity-50 select-none">
                        {{ suffix }}
                    </div>
                </Transition>
            </slot>
        </div>

        <div class="align-middle">
            <slot name="stateIcon" />
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
</style>
