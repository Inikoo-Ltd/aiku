<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    modelValue: { in: boolean; out: boolean }
    disabled?: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: { in: boolean; out: boolean }): void
}>()

const toggleVisibility = (key: 'in' | 'out') => {
    if (props.disabled) return

    const newValue = { ...props.modelValue, [key]: !props.modelValue[key] }
    emit('update:modelValue', newValue)
}
</script>


<template>
    <div class="pb-3 border-gray-300 mb-5 px-2 grid">
        <div class="w-full my-2 text-start py-1 font-semibold select-none text-sm border-b border-gray-300 pb-1 mb-3">
            {{ trans('Visibility') }}
        </div>

        <div class="flex gap-x-8">
            <div class="flex items-center">
                <input 
                    type="checkbox"
                    id="loggedIn"
                    :checked="modelValue.in"
                    :disabled="disabled"
                    @change="toggleVisibility('in')"
                    class="form-checkbox h-5 w-5 text-indigo-500 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                />
                <label 
                    for="loggedIn"
                    class="ml-2 cursor-pointer text-xs"
                    :class="disabled ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:text-indigo-600'"
                >
                    {{ trans('Logged In') }}
                </label>
            </div>

            <div class="flex items-center">
                <input 
                    type="checkbox"
                    id="loggedOut"
                    :checked="modelValue.out"
                    :disabled="disabled"
                    @change="toggleVisibility('out')"
                    class="form-checkbox h-5 w-5 text-indigo-500 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                />
                <label 
                    for="loggedOut"
                    class="ml-2 cursor-pointer text-xs"
                    :class="disabled ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:text-indigo-600'"
                >
                    {{ trans('Logged Out') }}
                </label>
            </div>
        </div>
    </div>
</template>




<style lang="scss" scoped></style>
