<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { ref } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faEye, faEyeSlash } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faEye, faEyeSlash)

const props = withDefaults(defineProps<{
    modelValue: string
    name?: string
    placeholder?: string
}>(), {
    name: 'password',
    placeholder: 'Enter your password'
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void
}>()


const showPassword = ref(true);
</script>

<template>
    <div class="flex">
        <input 
            :value="modelValue"
            @input="(e: any) => emits('update:modelValue', e.target.value)"
            :id='name'
            :type="showPassword ? 'password' : 'text'" autocomplete="off"
            :placeholder="trans(props.placeholder)"
            class="text-gray-700 placeholder-gray-400 shadow-sm focus:ring-gray-500 focus:border-gray-500 w-full border-gray-300 rounded-l-md" />
            
        <button type="button" @click="showPassword = !showPassword" :id="'show-password-' + name"
            class="w-min px-3 py-2 border border-gray-300 text-sm font-medium rounded-r-md text-gray-700 bg-gray-50 hover:bg-gray-100 focus:outline-hidden focus:ring-1 focus:ring-gray-500 focus:border-gray-500">
            <FontAwesomeIcon aria-hidden="true" class="h-5 w-5 text-gray-400"
                :icon="showPassword ? 'fas fa-eye' : 'fas fa-eye-slash'" />
        </button>
    </div>
</template>