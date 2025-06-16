<script setup lang="ts">
// import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from '@fortawesome/fontawesome-svg-core'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faExclamationCircle, faCheckCircle } from "@fas"
library.add(faExclamationCircle, faCheckCircle)

defineOptions({
    inheritAttrs: false
})

const props = defineProps<{
    modelValue: string | null
    placeholder?: string
    counter?: boolean
    full?: boolean
    disabled?: boolean
    loading?: boolean
    isSuccess?: boolean
    isError?: boolean
    rows?:Number|null
    inputName?: string
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void
}>()
</script>

<template>
    <div class="relative rounded-lg" :class="full ? 'w-full' : ''">
        <textarea
            :value="modelValue"
            @input="(event: any) => emits('update:modelValue', event.target.value)"
            v-bind="$attrs"
            :disabled="disabled" 
            :name="inputName"
            :id="inputName"
            :rows
            :placeholder="placeholder || trans('Enter text here')"
            class="pr-6 block w-full rounded-md placeholder:text-gray-400 placeholder:italic placeholder:text-xs 
            disabled:text-gray-500 disabled:border-gray-300 disabled:bg-gray-100
            border-gray-300 focus:border-gray-500 focus:ring-gray-500 sm:text-sm"
        />
        <!-- Loading icon -->
        
        <div class="absolute top-2 right-2 flex items-center justify-center">
            <slot name='stateIcon'>
                <FontAwesomeIcon v-if="isError" icon="fas fa-exclamation-circle" class="h-5 w-5 text-red-500" aria-hidden="true" />
                <FontAwesomeIcon v-if="isSuccess" icon="fas fa-check-circle" class="h-5 w-5 text-green-500" aria-hidden="true" />
                <LoadingIcon v-if="loading" class="h-5 w-5 text-gray-500" />
            </slot>
        </div>
    </div>
    <div v-if="counter" class="grid grid-flow-col text-xs italic text-gray-500 mt-2 space-x-12 justify-start tabular-nums">
        <p class="">
            {{ trans('Letters') }}: {{ modelValue.length }}<span v-if="$attrs.maxLength">/{{ $attrs.maxLength }}</span>
        </p>
        <p class="">
            {{ trans('Words') }}: {{ modelValue.trim().split(/\s+/).filter(Boolean).length }}
        </p>
    </div>
</template>