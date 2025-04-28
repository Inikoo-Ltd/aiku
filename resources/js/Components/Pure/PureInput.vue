<script setup lang='ts'>
import { ref, onMounted } from 'vue'
import { useCopyText } from '@/Composables/useCopyText'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy, faCheck } from '@fal'
import {faEye, faEyeSlash} from '@far'
import {faTimesCircle,} from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faCopy, faCheck, faEye, faEyeSlash,faTimesCircle,faSpinnerThird)

const props = withDefaults(defineProps<{
    modelValue: string | number | null | any
    placeholder?: string
    readonly?: boolean
    inputName?: string
    counter?: boolean
    maxLength?: number
    type?: string
    copyButton?: boolean
    autofocus?: boolean
    required?: boolean
    disabled?: boolean
    autocomplete?: string
    minValue?: string | number
    maxValue?: string | number
    caret?: boolean
    pattern?: string 
    clear?:boolean
    suffix?: boolean
    step?: number
    isLoading?: boolean
    align?: string  // 'right' 
    isError?: boolean
    defaultValue?:string
    prefix?: {
        label?: string
        icon?: string
    }
}>(), {
    caret: true,
    type: 'text'
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
    (e: 'onEnter', value: string): void
    (e: 'input', value: string): void
    (e: 'focus', value: Event): void
    (e: 'blur', value: string): void
}>()

const showPassword = ref(props.type)  // default is type = "text"
const handleEyeIcon = () => {
    if(showPassword.value == 'password') {
        showPassword.value = 'text'
    } else {
        showPassword.value = 'password'
    }
    // showPassword.value = showPassword.value == 'text' ? 'password' : props.type
}

const clearValue=()=>{
    emits('update:modelValue', '')
}

const onChange=(event: any)=>{
    emits('update:modelValue', event.target.value)
    emits('input', event.target.value)
}

const _inputRef = ref<HTMLInputElement | null>(null)

// Auto focus on mounted
onMounted(() => {
    if(props.autofocus) {
        _inputRef.value?.focus()
    }
    if(props.defaultValue && !props.modelValue){
        emits('update:modelValue', props.defaultValue)
    }
})

defineExpose({
    _inputRef
})

// Method: On recently copied
const isRecentlyCopied = ref(false)
const onClickCopyButton = async (text: string) => {
    useCopyText(text)
    isRecentlyCopied.value = true
    setTimeout(() => {
        isRecentlyCopied.value = false
    }, 2000)
}

const checkNumber = (event: KeyboardEvent) => {
    if (event.key.length === 1 && isNaN(Number(event.key))) {
        event.preventDefault();
    }
}

</script>

<template>
    <div
        class="bg-white w-full flex relative ring-1 ring-gray-300 focus-within:ring-2 focus-within:ring-gray-500 rounded-md overflow-hidden"
        :class="isError ? 'errorShake' : ''"
    >
        <div class="relative w-full flex items-center">
            <slot name="prefix">
                <div v-if="prefix" class="pl-3 whitespace-nowrap text-gray-400">
                    <FontAwesomeIcon v-if="prefix?.icon" :icon='prefix?.icon' class='' fixed-width aria-hidden='true' />
                    {{ prefix?.label}}
                </div>
            </slot>

            <input
                ref="_inputRef"
                :value="modelValue"
                @input="onChange"
                @keydown="type === 'number' ? checkNumber : false"
                @keyup.enter="(event: any) => emits('onEnter', event.target.value)"
                @focus="(event: any) => emits('focus', event)"
                @blur ="(event: Event) => emits('blur', event.target?.value)"
                :id="inputName"
                :name="inputName"
                :readonly="isLoading || readonly"
                :type="type == 'password' ? showPassword : type"
                :placeholder="placeholder"
                :maxlength="maxLength"
                :autofocus="autofocus"
                :disabled
                :min="minValue"
                :max="maxValue"
                :required="required"
                :step="step"
                :pattern="pattern ?? type == 'number' ? '[0-9]*' : undefined"
                :autocomplete="autocomplete"
                class="remove-arrows-input bg-transparent py-2.5 block w-full
                    text-gray-600 sm:text-sm placeholder:text-gray-400
                    border-transparent
                    focus:ring-0 focus:ring-gray-500 focus:outline-0 focus:border-transparent
                    read-only:bg-gray-100 read-only:ring-0 read-only:ring-transparent read-only:focus:border-transparent read-only:focus:border-gray-300 read-only:text-gray-500
                "
                :class="[
                    caret ? '' : '[appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none',
                    clear && modelValue.length ? 'pl-3 pr-7' : 'px-3',
                    align == 'right' ? 'text-right' : ''
                ]"
            />
            <slot v-if="copyButton" name="copyButton">
                <div class="group flex justify-center items-center absolute inset-y-0 gap-x-1"
                    :class="align === 'right' ? 'left-0' : 'right-0'"
                >
                    <Transition name="spin-to-down">
                        <FontAwesomeIcon v-if="isRecentlyCopied" icon='fal fa-check' class='text-green-500 px-3 h-full text-xxs leading-none ' fixed-width aria-hidden='true' />
                        <FontAwesomeIcon v-else @click="() => onClickCopyButton(modelValue)" icon="fal fa-copy" class="px-3 h-full text-xxs leading-none opacity-20 group-hover:opacity-75 group-active:opacity-100 cursor-pointer" fixed-width aria-hidden="true" />
                    </Transition>
                </div>
            </slot>

            <slot v-if="suffix" name="suffix">
                <div 
                    class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer opacity-20 hover:opacity-75 active:opacity-100"
                    @click="useCopyText(modelValue)"
                >
                <!-- suffix -->
                </div>
            </slot>


            <div v-if="clear && modelValue.length"
                class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer opacity-20 hover:opacity-75 active:opacity-100"
                @click="clearValue()"
            >
               <font-awesome-icon :icon="['fas', 'times-circle']"
                    class="text-lg leading-none"
                    aria-hidden="true" />
            </div>
        </div>

        <!-- Slot: for icon error/success/loading in field edit -->
        <div class="absolute inset-y-0 align-middle flex items-center"
            :class="align === 'right' ? 'left-0' : 'right-0'"
        >
            <Transition name="spin-to-down">
                <div v-if="isLoading" class="px-1">
                    <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-xl' fixed-width aria-hidden='true' />
                </div>
            </Transition>

            <slot name="stateIcon"/>
        </div>


        <button v-if="props.type == 'password'"
            @click="handleEyeIcon"
            type="button"
            name="showPassword"
            class="-ml-px relative inline-flex items-center px-4 py-2 border-l border-gray-300 text-sm font-medium rounded-r-md text-gray-700 bg-gray-50 hover:bg-gray-200 ">
            <FontAwesomeIcon v-show="showPassword == 'text'" aria-hidden="true" class="h-5 w-5 text-gray-400" icon="fa-regular fa-eye"/>
            <FontAwesomeIcon v-show="showPassword == 'password'" aria-hidden="true" class="h-5 w-5 text-gray-400" icon="fa-regular fa-eye-slash"/>
        </button>
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
    appearance: textfield;
}
</style>
