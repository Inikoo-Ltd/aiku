<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    modelValue: { in: boolean; out: boolean }
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: { in: boolean; out: boolean }): void;
}>();

const toggleVisibility = (key: 'in' | 'out') => {
    const newValue = { ...props.modelValue, [key]: !props.modelValue[key] };
    emit('update:modelValue', newValue);
};
</script>

<template>
    <div class=" pb-3 border-gray-300 mb-5 px-2 grid">
        <div  class="w-full my-2 text-start py-1 font-semibold select-none text-sm border-b border-gray-300 pb-1 mb-3">{{ trans('Visibility') }}</div>
        <!-- <div class="border-l-2 border-gray-300 h-6 mx-4"></div> Vertical Line -->
        <div class="flex gap-x-8">
            <div class="flex items-center">
                <input 
                    type="checkbox" 
                    id="loggedIn" 
                    :checked="modelValue.in" 
                    @change="toggleVisibility('in')" 
                    class="form-checkbox h-5 w-5 text-indigo-500 border-gray-300 rounded-sm focus:ring-indigo-500" 
                />
                <label for="loggedIn" class="ml-2 text-gray-700 cursor-pointer hover:text-indigo-600 text-xs">{{ trans('Logged In') }}</label>
            </div>
            <div class="flex items-center">
                <input 
                    type="checkbox" 
                    id="loggedOut" 
                    :checked="modelValue.out" 
                    @change="toggleVisibility('out')" 
                    class="form-checkbox h-5 w-5 text-indigo-500 border-gray-300 rounded-sm focus:ring-indigo-500" 
                />
                <label for="loggedOut" class="ml-2 text-gray-700 cursor-pointer hover:text-indigo-600 text-xs">{{ trans('Logged Out') }}</label>
            </div>
        </div>
    </div>
</template>



<style lang="scss" scoped></style>
