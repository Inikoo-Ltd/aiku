<script setup lang="ts">
import Icon from "@/Components/Icon.vue"

defineProps<{
    options: Array<{
        id: number | string
        name: string
        type?: string
        icon?: string
    }>
    modelValue: number | string | null
}>()

defineEmits(['update:modelValue'])
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 max-h-[60vh] overflow-y-auto p-1">
        <button
            v-for="option in options"
            :key="option.id"
            @click="$emit('update:modelValue', option.id)"
            type="button"
            class="relative min-h-[120px] text-left border rounded-md p-6 transition-all duration-200 flex flex-col justify-between group"
            :class="[
                modelValue === option.id
                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 z-10'
                    : 'border-gray-300 bg-gray-50 hover:bg-gray-100 hover:border-gray-400'
            ]"
        >
            <div class="flex justify-between items-start w-full mb-3">
                <div class="font-semibold text-gray-900 capitalize text-lg pr-2 leading-tight">
                    {{ option.name }}
                </div>
                <Icon
                    v-if="option.icon"
                    :data="{ icon: option.icon }"
                    class="text-2xl flex-shrink-0 transition-colors"
                    :class="modelValue === option.id ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500'"
                />
            </div>

            <div class="mt-auto" v-if="option.type">
                 <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium uppercase tracking-wide"
                    :class="modelValue === option.id ? 'bg-indigo-200 text-indigo-800' : 'bg-gray-200 text-gray-600'">
                    {{ option.type }}
                </span>
            </div>
        </button>
    </div>
</template>
