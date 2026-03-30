<script setup lang="ts">
const props = defineProps<{
    modelValue: number
    min?: number
}>()

const emit = defineEmits(['update:modelValue'])

const increment = () => {
    emit('update:modelValue', (props.modelValue || 1) + 1)
}

const decrement = () => {
    if ((props.modelValue || 1) > (props.min || 1)) {
        emit('update:modelValue', props.modelValue - 1)
    }
}
</script>

<template>
    <div class="flex items-center gap-1 mt-2">
        <button class="px-2 py-1 border rounded hover:bg-gray-100" @click.stop="decrement">
            -
        </button>

        <span class="px-2 min-w-[24px] text-center">
            {{ modelValue || 1 }}
        </span>

        <button class="px-2 py-1 border rounded hover:bg-gray-100" @click.stop="increment">
            +
        </button>
    </div>
</template>